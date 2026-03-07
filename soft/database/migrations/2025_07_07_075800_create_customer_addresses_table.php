<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_addresses', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('customer_id');     // FK khách hàng
            $table->string('phone')->nullable();
            $table->string('address')->nullable();
            $table->string('ward')->nullable();
            $table->string('district')->nullable();
            $table->string('province')->nullable();
            $table->string('zipcode')->nullable();
            $table->string('email')->nullable();
            $table->string('type')->nullable();           // Loại địa chỉ (nhà riêng, công ty, mặc định...)
            $table->timestamps();

            $table->foreign('customer_id')->references('id')->on('customers')->onDelete('cascade'); // Bổ sung sau khi migrate xong hết
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_addresses');
    }
};
