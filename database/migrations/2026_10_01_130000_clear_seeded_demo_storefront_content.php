<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Clears demo values inserted by WebsiteSeeder / BlogSeeder
 * (site settings, shop contact fallback).
 * Only exact seeded values are cleared, so anything edited in admin is kept.
 */
return new class extends Migration
{
    private const DEMO_SETTINGS = [
        'contact_email' => 'support@maksgadget.com',
        'contact_phone' => '+880 1712-345678',
        'contact_address' => 'Gulshan 1, Dhaka 1212, Bangladesh',
        'contact_hours_weekday' => 'Sat – Thu: 10:00 AM – 8:00 PM (BDT)',
        'contact_hours_weekend' => 'Fri: 3:00 PM – 8:00 PM (BDT)',
        'special_offer_text' => 'Gadget Week Deals',
        'trusted_by_text' => 'Trusted by gadget lovers across Bangladesh',
        'footer_tagline' => 'Authentic phones, laptops, audio & accessories — delivered with care across Bangladesh.',
        'blog_hero_image' => 'cms/blog-hero/hero-gadgets.jpg',
        'blog_feature_1_title' => 'Expert Reviews',
        'blog_feature_1_text' => 'In-depth & honest',
        'blog_feature_2_title' => 'Buying Guides',
        'blog_feature_2_text' => 'Smart picks for you',
        'blog_feature_3_title' => 'Latest Updates',
        'blog_feature_3_text' => 'Tech news, trends & more',
    ];

    private const REPLACEMENTS = [
        'footer_tagline' => 'Your Gadget. Our Priority.',
    ];

    public function up(): void
    {
        if (Schema::hasTable('site_settings')) {
            foreach (self::DEMO_SETTINGS as $column => $demo) {
                if (Schema::hasColumn('site_settings', $column)) {
                    DB::table('site_settings')->where($column, $demo)->update([$column => self::REPLACEMENTS[$column] ?? null]);
                }
            }
        }

        if (Schema::hasTable('shops')) {
            DB::table('shops')->where('email', 'support@maksgadget.com')->update(['email' => null]);
            DB::table('shops')->where('phone', '+880 1712-345678')->update(['phone' => null]);
            DB::table('shops')->where('address', 'Gulshan 1, Dhaka 1212, Bangladesh')->update(['address' => null]);
        }
    }

    public function down(): void
    {
        // Demo content is not restored.
    }
};
