<?php

namespace Database\Seeders;

use App\Models\NavigationLink;
use App\Models\Shop;
use App\Models\SiteFeature;
use App\Models\SiteSetting;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Production-safe bootstrap only — no demo products, blogs, reviews, or fake CMS media.
 * Creates roles, the Maks Gadget shop, an admin user, and minimal site settings.
 */
class ProductionSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(RolesAndPermissionsSeeder::class);

        $shop = Shop::firstOrCreate(
            ['email' => env('ADMIN_EMAIL', 'admin@maksgadget.com')],
            [
                'name' => env('SHOP_NAME', 'Maks Gadget'),
                'phone' => env('SHOP_PHONE', '+880 1712-345678'),
                'address' => env('SHOP_ADDRESS', 'Dhaka, Bangladesh'),
                'is_active' => true,
            ]
        );

        $password = env('ADMIN_PASSWORD');
        if (! filled($password)) {
            if (app()->environment('production')) {
                $this->command?->error('Set ADMIN_PASSWORD in .env before seeding production.');

                return;
            }
            $password = '12345678';
        }

        $admin = User::updateOrCreate(
            ['email' => env('ADMIN_EMAIL', 'admin@maksgadget.com')],
            [
                'shop_id' => $shop->id,
                'role' => 'admin',
                'name' => env('ADMIN_NAME', 'Admin'),
                'password' => $password,
                'email_verified_at' => now(),
            ]
        );

        $admin->syncRoles(['Admin']);

        $settings = SiteSetting::query()->first() ?? new SiteSetting;
        $settings->fill([
            'default_shop_id' => $shop->id,
            'store_name' => env('SHOP_NAME', 'Maks Gadget'),
            'currency_code' => 'BDT',
            'currency_symbol' => '৳',
            'special_offer_text' => 'Special Offer!',
            'trusted_by_text' => 'Trusted by gadget lovers across Bangladesh',
            'deals_kicker' => 'SPECIAL OFFERS',
            'deals_title' => "Deals You'll",
            'deals_title_accent' => 'Love',
            'deals_subtitle' => 'Grab the best deals on phones, laptops, audio, and accessories.',
            'contact_email' => env('SHOP_EMAIL', 'support@maksgadget.com'),
            'contact_phone' => env('SHOP_PHONE', '+880 1712-345678'),
            'contact_address' => env('SHOP_ADDRESS', 'Gulshan 1, Dhaka 1212, Bangladesh'),
            'contact_hours_weekday' => 'Sat - Thu: 10:00 AM - 8:00 PM (BDT)',
            'contact_hours_weekend' => 'Fri: 3:00 PM - 8:00 PM (BDT)',
            'delivery_inside_dhaka' => 60,
            'delivery_outside_dhaka' => 120,
            'delivery_free_enabled' => true,
            'delivery_free_min_amount' => 10000,
            'delivery_cod_enabled' => true,
            'delivery_confirmation_enabled' => false,
            'delivery_confirmation_amount' => 0,
        ])->save();

        $this->seedMainNav($shop->id);
        $this->seedFeatures($shop->id);

        $this->command?->info('Production bootstrap ready (shop + admin + settings). No demo catalog.');
    }

    private function seedMainNav(int $shopId): void
    {
        $links = [
            ['label' => 'Home', 'url' => '/', 'sort_order' => 1],
            ['label' => 'Shop', 'url' => '/shop', 'sort_order' => 2],
            ['label' => 'Deals', 'url' => '/shop?filter=deals', 'sort_order' => 3],
            ['label' => 'New Arrivals', 'url' => '/shop?filter=new', 'sort_order' => 4],
            ['label' => 'Blog', 'url' => '/blog', 'sort_order' => 5],
            ['label' => 'Contact', 'url' => '/contact', 'sort_order' => 6],
        ];

        foreach ($links as $link) {
            NavigationLink::updateOrCreate(
                [
                    'shop_id' => $shopId,
                    'location' => 'main_nav',
                    'url' => $link['url'],
                ],
                [
                    'label' => $link['label'],
                    'sort_order' => $link['sort_order'],
                    'is_active' => true,
                ]
            );
        }
    }

    private function seedFeatures(int $shopId): void
    {
        $features = [
            [
                'icon' => 'truck',
                'title' => 'Free Shipping',
                'subtitle' => 'On orders over ৳10,000',
                'sort_order' => 1,
            ],
            [
                'icon' => 'return',
                'title' => '30-Day Returns',
                'subtitle' => 'Hassle-free returns',
                'sort_order' => 2,
            ],
            [
                'icon' => 'lock',
                'title' => 'Cash on Delivery',
                'subtitle' => 'Pay when you receive',
                'sort_order' => 3,
            ],
            [
                'icon' => 'shield',
                'title' => 'Official Warranty',
                'subtitle' => 'On eligible products',
                'sort_order' => 4,
            ],
        ];

        foreach ($features as $feature) {
            SiteFeature::updateOrCreate(
                [
                    'shop_id' => $shopId,
                    'title' => $feature['title'],
                ],
                [
                    'icon' => $feature['icon'],
                    'subtitle' => $feature['subtitle'],
                    'sort_order' => $feature['sort_order'],
                    'is_active' => true,
                ]
            );
        }
    }
}
