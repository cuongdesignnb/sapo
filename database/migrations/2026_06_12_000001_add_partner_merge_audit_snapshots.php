<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('partner_merges', function (Blueprint $table) {
            $table->decimal('source_total_spent_before', 15, 2)->nullable();
            $table->decimal('source_total_returns_before', 15, 2)->nullable();
            $table->decimal('source_total_bought_before', 15, 2)->nullable();
            $table->decimal('target_total_spent_before', 15, 2)->nullable();
            $table->decimal('target_total_returns_before', 15, 2)->nullable();
            $table->decimal('target_total_bought_before', 15, 2)->nullable();
            $table->decimal('target_debt_amount_after', 15, 2)->nullable();
            $table->decimal('target_supplier_debt_amount_after', 15, 2)->nullable();
            $table->decimal('target_total_spent_after', 15, 2)->nullable();
            $table->decimal('target_total_returns_after', 15, 2)->nullable();
            $table->decimal('target_total_bought_after', 15, 2)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('partner_merges', function (Blueprint $table) {
            $table->dropColumn([
                'source_total_spent_before',
                'source_total_returns_before',
                'source_total_bought_before',
                'target_total_spent_before',
                'target_total_returns_before',
                'target_total_bought_before',
                'target_debt_amount_after',
                'target_supplier_debt_amount_after',
                'target_total_spent_after',
                'target_total_returns_after',
                'target_total_bought_after',
            ]);
        });
    }
};
