<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('employee_financial_transactions')) {
            Schema::create('employee_financial_transactions', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('employee_id');
                $table->unsignedBigInteger('warehouse_id')->nullable();
                $table->date('occurred_at');
                $table->string('type', 30); // advance | repayment | adjustment
                $table->decimal('amount', 12, 2); // signed: +company owes employee, -employee owes company
                $table->string('reference', 100)->nullable();
                $table->text('notes')->nullable();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->timestamps();

                $table->index(['employee_id', 'occurred_at']);
                $table->index(['warehouse_id', 'occurred_at']);
                $table->index(['type']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_financial_transactions');
    }
};
