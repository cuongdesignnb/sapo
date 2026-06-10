<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            if (!Schema::hasColumn('order_items', 'fulfilled_quantity')) {
                $table->unsignedInteger('fulfilled_quantity')->default(0)->after('qty');
            }
        });

        Schema::table('invoice_items', function (Blueprint $table) {
            if (!Schema::hasColumn('invoice_items', 'order_item_id')) {
                $table->unsignedBigInteger('order_item_id')->nullable()->after('product_id');
                $table->index('order_item_id', 'invoice_items_order_item_id_idx');
            }
        });

        Schema::table('invoices', function (Blueprint $table) {
            if (!Schema::hasColumn('invoices', 'order_deposit_applied_amount')) {
                $table->decimal('order_deposit_applied_amount', 15, 2)->default(0)->after('customer_paid');
            }
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            if (Schema::hasColumn('invoices', 'order_deposit_applied_amount')) {
                $table->dropColumn('order_deposit_applied_amount');
            }
        });

        Schema::table('invoice_items', function (Blueprint $table) {
            if (Schema::hasColumn('invoice_items', 'order_item_id')) {
                $table->dropIndex('invoice_items_order_item_id_idx');
                $table->dropColumn('order_item_id');
            }
        });

        Schema::table('order_items', function (Blueprint $table) {
            if (Schema::hasColumn('order_items', 'fulfilled_quantity')) {
                $table->dropColumn('fulfilled_quantity');
            }
        });
    }
};
