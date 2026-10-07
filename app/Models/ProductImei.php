<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductImei extends Model
{
    public const STATUS_AVAILABLE = 'available';

    public const STATUS_SOLD = 'sold';

    public const STATUS_RESERVED = 'reserved';

    protected $fillable = [
        'product_id',
        'imei',
        'imei2',
        'status',
        'order_id',
        'order_item_id',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class)->withTrashed();
    }

    public function scopeAvailable($query)
    {
        return $query->where('status', self::STATUS_AVAILABLE);
    }

    public static function normalize(string $imei): string
    {
        return preg_replace('/\s+/', '', trim($imei)) ?: '';
    }

    public function scopeMatching($query, string $imei)
    {
        return $query->where(fn ($q) => $q->where('imei', $imei)->orWhere('imei2', $imei));
    }

    public function label(): string
    {
        return $this->imei2 ? $this->imei.' / '.$this->imei2 : $this->imei;
    }

    /**
     * Parse an IMEI list into units. One unit per line; a second IMEI on the
     * same line is separated by "/", "|" or a tab. Commas and semicolons also
     * separate units (legacy single-IMEI lists).
     *
     * @return list<array{imei: string, imei2: ?string}>
     */
    public static function parseUnits(string $raw): array
    {
        $units = [];
        foreach (preg_split('/[\r\n,;]+/', $raw) ?: [] as $line) {
            $parts = array_values(array_filter(
                array_map(fn ($p) => self::normalize($p), preg_split('/[\/|\t]+/', $line) ?: []),
                fn ($p) => $p !== ''
            ));
            if ($parts === []) {
                continue;
            }
            $units[] = [
                'imei' => $parts[0],
                'imei2' => isset($parts[1]) && $parts[1] !== $parts[0] ? $parts[1] : null,
            ];
        }

        return $units;
    }
}
