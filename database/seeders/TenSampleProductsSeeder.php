<?php

namespace Database\Seeders;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\Shop;
use App\Models\SiteSetting;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Seeds exactly 10 shop products with images — mix of retail + service parts.
 */
class TenSampleProductsSeeder extends Seeder
{
    public function run(): void
    {
        // Local/demo only — never seed sample catalog in production.
        if (! app()->environment(['local', 'development', 'testing'])) {
            $this->command?->warn('TenSampleProductsSeeder skipped (local/demo only).');

            return;
        }

        $shop = SiteSetting::query()->first()?->defaultShop
            ?? Shop::query()->orderBy('id')->first();

        if (! $shop) {
            $this->command?->warn('No shop found — skip TenSampleProductsSeeder.');

            return;
        }

        $categories = $this->seedCategories($shop->id);
        $brands = $this->seedBrands($shop->id);
        $created = 0;

        foreach ($this->catalog() as $i => $item) {
            $n = $i + 1;
            $barcode = 'SAMPLE-'.str_pad((string) $n, 3, '0', STR_PAD_LEFT);
            $category = $categories[$item['category']] ?? null;
            $brand = $brands[$item['brand']] ?? null;

            $imagePath = $this->storeRemoteImage(
                $item['image'],
                'products',
                'sample-'.str_pad((string) $n, 3, '0', STR_PAD_LEFT).'.jpg'
            );

            $product = Product::updateOrCreate(
                ['shop_id' => $shop->id, 'barcode' => $barcode],
                [
                    'name' => $item['name'],
                    'sku' => $item['sku'],
                    'category_id' => $category?->id,
                    'brand_id' => $brand?->id,
                    'brand_name' => $brand?->name ?? $item['brand'],
                    'color' => $item['color'] ?? null,
                    'color_hex' => $item['color_hex'] ?? null,
                    'cost_price' => $item['cost'],
                    'selling_price' => $item['price'],
                    'original_price' => $item['original'] ?? $item['price'],
                    'stock_quantity' => $item['stock'],
                    'alert_quantity' => 5,
                    'availability' => 'in_stock',
                    'short_description' => $item['desc'],
                    'image' => $imagePath,
                    'requires_imei' => false,
                    'rating' => $item['rating'] ?? 4.6,
                    'review_count' => $item['reviews'] ?? random_int(18, 160),
                    'is_published' => true,
                    'is_new_arrival' => (bool) ($item['new'] ?? false),
                    'is_best_seller' => (bool) ($item['best'] ?? false),
                    'is_featured' => (bool) ($item['featured'] ?? false),
                ]
            );

            if ($imagePath) {
                ProductImage::updateOrCreate(
                    ['product_id' => $product->id, 'path' => $imagePath],
                    ['sort_order' => 0]
                );
            }

            $created++;
            $this->command?->info("  {$n}. {$product->name}".($imagePath ? ' ✓ image' : ' (no image)'));
        }

        $this->command?->info("Seeded {$created} sample products for {$shop->name}.");
    }

    private function seedCategories(int $shopId): array
    {
        $defs = [
            ['name' => 'Chargers & Cables', 'icon' => 'plug'],
            ['name' => 'Power Bank', 'icon' => 'battery'],
            ['name' => 'Earbuds', 'icon' => 'earbuds'],
            ['name' => 'Accessories', 'icon' => 'mouse'],
            ['name' => 'Speakers', 'icon' => 'speaker'],
            ['name' => 'Service Parts', 'icon' => 'phone'],
            ['name' => 'Smartwatches', 'icon' => 'watch'],
        ];

        $map = [];
        foreach ($defs as $def) {
            $map[$def['name']] = Category::updateOrCreate(
                ['shop_id' => $shopId, 'slug' => Str::slug($def['name'])],
                [
                    'name' => $def['name'],
                    'icon' => $def['icon'],
                    'description' => $def['name'].' for Maks Gadget',
                    'is_featured' => true,
                ]
            );
        }

        return $map;
    }

    private function seedBrands(int $shopId): array
    {
        $names = ['Anker', 'Baseus', 'Oraimo', 'JBL', 'Samsung', 'Apple', 'Spigen', 'Xiaomi'];
        $map = [];
        foreach ($names as $i => $name) {
            $map[$name] = Brand::updateOrCreate(
                ['shop_id' => $shopId, 'name' => $name],
                ['sort_order' => $i + 1, 'is_active' => true]
            );
        }

        return $map;
    }

