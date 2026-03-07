<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('code')->unique();                   // Mã đơn hàng
            $table->unsignedBigInteger('customer_id');          // Khách hàng
            $table->decimal('total', 15, 2)->default(0);        // Tổng tiền đơn hàng
            $table->decimal('paid', 15, 2)->default(0);         // Đã thanh toán
            $table->decimal('debt', 15, 2)->default(0);         // Công nợ còn lại (tự động hoặc thủ công)
            $table->string('status')->default('pending');       // Trạng thái đơn (pending, paid, cancel...)
            $table->unsignedBigInteger('branch_id')->nullable();// Chi nhánh bán (nếu có đa chi nhánh)
            $table->unsignedBigInteger('created_by')->nullable(); // Nhân viên tạo
            $table->dateTime('ordered_at')->nullable();         // Ngày tạo đơn
            $table->text('note')->nullable();                   // Ghi chú
            $table->timestamps();

            $table->foreign('customer_id')->references('id')->on('customers')->onDelete('cascade');        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
