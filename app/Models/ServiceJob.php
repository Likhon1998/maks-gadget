<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ServiceJob extends Model
{
    protected $fillable = [
        'shop_id',
        'service_technician_id',
        'created_by',
        'invoice_no',
        'customer_name',
        'customer_phone',
        'job_description',
        'shop_parts_amount',
        'shop_parts_cost',
        'materials_cost', // outside / other-shop materials (pass-through)
        'labor_cost',
        'total_amount',
        'payment_method',
        'cash_amount',
        'card_amount',
        'mobile_amount',
        'paid_amount',
        'credit_amount',
        'commission_percent',
        'commission_amount',
        'commission_paid_amount',
        'commission_advance_applied',
        'status',
        'served_at',
        'notes',
    ];

    protected $casts = [
        'shop_parts_amount' => 'decimal:2',
        'shop_parts_cost' => 'decimal:2',
        'materials_cost' => 'decimal:2',
        'labor_cost' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'cash_amount' => 'decimal:2',
        'card_amount' => 'decimal:2',
        'mobile_amount' => 'decimal:2',
        'paid_amount' => 'decimal:2',
        'credit_amount' => 'decimal:2',
        'commission_percent' => 'decimal:2',
        'commission_amount' => 'decimal:2',
        'commission_paid_amount' => 'decimal:2',
        'commission_advance_applied' => 'decimal:2',
        'served_at' => 'datetime',
    ];

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    public function technician(): BelongsTo
    {
        return $this->belongsTo(ServiceTechnician::class, 'service_technician_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(ServiceJobItem::class);
    }

    public function outsideItems(): HasMany
    {
        return $this->hasMany(ServiceJobOutsideItem::class)->orderBy('sort_order')->orderBy('id');
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    public function isVoided(): bool
    {
        return $this->status === 'voided';
    }

    /** Outside / other-shop materials collected on the bill. */
    public function outsideMaterialsAmount(): float
    {
        return round((float) $this->materials_cost, 2);
    }

    public function shopPartsProfit(): float
    {
        return max(0, round((float) $this->shop_parts_amount - (float) $this->shop_parts_cost, 2));
    }

    /** Customer still owes on this service bill. */
    public function customerDue(): float
    {
        return max(0, round((float) ($this->credit_amount ?? 0), 2));
    }

    /** Technician commission still unpaid for this bill (after cash + advance applied). */
    public function techBaki(): float
    {
        return max(0, round((float) $this->commission_amount - (float) ($this->commission_paid_amount ?? 0), 2));
    }

    public function commissionAdvanceApplied(): float
    {
        return max(0, round((float) ($this->commission_advance_applied ?? 0), 2));
    }

    public function commissionPayments(): HasMany
    {
        return $this->hasMany(ServiceCommissionPayment::class);
    }

    public static function nextInvoiceNo(int $shopId): string
    {
        $prefix = 'SVC-'.$shopId.'-'.now()->format('Y').'-';
        $latest = static::where('shop_id', $shopId)
            ->where('invoice_no', 'like', $prefix.'%')
            ->orderByDesc('id')
            ->value('invoice_no');

        $seq = 1;
        if ($latest && preg_match('/(\d+)$/', $latest, $m)) {
            $seq = ((int) $m[1]) + 1;
        }

        return $prefix.str_pad((string) $seq, 5, '0', STR_PAD_LEFT);
    }

    /**
     * Commission base = service/labor amount only.
     * Shop inventory profit is never shared. Outside parts are pass-through.
     */
    public static function commissionBase(float $laborAmount): float
    {
        return max(0, round($laborAmount, 2));
    }

    /** Commission % of service/labor amount. */
    public static function computeCommission(float $shareBase, float $percent): float
    {
        return round(max(0, $shareBase) * (max(0, $percent) / 100), 2);
    }
}
