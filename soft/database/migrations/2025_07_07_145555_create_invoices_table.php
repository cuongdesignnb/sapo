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
 Schema::create('invoices', function (Blueprint $table) {
    $table->bigIncrements('id');
    $table->string('code')->unique();                  // Số hóa đơn (INVxxxx)
    $table->string('type')->default('sale');           // sale/purchase/return/other
    $table->unsignedBigInteger('order_id')->nullable();     // Liên kết đơn bán (nếu có)
    $table->unsignedBigInteger('purchase_order_id')->nullable(); // Liên kết phiếu nhập (nếu có)
    $table->unsignedBigInteger('customer_id')->nullable();  // Người mua (nếu là bán hàng)
    $table->unsignedBigInteger('supplier_id')->nullable();  // NCC (nếu là hóa đơn mua vào)
    $table->date('invoice_date')->nullable();          // Ngày hóa đơn
    $table->decimal('total', 15, 2)->default(0);       // Tổng tiền hóa đơn
    $table->decimal('vat', 15, 2)->default(0);         // Tiền thuế VAT
    $table->decimal('amount', 15, 2)->default(0);      // Số tiền phải trả
    $table->decimal('paid', 15, 2)->default(0);        // Đã thanh toán
    $table->string('status')->default('unpaid');       // unpaid/paid/cancelled/partially
    $table->text('note')->nullable();
    $table->timestamps();

    $table->foreign('order_id')->references('id')->on('orders')->onDelete('set null');
    $table->foreign('purchase_order_id')->references('id')->on('purchase_orders')->onDelete('set null');
    $table->foreign('customer_id')->references('id')->on('customers')->onDelete('set null');
    $table->foreign('supplier_id')->references('id')->on('suppliers')->onDelete('set null');
});


    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};
