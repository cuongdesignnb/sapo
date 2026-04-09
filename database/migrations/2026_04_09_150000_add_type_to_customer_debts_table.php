<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customer_debts', function (Blueprint $table) {
            // Thêm trường type để phân biệt loại giao dịch (giống SupplierDebt)
            $table->string('type', 20)->default('sale')->after('debt_total');
            // Liên kết đơn trả hàng
            $table->unsignedBigInteger('order_return_id')->nullable()->after('order_id');
            
            $table->foreign('order_return_id')->references('id')->on('order_returns')->onDelete('set null');
            $table->index(['customer_id', 'type']);
            $table->index(['customer_id', 'recorded_at']);
        });

        // Backfill type cho data cũ
        DB::statement("UPDATE customer_debts SET type = CASE
            WHEN amount > 0 THEN 'sale'
            WHEN amount < 0 THEN 'payment'
            ELSE 'adjustment'
        END WHERE type = 'sale'");
    }

    public function down(): void
    {
        Schema::table('customer_debts', function (Blueprint $table) {
            $table->dropForeign(['order_return_id']);
            $table->dropIndex(['customer_id', 'type']);
            $table->dropIndex(['customer_id', 'recorded_at']);
            $table->dropColumn(['type', 'order_return_id']);
        });
    }
};
