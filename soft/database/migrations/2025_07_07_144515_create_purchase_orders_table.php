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
        Schema::create('purchase_orders', function (Blueprint $table) {
    $table->bigIncrements('id');
    $table->string('code')->unique();
    $table->unsignedBigInteger('supplier_id');
    $table->unsignedBigInteger('warehouse_id');
    $table->unsignedBigInteger('created_by')->nullable();
    $table->date('expected_at')->nullable();
    $table->dateTime('imported_at')->nullable();
    $table->string('status')->default('draft');
    $table->decimal('discount', 15, 2)->default(0);
    $table->decimal('additional_fee', 15, 2)->default(0);
    $table->decimal('tax', 15, 2)->default(0);
    $table->decimal('total', 15, 2)->default(0);
    $table->decimal('need_pay', 15, 2)->default(0);
    $table->decimal('paid', 15, 2)->default(0);
    $table->text('note')->nullable();
    $table->text('tags')->nullable();
    $table->timestamps();
    
    $table->foreign('supplier_id')->references('id')->on('suppliers');
    $table->foreign('warehouse_id')->references('id')->on('warehouses');
});

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('purchase_orders');
    }
};
