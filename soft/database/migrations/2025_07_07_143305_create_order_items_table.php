<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_items', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('order_id');       // Đơn hàng
            $table->unsignedBigInteger('product_id');     // Sản phẩm
            $table->integer('quantity')->default(1);      // Số lượng mua
            $table->decimal('price', 15, 2)->default(0);  // Đơn giá tại thời điểm mua
            $table->decimal('total', 15, 2)->default(0);  // Thành tiền dòng này
            $table->text('note')->nullable();             // Ghi chú riêng từng dòng
            $table->timestamps();

            $table->foreign('order_id')->references('id')->on('orders')->onDelete('cascade');
            $table->foreign('product_id')->references('id')->on('products');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_items');
    }
};
