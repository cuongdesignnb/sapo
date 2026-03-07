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
Schema::create('warehouse_transfers', function (Blueprint $table) {
    $table->bigIncrements('id');
    $table->string('code')->unique();
    $table->unsignedBigInteger('warehouse_from');
    $table->unsignedBigInteger('warehouse_to');
    $table->unsignedBigInteger('created_by')->nullable();
    $table->unsignedBigInteger('approved_by')->nullable();
    $table->dateTime('transfered_at')->nullable();
    $table->string('status')->default('pending');
    $table->text('note')->nullable();
    $table->timestamps();

    $table->foreign('warehouse_from')->references('id')->on('warehouses');
    $table->foreign('warehouse_to')->references('id')->on('warehouses');
    // $table->foreign('created_by')->references('id')->on('users');
    // $table->foreign('approved_by')->references('id')->on('users');
});


    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('warehouse_transfers');
    }
};
