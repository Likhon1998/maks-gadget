<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('service_jobs', function (Blueprint $table) {
            $table->decimal('cash_amount', 12, 2)->default(0)->after('payment_method');
            $table->decimal('card_amount', 12, 2)->default(0)->after('cash_amount');
            $table->decimal('mobile_amount', 12, 2)->default(0)->after('card_amount');
            $table->decimal('credit_amount', 12, 2)->default(0)->after('paid_amount');
            $table->decimal('commission_paid_amount', 12, 2)->default(0)->after('commission_amount');
        });

        Schema::table('service_commission_payments', function (Blueprint $table) {
            $table->foreignId('service_job_id')
                ->nullable()
                ->after('service_technician_id')
                ->constrained('service_jobs')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('service_commission_payments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('service_job_id');
        });

        Schema::table('service_jobs', function (Blueprint $table) {
            $table->dropColumn([
                'cash_amount',
                'card_amount',
                'mobile_amount',
                'credit_amount',
                'commission_paid_amount',
            ]);
        });
    }
};
