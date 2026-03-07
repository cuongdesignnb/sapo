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
Schema::create('stock_adjustment_items', function (Blueprint $table) {
    $table->bigIncrements('id');
    $table->unsignedBigInteger('stock_adjustment_id');
    $table->unsignedBigInteger('product_id');
    $table->integer('quantity_expected')->default(0);
    $table->integer('quantity_real')->default(0);
    $table->string('reason')->nullable();
    $table->text('note')->nullable();
    $table->timestamps();

    $table->foreign('stock_adjustment_id')->references('id')->on('stock_adjustments')->onDelete('cascade');
    $table->foreign('product_id')->references('id')->on('products');
});

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stock_adjustment_items');
    }
};
