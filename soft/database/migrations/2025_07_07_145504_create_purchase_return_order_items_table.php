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
Schema::create('purchase_return_order_items', function (Blueprint $table) {
    $table->bigIncrements('id');
    $table->unsignedBigInteger('purchase_return_order_id');
    $table->unsignedBigInteger('product_id');
    $table->unsignedBigInteger('unit_id')->nullable();
    $table->integer('quantity')->default(0);
    $table->decimal('price', 15, 2)->default(0);
    $table->decimal('total', 15, 2)->default(0);
    $table->text('note')->nullable();
    $table->timestamps();

    $table->foreign('purchase_return_order_id')->references('id')->on('purchase_return_orders')->onDelete('cascade');
    $table->foreign('product_id')->references('id')->on('products');
    // $table->foreign('unit_id')->references('id')->on('units');
});


    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('purchase_return_order_items');
    }
};
