<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ShopScoped;
use App\Models\Product;
use App\Models\ServiceCommissionPayment;
use App\Models\ServiceJob;
use App\Models\ServiceJobItem;
use App\Models\ServiceJobOutsideItem;
use App\Models\ServiceTechnician;
use App\Services\AccountService;
use App\Services\StockService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use InvalidArgumentException;

class ServiceJobController extends Controller
{
    use ShopScoped;

    public function __construct(
        protected AccountService $accounts,
        protected StockService $stock,
    ) {}

    public function index(Request $request): View
    {
        $shopId = $this->shopId();
        $query = ServiceJob::where('shop_id', $shopId)
            ->with(['technician:id,name,commission_percent', 'creator:id,name'])
            ->latest('served_at');

        if ($request->filled('technician_id')) {
            $query->where('service_technician_id', (int) $request->input('technician_id'));
        }
        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }
        if ($request->filled('q')) {
            $q = trim((string) $request->input('q'));
            $query->where(function ($inner) use ($q) {
                $inner->where('invoice_no', 'like', "%{$q}%")
                    ->orWhere('customer_name', 'like', "%{$q}%")
                    ->orWhere('customer_phone', 'like', "%{$q}%")
                    ->orWhere('job_description', 'like', "%{$q}%");
            });
        }
        if ($request->filled('start_date')) {
            $query->whereDate('served_at', '>=', $request->input('start_date'));
        }
        if ($request->filled('end_date')) {
            $query->whereDate('served_at', '<=', $request->input('end_date'));
        }

        $jobs = $query->paginate(25)->withQueryString();
        $technicians = ServiceTechnician::where('shop_id', $shopId)
            ->orderByDesc('is_active')
            ->orderBy('name')
            ->get(['id', 'name', 'is_active']);

