<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ServiceCommissionPayment extends Model
{
    protected $fillable = [
        'shop_id',
        'service_technician_id',
        'service_job_id',
        'paid_by',
        'amount',
        'paid_on',
        'period_month',
        'reference',
        'notes',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'paid_on' => 'date',
    ];

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    public function technician(): BelongsTo
    {
        return $this->belongsTo(ServiceTechnician::class, 'service_technician_id');
    }

    public function job(): BelongsTo
    {
        return $this->belongsTo(ServiceJob::class, 'service_job_id');
    }

    public function payer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'paid_by');
    }
}
