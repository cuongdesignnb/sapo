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
Schema::create('invoice_items', function (Blueprint $table) {
    $table->bigIncrements('id');
    $table->unsignedBigInteger('invoice_id');
    $table->unsignedBigInteger('product_id')->nullable();
    $table->string('service_name')->nullable();
    $table->string('unit')->nullable();
    $table->integer('quantity')->default(1);
    $table->decimal('price', 15, 2)->default(0);
    $table->decimal('vat', 15, 2)->default(0);
    $table->decimal('total', 15, 2)->default(0);
    $table->text('note')->nullable();
    $table->timestamps();

    $table->foreign('invoice_id')->references('id')->on('invoices')->onDelete('cascade');
    $table->foreign('product_id')->references('id')->on('products');
});


    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('invoice_items');
    }
};
