<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_technicians', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shop_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->decimal('commission_percent', 5, 2)->default(0);
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['shop_id', 'is_active']);
        });

        Schema::create('service_jobs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shop_id')->constrained()->cascadeOnDelete();
            $table->foreignId('service_technician_id')->constrained('service_technicians')->restrictOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('invoice_no', 64);
            $table->string('customer_name')->nullable();
            $table->string('customer_phone')->nullable();
            $table->text('job_description');
            $table->decimal('materials_cost', 12, 2)->default(0);
            $table->decimal('labor_cost', 12, 2)->default(0);
            $table->decimal('total_amount', 12, 2)->default(0);
            $table->string('payment_method', 32)->default('cash');
            $table->decimal('paid_amount', 12, 2)->default(0);
            $table->decimal('commission_percent', 5, 2)->default(0);
            $table->decimal('commission_amount', 12, 2)->default(0);
            $table->string('status', 20)->default('completed'); // completed|voided
            $table->timestamp('served_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['shop_id', 'invoice_no']);
            $table->index(['shop_id', 'served_at']);
            $table->index(['shop_id', 'service_technician_id']);
            $table->index(['shop_id', 'status']);
        });

        Schema::create('service_commission_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shop_id')->constrained()->cascadeOnDelete();
            $table->foreignId('service_technician_id')->constrained('service_technicians')->restrictOnDelete();
            $table->foreignId('paid_by')->nullable()->constrained('users')->nullOnDelete();
            $table->decimal('amount', 12, 2);
            $table->date('paid_on');
            $table->string('period_month', 7)->nullable(); // YYYY-MM reference
            $table->string('reference')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['shop_id', 'paid_on']);
            $table->index(['shop_id', 'service_technician_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_commission_payments');
        Schema::dropIfExists('service_jobs');
        Schema::dropIfExists('service_technicians');
    }
};
