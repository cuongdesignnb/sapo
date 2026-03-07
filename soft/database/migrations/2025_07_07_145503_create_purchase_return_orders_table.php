<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
Schema::create('purchase_return_orders', function (Blueprint $table) {
    $table->bigIncrements('id');
    $table->string('code')->unique();
    $table->unsignedBigInteger('supplier_id');
    $table->unsignedBigInteger('warehouse_id');
    $table->unsignedBigInteger('created_by')->nullable();
    $table->dateTime('returned_at')->nullable();
    $table->string('status')->default('pending');
    $table->decimal('total', 15, 2)->default(0);
    $table->decimal('refunded', 15, 2)->default(0); // NCC đã hoàn tiền chưa
    $table->text('note')->nullable();
    $table->timestamps();

    $table->foreign('supplier_id')->references('id')->on('suppliers');
    $table->foreign('warehouse_id')->references('id')->on('warehouses');
    // $table->foreign('created_by')->references('id')->on('users');
});

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('purchase_return_orders');
    }
};
