<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('product_imeis', 'imei2')) {
            Schema::table('product_imeis', function (Blueprint $table) {
                $table->string('imei2', 32)->nullable()->after('imei');
                $table->unique('imei2');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('product_imeis', 'imei2')) {
            Schema::table('product_imeis', function (Blueprint $table) {
                $table->dropUnique(['imei2']);
                $table->dropColumn('imei2');
            });
        }
    }
};
