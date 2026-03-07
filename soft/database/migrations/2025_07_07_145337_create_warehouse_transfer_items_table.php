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
Schema::create('warehouse_transfer_items', function (Blueprint $table) {
    $table->bigIncrements('id');
    $table->unsignedBigInteger('warehouse_transfer_id');
    $table->unsignedBigInteger('product_id');
    $table->unsignedBigInteger('unit_id')->nullable();
    $table->integer('quantity')->default(0);
    $table->decimal('cost', 15, 2)->default(0);
    $table->text('note')->nullable();
    $table->timestamps();

    $table->foreign('warehouse_transfer_id')->references('id')->on('warehouse_transfers')->onDelete('cascade');
    $table->foreign('product_id')->references('id')->on('products');
    // $table->foreign('unit_id')->references('id')->on('units');
});


    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('warehouse_transfer_items');
    }
};
