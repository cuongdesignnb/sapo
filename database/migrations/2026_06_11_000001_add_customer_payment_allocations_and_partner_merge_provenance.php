<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_payment_allocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cash_flow_id')->constrained('cash_flows')->restrictOnDelete();
            $table->foreignId('customer_id')->constrained('customers')->restrictOnDelete();
            $table->foreignId('invoice_id')->constrained('invoices')->restrictOnDelete();
            $table->decimal('amount', 15, 2);
            $table->timestamps();

            $table->unique(['cash_flow_id', 'invoice_id'], 'uq_customer_payment_allocation');
            $table->index(['customer_id', 'invoice_id'], 'idx_customer_payment_allocation');
        });

        Schema::create('partner_merges', function (Blueprint $table) {
            $table->id();
            $table->string('ref_code')->unique();
            $table->foreignId('source_partner_id')->constrained('customers')->restrictOnDelete();
            $table->foreignId('target_partner_id')->constrained('customers')->restrictOnDelete();
            $table->decimal('source_debt_amount', 15, 2)->default(0);
            $table->decimal('source_supplier_debt_amount', 15, 2)->default(0);
            $table->decimal('target_debt_amount_before', 15, 2)->default(0);
            $table->decimal('target_supplier_debt_amount_before', 15, 2)->default(0);
            $table->foreignId('merged_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('merged_at');
            $table->timestamps();
        });

        Schema::table('customers', function (Blueprint $table) {
            $table->foreignId('merged_into_id')
                ->nullable()
                ->after('status')
                ->constrained('customers')
                ->nullOnDelete();
            $table->dateTime('merged_at')->nullable()->after('merged_into_id');
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropForeign(['merged_into_id']);
            $table->dropColumn(['merged_into_id', 'merged_at']);
        });

        Schema::dropIfExists('partner_merges');
        Schema::dropIfExists('customer_payment_allocations');
    }
};
