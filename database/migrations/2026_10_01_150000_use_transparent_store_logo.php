<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

/**
 * Replaces the uploaded logo JPG (which has a checkerboard baked into it) with a
 * transparent PNG of the same artwork. Only applies while that exact JPG is the logo.
 */
return new class extends Migration
{
    private const OLD_LOGO = 'cms/logo/iSGWm8EKssWB6WOGr6Ih8QW5ecArJGU8b6D3gIaI.jpg';

    private const NEW_LOGO = 'cms/logo/maks-gadget-logo.png';

    public function up(): void
    {
        if (! Schema::hasTable('site_settings') || ! Schema::hasColumn('site_settings', 'logo_path')) {
            return;
        }

        if (! DB::table('site_settings')->where('logo_path', self::OLD_LOGO)->exists()) {
            return;
        }

        $source = database_path('seeders/assets/logo/maks-gadget-logo.png');
        if (! is_file($source)) {
            return;
        }

        Storage::disk('public')->put(self::NEW_LOGO, file_get_contents($source));

        DB::table('site_settings')->where('logo_path', self::OLD_LOGO)->update([
            'logo_path' => self::NEW_LOGO,
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        if (Schema::hasTable('site_settings') && Storage::disk('public')->exists(self::OLD_LOGO)) {
            DB::table('site_settings')->where('logo_path', self::NEW_LOGO)->update(['logo_path' => self::OLD_LOGO]);
        }
    }
};
