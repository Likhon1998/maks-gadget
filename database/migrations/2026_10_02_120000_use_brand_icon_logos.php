<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Swaps the auto-generated text wordmarks (brands/{slug}.svg) for the square brand icons
 * shipped in database/seeders/assets/brands. Logos uploaded by staff are left untouched.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('brands') || ! Schema::hasColumn('brands', 'logo_path')) {
            return;
        }

        foreach (DB::table('brands')->get(['id', 'name', 'logo_path']) as $brand) {
            $slug = Str::slug((string) $brand->name);
            $source = database_path("seeders/assets/brands/{$slug}.png");
            if ($slug === '' || ! is_file($source)) {
                continue;
            }

            $generated = "brands/{$slug}.svg";
            if (! empty($brand->logo_path) && $brand->logo_path !== $generated) {
                continue;
            }

            $target = "brands/{$slug}.png";
            Storage::disk('public')->put($target, file_get_contents($source));

            DB::table('brands')->where('id', $brand->id)->update([
                'logo_path' => $target,
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        foreach (DB::table('brands')->where('logo_path', 'like', 'brands/%.png')->get(['id', 'name', 'logo_path']) as $brand) {
            $slug = Str::slug((string) $brand->name);
            if ($brand->logo_path === "brands/{$slug}.png") {
                DB::table('brands')->where('id', $brand->id)->update(['logo_path' => "brands/{$slug}.svg"]);
            }
        }
    }
};
