<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchase_orders', function (Blueprint $table) {
            if (!Schema::hasColumn('purchase_orders', 'is_order_only')) {
                $table->boolean('is_order_only')->default(true)->after('note')
                    ->comment('true = đơn đặt (planned) chưa nhập kho & chưa ghi công nợ; false = đơn thực tế');
            }
            if (!Schema::hasColumn('purchase_orders', 'payment_status')) {
                $table->string('payment_status')->default('unpaid')->after('paid')
                    ->comment('unpaid|partial|paid');
            }
        });
    }

    public function down(): void
    {
        Schema::table('purchase_orders', function (Blueprint $table) {
            if (Schema::hasColumn('purchase_orders', 'is_order_only')) {
                $table->dropColumn('is_order_only');
            }
            if (Schema::hasColumn('purchase_orders', 'payment_status')) {
                $table->dropColumn('payment_status');
            }
        });
    }
};
