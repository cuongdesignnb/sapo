<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_serial_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_serial_id')->constrained('product_serials')->cascadeOnDelete();
            $table->enum('action', ['imported', 'sold', 'returned', 'transferred', 'adjusted', 'defective']);
            $table->foreignId('from_warehouse_id')->nullable()->constrained('warehouses')->nullOnDelete();
            $table->foreignId('to_warehouse_id')->nullable()->constrained('warehouses')->nullOnDelete();
            $table->string('reference_type')->nullable()->comment('Morph type: PurchaseReceipt, Order, etc.');
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->text('note')->nullable();
            $table->timestamps();

            $table->index(['product_serial_id', 'created_at']);
            $table->index(['reference_type', 'reference_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_serial_histories');
    }
};
