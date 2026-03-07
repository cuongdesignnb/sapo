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
    Schema::create('warehouse_products', function (Blueprint $table) {
    $table->bigIncrements('id');
    $table->unsignedBigInteger('warehouse_id');
    $table->unsignedBigInteger('product_id');
    $table->integer('quantity')->default(0);
    $table->decimal('cost', 15, 2)->default(0);
    $table->timestamps();

    $table->unique(['warehouse_id', 'product_id']);
    $table->foreign('warehouse_id')->references('id')->on('warehouses')->onDelete('cascade');
    $table->foreign('product_id')->references('id')->on('products')->onDelete('cascade');
});


    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('warehouse_products');
    }
};
