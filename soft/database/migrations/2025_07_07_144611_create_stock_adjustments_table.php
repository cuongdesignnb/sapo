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
        Schema::create('stock_adjustments', function (Blueprint $table) {
    $table->bigIncrements('id');
    $table->string('code')->unique();
    $table->unsignedBigInteger('warehouse_id');
    $table->unsignedBigInteger('created_by')->nullable();
    $table->unsignedBigInteger('checked_by')->nullable();
    $table->unsignedBigInteger('approved_by')->nullable();
    $table->dateTime('checked_at')->nullable();
    $table->dateTime('approved_at')->nullable();
    $table->string('status')->default('pending');
    $table->text('note')->nullable();
    $table->text('tags')->nullable();
    $table->timestamps();
    
    $table->foreign('warehouse_id')->references('id')->on('warehouses');

});

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stock_adjustments');
    }
};
