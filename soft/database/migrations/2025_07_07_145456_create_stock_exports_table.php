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
Schema::create('stock_exports', function (Blueprint $table) {
    $table->bigIncrements('id');
    $table->string('code')->unique();
    $table->string('type')->default('sale'); // sale/return/transfer/usage/dispose
    $table->unsignedBigInteger('warehouse_id');
    $table->unsignedBigInteger('order_id')->nullable(); // Nếu xuất theo đơn bán
    $table->unsignedBigInteger('created_by')->nullable();
    $table->dateTime('exported_at')->nullable();
    $table->string('status')->default('pending');
    $table->text('note')->nullable();
    $table->timestamps();

    $table->foreign('warehouse_id')->references('id')->on('warehouses');
    $table->foreign('order_id')->references('id')->on('orders')->onDelete('set null');
    // $table->foreign('created_by')->references('id')->on('users');
});

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stock_exports');
    }
};
