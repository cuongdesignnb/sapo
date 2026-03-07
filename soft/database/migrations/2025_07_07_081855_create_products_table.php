<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->bigIncrements('id');                // ID tự tăng
            $table->string('sku')->unique();            // Mã SKU (duy nhất)
            $table->string('name');                     // Tên sản phẩm
            $table->integer('quantity')->default(0);    // Số lượng tổng (có thể chuyển qua warehouse_products nếu quản lý nhiều kho)
            $table->unsignedBigInteger('unit_id')->nullable();       // Đơn vị (FK đơn vị tính, sẽ bổ sung sau)
            $table->decimal('cost_price', 15, 2)->default(0);        // Giá nhập
            $table->decimal('wholesale_price', 15, 2)->default(0);   // Giá bán buôn
            $table->decimal('retail_price', 15, 2)->default(0);      // Giá bán lẻ
            $table->text('stock_in_warehouses')->nullable();         // Các kho còn hàng (có thể để dạng JSON, sẽ tách bảng khi nâng cao)
            $table->unsignedBigInteger('supplier_id')->nullable();   // Nhà cung cấp (FK, tạo sau)
            $table->text('note')->nullable();                        // Ghi chú
            $table->timestamps(); 
            $table->foreign('unit_id')->references('id')->on('units');
            $table->foreign('supplier_id')->references('id')->on('suppliers');                                   
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
