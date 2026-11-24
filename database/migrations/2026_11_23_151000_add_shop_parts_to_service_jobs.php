<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('service_jobs', function (Blueprint $table) {
            $table->decimal('shop_parts_amount', 12, 2)->default(0)->after('job_description');
            $table->decimal('shop_parts_cost', 12, 2)->default(0)->after('shop_parts_amount');
        });

        Schema::create('service_job_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_job_id')->constrained('service_jobs')->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained('products')->nullOnDelete();
            $table->string('product_name');
            $table->unsignedInteger('quantity')->default(1);
            $table->decimal('unit_price', 12, 2)->default(0);
            $table->decimal('unit_cost', 12, 2)->default(0);
            $table->decimal('subtotal', 12, 2)->default(0);
            $table->decimal('line_cost', 12, 2)->default(0);
            $table->timestamps();

            $table->index(['service_job_id']);
            $table->index(['product_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_job_items');

        Schema::table('service_jobs', function (Blueprint $table) {
            $table->dropColumn(['shop_parts_amount', 'shop_parts_cost']);
        });
    }
};
