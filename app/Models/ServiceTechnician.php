<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class ServiceTechnician extends Model
{
    protected $fillable = [
        'shop_id',
        'name',
        'phone',
        'email',
        'commission_percent',
        'is_active',
        'notes',
    ];

    protected $casts = [
        'commission_percent' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    public function jobs(): HasMany
    {
        return $this->hasMany(ServiceJob::class);
    }

    public function commissionPayments(): HasMany
    {
        return $this->hasMany(ServiceCommissionPayment::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function earnedCommission(?string $from = null, ?string $to = null): float
    {
        $q = $this->jobs()->completed();
        if ($from) {
            $q->whereDate('served_at', '>=', $from);
        }
        if ($to) {
            $q->whereDate('served_at', '<=', $to);
        }

        return round((float) $q->sum('commission_amount'), 2);
    }

    public function paidCommission(?string $from = null, ?string $to = null): float
    {
        $q = $this->commissionPayments();
        if ($from) {
            $q->whereDate('paid_on', '>=', $from);
        }
        if ($to) {
            $q->whereDate('paid_on', '<=', $to);
        }

        return round((float) $q->sum('amount'), 2);
    }

    /** Sum of commission marked paid/covered on completed bills. */
    public function allocatedCommission(?string $from = null, ?string $to = null): float
    {
        $q = $this->jobs()->completed();
        if ($from) {
            $q->whereDate('served_at', '>=', $from);
        }
        if ($to) {
            $q->whereDate('served_at', '<=', $to);
        }

        return round((float) $q->sum('commission_paid_amount'), 2);
    }

    /** paid − earned (legacy net). Prefer outstanding / advance helpers. */
    public function commissionBalance(): float
    {
        return round($this->paidCommission() - $this->earnedCommission(), 2);
    }

    /**
     * Cash already paid that is not yet allocated to bills.
     * Stays until the owner chooses Deduct advance on payout.
     */
    public function advanceBalance(): float
    {
        return max(0, round($this->paidCommission() - $this->allocatedCommission(), 2));
    }

    /** Unpaid commission still sitting on bills (tech baki). */
    public function outstandingCommission(): float
    {
        return max(0, round($this->earnedCommission() - $this->allocatedCommission(), 2));
    }

    /**
     * Suggested cash payout for a date window: period earnings, capped by all-time due.
     * Does not subtract advance — owner chooses whether to deduct.
     */
    public function suggestedPayoutForPeriod(?string $from = null, ?string $to = null): float
    {
        $outstanding = $this->outstandingCommission();
        if ($outstanding <= 0) {
            return 0.0;
        }
        if (! $from && ! $to) {
            return $outstanding;
        }

        $earnedInPeriod = $this->earnedCommission($from, $to);

        return max(0, min($outstanding, $earnedInPeriod));
    }

    /**
     * One-time cleanup: clear auto-applied bill coverage so advance is never
     * silently deducted. Owner must confirm on Service payout.
     */
    public static function clearAutoAppliedCoverageOnce(): void
    {
        $key = 'service_commission_clear_auto_cover_v2';
        if (Cache::get($key)) {
            return;
        }

        DB::table('service_jobs')->update([
            'commission_paid_amount' => 0,
            'commission_advance_applied' => 0,
        ]);

        Cache::forever($key, true);
    }

    /**
     * Cover unpaid bills FIFO. When $asAdvance, also bump commission_advance_applied.
     * Call only from admin payout (cash pay or explicit Deduct advance).
     */
    public function allocateToUnpaidJobs(float $amount, bool $asAdvance = false): float
    {
        $left = round(max(0, $amount), 2);
        if ($left <= 0.009) {
            return 0.0;
        }

        $allocated = 0.0;
        $jobs = $this->jobs()
            ->completed()
            ->orderBy('served_at')
            ->orderBy('id')
            ->lockForUpdate()
            ->get();

        foreach ($jobs as $job) {
            if ($left <= 0.009) {
                break;
            }

            $due = $job->techBaki();
            if ($due <= 0.009) {
                continue;
            }

            $take = min($due, $left);
            $job->commission_paid_amount = round((float) $job->commission_paid_amount + $take, 2);
            if ($asAdvance) {
                $job->commission_advance_applied = round((float) ($job->commission_advance_applied ?? 0) + $take, 2);
            }
            $job->save();

            $allocated = round($allocated + $take, 2);
            $left = round($left - $take, 2);
        }

        return $allocated;
    }

    /** Apply advance against unpaid bills — only when admin chooses Deduct advance. */
    public function applyAdvanceToDue(?float $upTo = null): float
    {
        $available = $this->advanceBalance();
        $due = $this->outstandingCommission();
        $take = min($available, $due);
        if ($upTo !== null) {
            $take = min($take, max(0, $upTo));
        }

        if ($take <= 0.009) {
            return 0.0;
        }

        return $this->allocateToUnpaidJobs($take, true);
    }
}
