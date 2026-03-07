<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_serials', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('warehouse_id')->nullable()->constrained()->nullOnDelete();
            $table->string('serial_number');
            $table->enum('status', ['in_stock', 'sold', 'returned', 'defective', 'transferred'])
                  ->default('in_stock');
            $table->decimal('cost_price', 15, 2)->default(0);
            $table->foreignId('purchase_receipt_item_id')->nullable()
                  ->constrained('purchase_receipt_items')->nullOnDelete();
            $table->foreignId('order_item_id')->nullable()
                  ->constrained('order_items')->nullOnDelete();
            $table->text('note')->nullable();
            $table->timestamps();

            // Unique: same serial within same product
            $table->unique(['product_id', 'serial_number']);
            $table->index(['product_id', 'warehouse_id', 'status']);
            $table->index('serial_number');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_serials');
    }
};
