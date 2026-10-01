<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

/**
 * Stores the homepage hero side cards ("New Arrivals" / "Best Sellers") as
 * editable CMS → Landing Page rows. Skipped when the storefront shop already
 * has hero side cards.
 */
return new class extends Migration
{
    private const CARDS = [
        [
            'file' => 'new-arrivals.jpg',
            'title' => 'New Arrivals',
            'subtitle' => 'Fresh tech, just landed',
            'badge_text' => 'New',
            'button_text' => 'See new',
            'button_url' => '/shop?filter=new',
            'theme' => 'dark',
        ],
        [
            'file' => 'best-sellers.jpg',
            'title' => 'Best Sellers',
            'subtitle' => 'Most loved gadgets',
            'badge_text' => 'Hot',
            'button_text' => 'Shop',
            'button_url' => '/shop',
            'theme' => 'light',
        ],
    ];

    public function up(): void
    {
        if (! Schema::hasTable('promo_banners') || ! Schema::hasTable('shops')) {
            return;
        }

        $shopId = $this->storefrontShopId();
        if (! $shopId) {
            return;
        }

        $exists = DB::table('promo_banners')
            ->where('shop_id', $shopId)
            ->where('placement', 'hero_side')
            ->exists();
        if ($exists) {
            return;
        }

        $disk = Storage::disk('public');
        $now = now();

        foreach (self::CARDS as $i => $card) {
            $source = database_path('seeders/assets/hero-side/'.$card['file']);
            $path = 'cms/promos/hero-side-'.$card['file'];

            if (is_file($source) && ! $disk->exists($path)) {
                $disk->put($path, file_get_contents($source));
            }

            DB::table('promo_banners')->insert([
                'shop_id' => $shopId,
                'title' => $card['title'],
                'subtitle' => $card['subtitle'],
                'badge_text' => $card['badge_text'],
                'button_text' => $card['button_text'],
                'button_url' => $card['button_url'],
                'theme' => $card['theme'],
                'placement' => 'hero_side',
                'image_path' => $disk->exists($path) ? $path : null,
                'sort_order' => $i,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        DB::table('promo_banners')
            ->where('placement', 'hero_side')
            ->whereIn('image_path', array_map(fn ($c) => 'cms/promos/hero-side-'.$c['file'], self::CARDS))
            ->delete();
    }

    private function storefrontShopId(): ?int
    {
        if (Schema::hasTable('site_settings') && Schema::hasColumn('site_settings', 'default_shop_id')) {
            $default = DB::table('site_settings')->value('default_shop_id');
            if ($default && DB::table('shops')->where('id', $default)->where('is_active', true)->exists()) {
                return (int) $default;
            }
        }

        $id = DB::table('shops')->where('is_active', true)->orderBy('id')->value('id');

        return $id ? (int) $id : null;
    }
};