        return view('services.jobs.index', compact('jobs', 'technicians'));
    }

    public function store(Request $request): JsonResponse
    {
        $user = Auth::user();
        if (! $user->canAccessPos()) {
            return response()->json(['success' => false, 'message' => 'POS access required.'], 403);
        }

        $data = $request->validate([
            'service_technician_id' => ['required', 'integer'],
            'customer_name' => ['nullable', 'string', 'max:120'],
            'customer_phone' => ['nullable', 'string', 'max:40'],
            'job_description' => ['nullable', 'string', 'max:5000'],
            'items' => ['nullable', 'array'],
            'items.*.id' => ['required_with:items', 'integer'],
            'items.*.qty' => ['required_with:items', 'integer', 'min:1'],
            'items.*.price' => ['nullable', 'numeric', 'min:0'],
            'outside_items' => ['nullable', 'array'],
            'outside_items.*.name' => ['nullable', 'string', 'max:180'],
            'outside_items.*.amount' => ['nullable', 'numeric', 'min:0'],
            'outside_materials_cost' => ['nullable', 'numeric', 'min:0'],
            'materials_cost' => ['nullable', 'numeric', 'min:0'],
            'labor_cost' => ['required', 'numeric', 'min:0'],
            'commission_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'commission_paid_now' => ['nullable', 'numeric', 'min:0'],
            'payment_method' => ['nullable', 'string', 'in:cash,card,bkash,mixed'],
            'cash_amount' => ['nullable', 'numeric', 'min:0'],
            'card_amount' => ['nullable', 'numeric', 'min:0'],
            'mobile_amount' => ['nullable', 'numeric', 'min:0'],
            'paid_amount' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $shopId = $this->shopId();
        $technician = ServiceTechnician::where('shop_id', $shopId)
            ->where('is_active', true)
            ->findOrFail($data['service_technician_id']);

        $outsideRows = collect($data['outside_items'] ?? [])
            ->map(function ($row, $idx) {
                $name = trim((string) ($row['name'] ?? ''));
                $amount = round((float) ($row['amount'] ?? 0), 2);
                if ($name === '' && $amount <= 0) {
                    return null;
                }
                if ($name === '') {
                    $name = 'Product';
                }

                return [
                    'name' => mb_substr($name, 0, 180),
                    'amount' => max(0, $amount),
                    'sort_order' => (int) $idx,
                ];
            })
            ->filter()
            ->values();

        $outside = round((float) $outsideRows->sum('amount'), 2);
        if ($outside <= 0) {
            // Legacy single-field fallback
            $outside = round((float) ($data['outside_materials_cost'] ?? $data['materials_cost'] ?? 0), 2);
        }
        $labor = round((float) $data['labor_cost'], 2);
        $rawItems = collect($data['items'] ?? []);

        try {
            $payload = DB::transaction(function () use ($shopId, $user, $technician, $data, $outside, $outsideRows, $labor, $rawItems) {
                $lineRows = [];
                $shopPartsAmount = 0.0;
                $shopPartsCost = 0.0;

                foreach ($rawItems as $item) {
                    $product = Product::where('shop_id', $shopId)
                        ->whereKey((int) $item['id'])
                        ->lockForUpdate()
                        ->firstOrFail();

                    if ($product->requires_imei) {
                        throw new InvalidArgumentException(
                            "{$product->name} needs IMEI tracking — sell it in Sales mode, or use a non-IMEI parts SKU."
                        );
                    }

                    $qty = (int) $item['qty'];
                    $available = $product->availableStock();
                    if ($qty > $available) {
                        throw new InvalidArgumentException(
                            "Insufficient stock for {$product->name}. Available: {$available}"
                        );
                    }

                    $unitPrice = array_key_exists('price', $item) && $item['price'] !== null
                        ? round((float) $item['price'], 2)
                        : round((float) $product->selling_price, 2);
                    $unitCost = round((float) $product->cost_price, 2);
                    $subtotal = round($unitPrice * $qty, 2);
                    $lineCost = round($unitCost * $qty, 2);

                    $lineRows[] = [
                        'product' => $product,
                        'qty' => $qty,
                        'unit_price' => $unitPrice,
                        'unit_cost' => $unitCost,
                        'subtotal' => $subtotal,
                        'line_cost' => $lineCost,
                    ];
                    $shopPartsAmount = round($shopPartsAmount + $subtotal, 2);
                    $shopPartsCost = round($shopPartsCost + $lineCost, 2);
                }

                $total = round($shopPartsAmount + $outside + $labor, 2);
                if ($total <= 0) {
                    throw new InvalidArgumentException(
                        'Add shop parts, products, and/or service amount greater than zero.'
                    );
                }

                $cashIn = round((float) ($data['cash_amount'] ?? 0), 2);
                $cardIn = round((float) ($data['card_amount'] ?? 0), 2);
                $mobileIn = round((float) ($data['mobile_amount'] ?? 0), 2);
                $received = round($cashIn + $cardIn + $mobileIn, 2);

                if ($received <= 0 && array_key_exists('paid_amount', $data) && $data['paid_amount'] !== null) {
                    $received = round((float) $data['paid_amount'], 2);
                    $method = strtolower((string) ($data['payment_method'] ?? 'cash'));
                    if ($method === 'card') {
                        $cardIn = $received;
                    } elseif (in_array($method, ['bkash', 'mobile'], true)) {
                        $mobileIn = $received;
                    } else {
                        $cashIn = $received;
                    }
                }

                // Apply received to bill; overpay becomes change (not stored as paid)
                $paid = min($received, $total);
                $change = max(0, round($received - $total, 2));
                $credit = max(0, round($total - $paid, 2));

                // Scale tender lines down to amount applied (change comes from cash first)
                $appliedCash = $cashIn;
                $appliedCard = $cardIn;
                $appliedMobile = $mobileIn;
                if ($change > 0.009) {
                    $left = $change;
                    $fromCash = min($appliedCash, $left);
                    $appliedCash = round($appliedCash - $fromCash, 2);
                    $left = round($left - $fromCash, 2);
                    if ($left > 0.009) {
                        $fromCard = min($appliedCard, $left);
                        $appliedCard = round($appliedCard - $fromCard, 2);
                        $left = round($left - $fromCard, 2);
                    }
                    if ($left > 0.009) {
                        $appliedMobile = round(max(0, $appliedMobile - $left), 2);
                    }
                }

                $tenderCount = (int) ($appliedCash > 0.009) + (int) ($appliedCard > 0.009) + (int) ($appliedMobile > 0.009);
                if ($tenderCount > 1) {
                    $paymentMethod = 'mixed';
                } elseif ($appliedCard > 0.009) {
                    $paymentMethod = 'card';
                } elseif ($appliedMobile > 0.009) {
                    $paymentMethod = 'bkash';
                } else {
                    $paymentMethod = 'cash';
                }

                $percent = array_key_exists('commission_percent', $data) && $data['commission_percent'] !== null
                    ? round((float) $data['commission_percent'], 2)
                    : round((float) $technician->commission_percent, 2);
                // Shop inventory profit is NOT shared — commission on service amount only
                $commission = ServiceJob::computeCommission(
                    ServiceJob::commissionBase($labor),
                    $percent
                );

                // POS never pays the tech — full commission stays as due.
                // Advance is never auto-cut; admin decides on Service payout.
                $cashToTech = 0.0;
                $advanceApplied = 0.0;
                $covered = 0.0;
                $newAdvanceFromCash = 0.0;
                $techBakiOnJob = round($commission, 2);

                $job = ServiceJob::create([
                    'shop_id' => $shopId,
                    'service_technician_id' => $technician->id,
                    'created_by' => $user->id,
                    'invoice_no' => ServiceJob::nextInvoiceNo($shopId),
                    'customer_name' => $data['customer_name'] ?? null,
                    'customer_phone' => $data['customer_phone'] ?? null,
                    'job_description' => trim((string) ($data['job_description'] ?? '')),
                    'shop_parts_amount' => $shopPartsAmount,
                    'shop_parts_cost' => $shopPartsCost,
                    'materials_cost' => $outside,
                    'labor_cost' => $labor,
                    'total_amount' => $total,
                    'payment_method' => $paymentMethod,
                    'cash_amount' => $appliedCash,
                    'card_amount' => $appliedCard,
                    'mobile_amount' => $appliedMobile,
                    'paid_amount' => $paid,
                    'credit_amount' => $credit,
                    'commission_percent' => $percent,
                    'commission_amount' => $commission,
                    'commission_paid_amount' => $covered,
                    'commission_advance_applied' => $advanceApplied,
                    'status' => 'completed',
                    'served_at' => now(),
                    'notes' => $data['notes'] ?? null,
                ]);

                foreach ($outsideRows as $outRow) {
                    ServiceJobOutsideItem::create([
                        'service_job_id' => $job->id,
                        'name' => $outRow['name'],
                        'amount' => $outRow['amount'],
                        'sort_order' => $outRow['sort_order'],
                    ]);
                }

                // Legacy single outside amount with no line items
                if ($outsideRows->isEmpty() && $outside > 0.009) {
                    ServiceJobOutsideItem::create([
                        'service_job_id' => $job->id,
                        'name' => 'Product',
                        'amount' => $outside,
                        'sort_order' => 0,
                    ]);
                }

                $stockUpdates = [];
                foreach ($lineRows as $row) {
                    /** @var Product $product */
                    $product = $row['product'];

                    ServiceJobItem::create([
                        'service_job_id' => $job->id,
                        'product_id' => $product->id,
                        'product_name' => $product->name,
                        'quantity' => $row['qty'],
                        'unit_price' => $row['unit_price'],
                        'unit_cost' => $row['unit_cost'],
                        'subtotal' => $row['subtotal'],
                        'line_cost' => $row['line_cost'],
                    ]);

                    $this->stock->recordSale(
                        $product,
                        $row['qty'],
                        'Service - '.$job->invoice_no,
                        $user->id,
                        'service_job',
                        $job->id,
                    );

                    $product->refresh();
                    $stockUpdates[] = [
                        'id' => $product->id,
                        'stock_quantity' => (int) $product->stock_quantity,
                    ];
                }

                $this->accounts->postServiceJob($job, $user->counter_id);

                if ($cashToTech > 0.009) {
                    $noteParts = ['Paid at service checkout'];
                    if ($cashTowardCommission > 0.009) {
                        $noteParts[] = 'toward commission ৳'.number_format($cashTowardCommission, 2);
                    }
                    if ($newAdvanceFromCash > 0.009) {
                        $noteParts[] = 'advance ৳'.number_format($newAdvanceFromCash, 2);
                    }
                    if ($advanceApplied > 0.009) {
                        $noteParts[] = 'prior advance applied ৳'.number_format($advanceApplied, 2);
                    }

                    $payout = ServiceCommissionPayment::create([
                        'shop_id' => $shopId,
                        'service_technician_id' => $technician->id,
                        'service_job_id' => $job->id,
                        'paid_by' => $user->id,
                        'amount' => $cashToTech,
                        'paid_on' => now()->toDateString(),
                        'period_month' => now()->format('Y-m'),
                        'reference' => $job->invoice_no,
                        'notes' => implode(' · ', $noteParts),
                    ]);
                    $this->accounts->postServiceCommissionPayment($payout);
                }

                $technician->refresh();
                $advanceAfter = $technician->advanceBalance();
                $outstandingAfter = $technician->outstandingCommission();

                return compact('job', 'stockUpdates', 'change', 'advanceApplied', 'newAdvanceFromCash', 'techBakiOnJob', 'advanceAfter', 'outstandingAfter', 'cashToTech');
            });
        } catch (InvalidArgumentException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        } catch (\Throwable $e) {
            report($e);

            return response()->json([
                'success' => false,
                'message' => 'Could not save service bill: '.$e->getMessage(),
            ], 500);
        }

        /** @var ServiceJob $job */
        $job = $payload['job'];

        $techBaki = (float) ($payload['techBakiOnJob'] ?? $job->techBaki());
        $advanceAfter = (float) ($payload['advanceAfter'] ?? 0);
        $newAdvance = (float) ($payload['newAdvanceFromCash'] ?? 0);
        $advanceApplied = (float) ($payload['advanceApplied'] ?? 0);

        $message = 'Service bill '.$job->invoice_no.' saved.';
        if ($techBaki > 0.009) {
            $message .= ' Tech commission due ৳'.number_format($techBaki, 2).' (pay from Service).';
        }
        if ($advanceAfter > 0.009) {
            $message .= ' Advance on hand ৳'.number_format($advanceAfter, 2).'.';
        }

        return response()->json([
            'success' => true,
            'message' => $message,
            'job' => [
                'id' => $job->id,
                'invoice_no' => $job->invoice_no,
                'total_amount' => (float) $job->total_amount,
                'shop_parts_amount' => (float) $job->shop_parts_amount,
                'outside_materials_cost' => (float) $job->materials_cost,
                'labor_cost' => (float) $job->labor_cost,
                'paid_amount' => (float) $job->paid_amount,
                'credit_amount' => (float) $job->credit_amount,
                'commission_amount' => (float) $job->commission_amount,
                'commission_paid_amount' => (float) $job->commission_paid_amount,
                'commission_advance_applied' => (float) ($job->commission_advance_applied ?? 0),
                'tech_baki' => $techBaki,
                'tech_advance_after' => $advanceAfter,
                'tech_outstanding_after' => (float) ($payload['outstandingAfter'] ?? 0),
                'change' => (float) ($payload['change'] ?? 0),
            ],
            'technician' => [
                'id' => (int) $job->service_technician_id,
                'advance_balance' => $advanceAfter,
                'outstanding_commission' => (float) ($payload['outstandingAfter'] ?? 0),
            ],
            'stock_updates' => $payload['stockUpdates'],
            'receipt_url' => route('services.jobs.receipt', $job),
        ]);
    }

    public function receipt(ServiceJob $job): View
    {
        $this->authorizeShop($job);
        $job->load(['technician', 'creator', 'shop', 'items', 'outsideItems']);

        return view('services.jobs.receipt', compact('job'));
    }

    public function void(ServiceJob $job): RedirectResponse
    {
        $this->authorizeShop($job);

        if ($job->isVoided()) {
            return back()->with('error', 'This service bill is already voided.');
        }

        DB::transaction(function () use ($job) {
            $job->load('items.product');

            foreach ($job->items as $item) {
                if (! $item->product || $item->quantity < 1) {
                    continue;
                }
                $this->stock->restockForDocument(
                    $item->product,
                    (int) $item->quantity,
                    'Void service '.$job->invoice_no,
                    'service_job',
                    $job->id,
                    'service_void',
                    Auth::id(),
                );
            }

            $job->update(['status' => 'voided']);
            $this->accounts->postServiceJobVoid($job);
        });

        return back()->with('success', 'Service bill '.$job->invoice_no.' voided.');
    }
}
