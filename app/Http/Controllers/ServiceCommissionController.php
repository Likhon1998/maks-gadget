<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ShopScoped;
use App\Models\ServiceCommissionPayment;
use App\Models\ServiceJob;
use App\Models\ServiceTechnician;
use App\Services\AccountService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class ServiceCommissionController extends Controller
{
    use ShopScoped;

    public function __construct(protected AccountService $accounts) {}

    /** Admin day-end hub: pay technicians (due / partial / advance). */
    public function index(Request $request): View
    {
        // One-time: undo silent auto-cover so advance only deducts when admin confirms.
        ServiceTechnician::clearAutoAppliedCoverageOnce();

        $shopId = $this->shopId();
        $today = now()->toDateString();
        $windows = $this->payRangeWindows();
        $range = $request->query('range', 'all');
        if (! array_key_exists($range, $windows)) {
            $range = 'all';
        }
        [$from, $to, $rangeLabel] = $windows[$range];

        $technicians = ServiceTechnician::where('shop_id', $shopId)
            ->orderByDesc('is_active')
            ->orderBy('name')
            ->get()
            ->map(function (ServiceTechnician $tech) use ($today, $from, $to) {
                $tech->earned = $tech->earnedCommission();
                $tech->paid = $tech->paidCommission();
                $tech->outstanding = $tech->outstandingCommission();
                $tech->advance = $tech->advanceBalance();
                $tech->earned_today = $tech->earnedCommission($today, $today);
                $tech->jobs_today = $tech->jobs()->completed()->whereDate('served_at', $today)->count();
                $tech->period_earned = $tech->earnedCommission($from, $to);
                $tech->period_suggested = $tech->suggestedPayoutForPeriod($from, $to);

                return $tech;
            });

        $totals = [
            'earned' => round((float) $technicians->sum('earned'), 2),
            'paid' => round((float) $technicians->sum('paid'), 2),
            'outstanding' => round((float) $technicians->sum('outstanding'), 2),
            'advance' => round((float) $technicians->sum('advance'), 2),
            'earned_today' => round((float) $technicians->sum('earned_today'), 2),
            'jobs_today' => (int) $technicians->sum('jobs_today'),
            'period_earned' => round((float) $technicians->sum('period_earned'), 2),
            'period_suggested' => round((float) $technicians->sum('period_suggested'), 2),
        ];

        $recentPayments = ServiceCommissionPayment::where('shop_id', $shopId)
            ->with('technician:id,name')
            ->latest('paid_on')
            ->latest('id')
            ->limit(20)
            ->get();

        return view('services.commissions.index', compact(
            'technicians',
            'totals',
            'recentPayments',
            'today',
            'range',
            'rangeLabel'
        ));
    }

    public function create(Request $request, ServiceTechnician $technician): View
    {
        $this->authorizeShop($technician);

        $earned = $technician->earnedCommission();
        $paid = $technician->paidCommission();
        $outstanding = $technician->outstandingCommission();
        $advance = $technician->advanceBalance();
        $today = now()->toDateString();
        $earnedToday = $technician->earnedCommission($today, $today);

        $windows = $this->payRangeWindows();
        $range = $request->query('range', 'all');
        if (! array_key_exists($range, $windows)) {
            $range = 'all';
        }

        $periodStats = [];
        foreach ($windows as $key => [$from, $to, $label]) {
            $periodStats[$key] = [
                'label' => $label,
                'from' => $from,
                'to' => $to,
                'earned' => $technician->earnedCommission($from, $to),
                'suggested' => $technician->suggestedPayoutForPeriod($from, $to),
            ];
        }

        $suggested = $periodStats[$range]['suggested'] ?? $outstanding;
        $applyAdvanceDefault = $advance > 0.009 && $outstanding > 0.009;

        return view('services.commissions.create', compact(
            'technician',
            'earned',
            'paid',
            'outstanding',
            'advance',
            'earnedToday',
            'periodStats',
            'range',
            'suggested',
            'applyAdvanceDefault'
        ));
    }

    public function store(Request $request, ServiceTechnician $technician): RedirectResponse
    {
        $this->authorizeShop($technician);

        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:0'],
            'commission_percent' => ['required', 'numeric', 'min:0', 'max:100'],
            'paid_on' => ['required', 'date'],
            'period_month' => ['nullable', 'regex:/^\d{4}-\d{2}$/'],
            'pay_range' => ['nullable', 'in:today,7d,month,30d,all'],
            'apply_advance' => ['nullable', 'boolean'],
            'reference' => ['nullable', 'string', 'max:120'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $amount = round((float) $data['amount'], 2);
        $newPct = round((float) $data['commission_percent'], 2);
        $applyAdvance = $request->boolean('apply_advance');

        if ($amount < 0.01 && ! $applyAdvance) {
            return back()->withInput()->with('error', 'Enter a pay amount, or choose Deduct advance.');
        }

        $windows = $this->payRangeWindows();
        $payRange = $data['pay_range'] ?? 'all';
        $rangeLabel = $windows[$payRange][2] ?? 'All due';
        $period = $data['period_month'] ?? now()->format('Y-m');

        $result = DB::transaction(function () use ($technician, $data, $amount, $period, $newPct, $applyAdvance, $rangeLabel) {
            if (abs($newPct - (float) $technician->commission_percent) > 0.009) {
                $technician->update(['commission_percent' => $newPct]);
            }

            $keptAdvance = (! $applyAdvance && $technician->advanceBalance() > 0.009);

            $advanceApplied = 0.0;
            if ($applyAdvance) {
                $advanceApplied = $technician->applyAdvanceToDue();
            }

            $dueBeforeCash = $technician->outstandingCommission();
            $towardDue = min($amount, $dueBeforeCash);
            $asAdvance = max(0, round($amount - $towardDue, 2));

            $noteParts = array_filter([
                $data['notes'] ?? null,
                'Range: '.$rangeLabel,
                $advanceApplied > 0.009 ? 'Advance deducted Tk '.number_format($advanceApplied, 2) : null,
                $towardDue > 0.009 ? 'Toward due Tk '.number_format($towardDue, 2) : null,
                $asAdvance > 0.009 ? 'New advance Tk '.number_format($asAdvance, 2) : null,
                $keptAdvance ? 'Advance kept (not deducted)' : null,
            ]);

            if ($amount >= 0.01) {
                $payment = ServiceCommissionPayment::create([
                    'shop_id' => $this->shopId(),
                    'service_technician_id' => $technician->id,
                    'paid_by' => Auth::id(),
                    'amount' => $amount,
                    'paid_on' => $data['paid_on'],
                    'period_month' => $period,
                    'reference' => $data['reference'] ?? null,
                    'notes' => $noteParts ? implode(' · ', $noteParts) : null,
                ]);

                $this->accounts->postServiceCommissionPayment($payment);

                // Allocate cash onto unpaid bills; leftover stays as advance.
                if ($towardDue > 0.009) {
                    $technician->allocateToUnpaidJobs($towardDue, false);
                }
            } elseif ($advanceApplied <= 0.009) {
                abort(422, 'Nothing to record.');
            }

            $technician->refresh();

            return [
                'toward_due' => $towardDue,
                'as_advance' => $asAdvance,
                'advance_applied' => $advanceApplied,
                'due_after' => $technician->outstandingCommission(),
                'advance_after' => $technician->advanceBalance(),
                'amount' => $amount,
            ];
        });

        if (($result['amount'] ?? 0) >= 0.01) {
            $msg = 'Payment Tk '.number_format($result['amount'], 2).' recorded for '.$technician->name.' ('.$rangeLabel.').';
        } else {
            $msg = 'Advance applied for '.$technician->name.'.';
        }
        if (($result['advance_applied'] ?? 0) > 0.009) {
            $msg .= ' Deducted advance Tk '.number_format($result['advance_applied'], 2).'.';
        }
        if (($result['as_advance'] ?? 0) > 0.009) {
            $msg .= ' Extra counted as advance Tk '.number_format($result['as_advance'], 2).'.';
        }
        if (($result['due_after'] ?? 0) > 0.009) {
            $msg .= ' Remaining due Tk '.number_format($result['due_after'], 2).'.';
        }

        return redirect()
            ->route('services.payments.index')
            ->with('success', $msg);
    }

    /** @return array<string, array{0:?string,1:?string,2:string}> */
    private function payRangeWindows(): array
    {
        $today = now()->toDateString();

        return [
            'today' => [$today, $today, 'Today'],
            '7d' => [now()->subDays(6)->toDateString(), $today, 'Last 7 days'],
            'month' => [now()->startOfMonth()->toDateString(), $today, 'This month'],
            '30d' => [now()->subDays(29)->toDateString(), $today, 'Last 30 days'],
            'all' => [null, null, 'All time'],
        ];
    }

    /** Admin: gross / individual / per-bill commission controls. */
    public function settings(): View
    {
        $shopId = $this->shopId();

        $technicians = ServiceTechnician::where('shop_id', $shopId)
            ->orderByDesc('is_active')
            ->orderBy('name')
            ->get()
            ->map(function (ServiceTechnician $tech) {
                $tech->outstanding = $tech->outstandingCommission();

                return $tech;
            });

        $defaultPercent = round((float) ($technicians->firstWhere('is_active', true)?->commission_percent
            ?? $technicians->first()?->commission_percent
            ?? 10), 2);

        $recentJobs = ServiceJob::where('shop_id', $shopId)
            ->with('technician:id,name')
            ->latest('served_at')
            ->limit(40)
            ->get();

        return view('services.commission.settings', compact('technicians', 'defaultPercent', 'recentJobs'));
    }

    public function updateGross(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'commission_percent' => ['required', 'numeric', 'min:0', 'max:100'],
            'include_inactive' => ['sometimes', 'boolean'],
        ]);

        $pct = round((float) $data['commission_percent'], 2);
        $query = ServiceTechnician::where('shop_id', $this->shopId());
        if (! $request->boolean('include_inactive')) {
            $query->where('is_active', true);
        }

        $updated = $query->update(['commission_percent' => $pct]);

        return redirect()
            ->route('services.commission.settings')
            ->with('success', 'Gross commission '.$pct.'% applied to '.$updated.' technician(s). Future bills use this rate.');
    }

    public function updateTechnician(Request $request, ServiceTechnician $technician): RedirectResponse
    {
        $this->authorizeShop($technician);

        $data = $request->validate([
            'commission_percent' => ['required', 'numeric', 'min:0', 'max:100'],
        ]);

        $pct = round((float) $data['commission_percent'], 2);
        $technician->update(['commission_percent' => $pct]);

        return redirect()
            ->route('services.commission.settings')
            ->with('success', $technician->name.' commission set to '.$pct.'%.');
    }

    public function updateJob(Request $request, ServiceJob $job): RedirectResponse
    {
        $this->authorizeShop($job);

        if ($job->isVoided()) {
            return redirect()
                ->route('services.commission.settings')
                ->with('error', 'Cannot change commission on a voided bill.');
        }

        $data = $request->validate([
            'commission_percent' => ['required', 'numeric', 'min:0', 'max:100'],
        ]);

        $pct = round((float) $data['commission_percent'], 2);
        $base = ServiceJob::commissionBase((float) $job->labor_cost);
        $amount = ServiceJob::computeCommission($base, $pct);
        $alreadyPaid = round((float) ($job->commission_paid_amount ?? 0), 2);

        // Keep paid coverage capped to the new commission amount
        $job->update([
            'commission_percent' => $pct,
            'commission_amount' => $amount,
            'commission_paid_amount' => min($alreadyPaid, $amount),
        ]);

        return redirect()
            ->route('services.commission.settings')
            ->with('success', $job->invoice_no.' commission updated to '.$pct.'% (৳'.number_format($amount, 2).').');
    }
}
