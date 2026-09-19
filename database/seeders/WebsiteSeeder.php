<?php

namespace Database\Seeders;

use App\Models\Brand;
use App\Models\HeroSlide;
use App\Models\NavigationLink;
use App\Models\PromoBanner;
use App\Models\Shop;
use App\Models\SiteFeature;
use App\Models\SiteSetting;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

class WebsiteSeeder extends Seeder
{
    public function run(): void
    {
        $shop = Shop::first();
        if (! $shop) {
            return;
        }

        SiteSetting::query()->delete();
        NavigationLink::where('shop_id', $shop->id)->delete();
        HeroSlide::where('shop_id', $shop->id)->delete();
        SiteFeature::where('shop_id', $shop->id)->delete();
        PromoBanner::where('shop_id', $shop->id)->delete();
        // Brands are managed by GadgetCatalogSeeder (logos + full list).

        SiteSetting::create([
            'default_shop_id' => $shop->id,
            'store_name' => 'Maks Gadget',
            'currency_code' => 'BDT',
            'currency_symbol' => '৳',
            'special_offer_text' => 'Special Offer!',
            'trusted_by_text' => 'Trusted by gadget lovers across Bangladesh',
            'deals_kicker' => 'SPECIAL OFFERS',
            'deals_title' => "Deals You'll",
            'deals_title_accent' => 'Love',
            'deals_subtitle' => 'Grab the best deals on phones, laptops, audio, and accessories.',
            'contact_email' => 'support@maksgadget.com',
            'contact_phone' => '+880 1712-345678',
            'contact_address' => 'Gulshan 1, Dhaka 1212, Bangladesh',
            'contact_hours_weekday' => 'Sat - Thu: 10:00 AM - 8:00 PM (BDT)',
            'contact_hours_weekend' => 'Fri: 3:00 PM - 8:00 PM (BDT)',
        ]);

        $this->call(HeroSlideSeeder::class);
        $this->call(SiteFeatureSeeder::class);

        $promos = [
            [
                'title' => 'Wireless Audio Fest',
                'subtitle' => 'Hot deals on headphones & earbuds',
                'badge_text' => 'BEST SELLER',
                'highlight_text' => 'Live deals',
                'discount_badge' => 'DEALS',
                'price_from' => 8900,
                'theme' => 'dark',
                'sort_order' => 1,
                'image' => 'https://images.unsplash.com/photo-1618366712010-f4ae9c647dcb?w=1200&q=80',
                'file' => 'promo-audio.jpg',
            ],
            [
                'title' => 'MacBook Air M3',
                'subtitle' => 'Supercharged for work & play',
                'badge_text' => 'MEGA POWER',
                'highlight_text' => null,
                'discount_badge' => null,
                'price_from' => 119900,
                'theme' => 'light',
                'sort_order' => 2,
                'image' => 'https://images.unsplash.com/photo-1517336714731-489689fd1ca8?w=1200&q=80',
                'file' => 'promo-macbook.jpg',
            ],
        ];

        foreach ($promos as $p) {
            $imagePath = $this->storeRemoteImage($p['image'], 'cms/promos', $p['file']);
            unset($p['image'], $p['file']);

            PromoBanner::create(array_merge($p, [
                'shop_id' => $shop->id,
                'image_path' => $imagePath,
                'button_text' => 'Shop Now',
                'button_url' => '/shop',
                'is_active' => true,
            ]));
        }

        foreach (['Apple', 'Samsung', 'Sony', 'Bose', 'Canon', 'Dell', 'Xiaomi'] as $i => $name) {
            Brand::updateOrCreate(
                ['shop_id' => $shop->id, 'name' => $name],
                ['sort_order' => $i + 1, 'is_active' => true]
            );
        }

        $navLinks = [
            ['label' => 'Home', 'url' => '/', 'location' => 'main_nav', 'sort_order' => 1],
            ['label' => 'Shop', 'url' => '/shop', 'location' => 'main_nav', 'sort_order' => 2],
            ['label' => 'Categories', 'url' => '/shop', 'location' => 'main_nav', 'sort_order' => 3],
            ['label' => 'Deals', 'url' => '/shop?filter=deals', 'location' => 'main_nav', 'sort_order' => 4],
            ['label' => 'New Arrivals', 'url' => '/shop?filter=new', 'location' => 'main_nav', 'sort_order' => 5],
            ['label' => 'Brands', 'url' => '/#brands', 'location' => 'main_nav', 'sort_order' => 6],
            ['label' => 'Blog', 'url' => '/blog', 'location' => 'main_nav', 'sort_order' => 7],
            ['label' => 'Contact', 'url' => '/contact', 'location' => 'main_nav', 'sort_order' => 8],
        ];

        foreach ($navLinks as $link) {
            NavigationLink::create(array_merge($link, ['shop_id' => $shop->id, 'is_active' => true]));
        }
    }

    private function storeRemoteImage(string $url, string $directory, string $filename): ?string
    {
        $relative = trim($directory, '/').'/'.$filename;

        try {
            if (Storage::disk('public')->exists($relative) && Storage::disk('public')->size($relative) > 1000) {
                return $relative;
            }

            $response = Http::timeout(25)
                ->withHeaders(['User-Agent' => 'MaksGadgetWebsiteSeeder/1.0'])
                ->get($url);

            if (! $response->successful() || strlen($response->body()) < 500) {
                return Storage::disk('public')->exists($relative) ? $relative : null;
            }

            Storage::disk('public')->put($relative, $response->body());

            return $relative;
        } catch (\Throwable $e) {
            return null;
        }
    }
}