    /** @return list<array<string, mixed>> */
    private function catalog(): array
    {
        return [
            [
                'name' => 'Anker 20W USB-C Fast Charger',
                'sku' => 'CHK-ANK-20W',
                'brand' => 'Anker',
                'category' => 'Chargers & Cables',
                'color' => 'White',
                'color_hex' => '#f8fafc',
                'cost' => 850,
                'price' => 1290,
                'stock' => 40,
                'desc' => 'Compact 20W PD wall charger for phones and earbuds.',
                'image' => 'https://images.unsplash.com/photo-1583863788434-e58a36330cf0?w=800&q=80',
                'best' => true,
                'featured' => true,
            ],
            [
                'name' => 'Baseus USB-C to USB-C Cable 1m',
                'sku' => 'CBL-BAS-1M',
                'brand' => 'Baseus',
                'category' => 'Chargers & Cables',
                'color' => 'Black',
                'color_hex' => '#0f172a',
                'cost' => 220,
                'price' => 450,
                'stock' => 80,
                'desc' => 'Durable 60W-capable braided Type-C cable.',
                'image' => 'https://images.unsplash.com/photo-1558618666-fcd25c85cd64?w=800&q=80',
            ],
            [
                'name' => 'Oraimo 20000mAh Power Bank',
                'sku' => 'PB-ORA-20K',
                'brand' => 'Oraimo',
                'category' => 'Power Bank',
                'color' => 'Black',
                'color_hex' => '#111827',
                'cost' => 1450,
                'price' => 2190,
                'stock' => 25,
                'desc' => 'Dual-output power bank for travel and daily backup.',
                'image' => 'https://images.unsplash.com/photo-1609091839311-d5365f9ff1c5?w=800&q=80',
                'featured' => true,
            ],
            [
                'name' => 'JBL Wave Beam Wireless Earbuds',
                'sku' => 'EAR-JBL-WB',
                'brand' => 'JBL',
                'category' => 'Earbuds',
                'color' => 'Black',
                'color_hex' => '#1e293b',
                'cost' => 2800,
                'price' => 3990,
                'stock' => 18,
                'desc' => 'True wireless earbuds with deep JBL bass.',
                'image' => 'https://images.unsplash.com/photo-1572569511254-d8f925fe2cbb?w=800&q=80',
                'best' => true,
                'new' => true,
            ],
            [
                'name' => 'Spigen Clear Phone Case (Universal Fit)',
                'sku' => 'CSE-SPG-CLR',
                'brand' => 'Spigen',
                'category' => 'Accessories',
                'color' => 'Clear',
                'color_hex' => '#e2e8f0',
                'cost' => 280,
                'price' => 590,
                'stock' => 50,
                'desc' => 'Shock-absorbing clear case for popular mid-size phones.',
                'image' => 'https://images.unsplash.com/photo-1601784551446-20c9e07cdbdb?w=800&q=80',
            ],
            [
                'name' => '9H Tempered Glass Screen Protector',
                'sku' => 'GLS-9H-UNI',
                'brand' => 'Baseus',
                'category' => 'Accessories',
                'color' => 'Clear',
                'color_hex' => '#f1f5f9',
                'cost' => 60,
                'price' => 180,
                'stock' => 120,
                'desc' => 'Anti-scratch tempered glass — common service install item.',
                'image' => 'https://images.unsplash.com/photo-1511707171634-5f897ff02aa9?w=800&q=80',
            ],
            [
                'name' => 'iPhone Display Assembly (Service Part)',
                'sku' => 'SVC-IP-DISP',
                'brand' => 'Apple',
                'category' => 'Service Parts',
                'color' => 'Black',
                'color_hex' => '#0f172a',
                'cost' => 4200,
                'price' => 6500,
                'stock' => 12,
                'desc' => 'Replacement display for service jobs — stock tracked, no IMEI.',
                'image' => 'https://images.unsplash.com/photo-1592899677977-9c10ca588bbd?w=800&q=80',
                'featured' => true,
            ],
            [
                'name' => 'Samsung Battery Pack (Service Part)',
                'sku' => 'SVC-SAM-BAT',
                'brand' => 'Samsung',
                'category' => 'Service Parts',
                'color' => 'Black',
                'color_hex' => '#111827',
                'cost' => 900,
                'price' => 1600,
                'stock' => 20,
                'desc' => 'OEM-style replacement battery for common Galaxy models.',
                'image' => 'https://images.unsplash.com/photo-1609091839311-d5365f9ff1c5?w=800&q=80',
            ],
            [
                'name' => 'JBL Go 3 Portable Speaker',
                'sku' => 'SPK-JBL-GO3',
                'brand' => 'JBL',
                'category' => 'Speakers',
                'color' => 'Blue',
                'color_hex' => '#2563eb',
                'cost' => 3200,
                'price' => 4490,
                'original' => 4990,
                'stock' => 15,
                'desc' => 'Waterproof Bluetooth speaker with punchy outdoor sound.',
                'image' => 'https://images.unsplash.com/photo-1608043152269-423dbba4e7e1?w=800&q=80',
                'best' => true,
            ],
            [
                'name' => 'Xiaomi Redmi Watch 4',
                'sku' => 'WCH-XIA-RW4',
                'brand' => 'Xiaomi',
                'category' => 'Smartwatches',
                'color' => 'Black',
                'color_hex' => '#0f172a',
                'cost' => 3800,
                'price' => 5490,
                'stock' => 14,
                'desc' => 'AMOLED smartwatch with fitness tracking and long battery.',
                'image' => 'https://images.unsplash.com/photo-1579586337278-3befd40fd17a?w=800&q=80',
                'new' => true,
                'featured' => true,
            ],
        ];
    }

    private function storeRemoteImage(string $url, string $directory, string $filename): ?string
    {
        $relative = trim($directory, '/').'/'.$filename;

        try {
            if (Storage::disk('public')->exists($relative) && Storage::disk('public')->size($relative) > 1000) {
                return $relative;
            }

            $response = Http::timeout(25)
                ->withHeaders(['User-Agent' => 'MaksGadgetSampleSeeder/1.0'])
                ->get($url);

            if (! $response->successful() || strlen($response->body()) < 500) {
                $this->command?->warn("Image skip: {$filename}");

                return Storage::disk('public')->exists($relative) ? $relative : null;
            }

            Storage::disk('public')->put($relative, $response->body());

            return $relative;
        } catch (\Throwable $e) {
            $this->command?->warn('Image error: '.$e->getMessage());

            return null;
        }
    }
}
