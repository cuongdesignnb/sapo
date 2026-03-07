<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('code')->unique();                 // Mã khách hàng (VD: CUZNxxxx)
            $table->string('name');                           // Tên khách hàng
            $table->unsignedBigInteger('group_id')->nullable();   // Nhóm khách hàng (FK customer_groups)
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->date('birthday')->nullable();
            $table->string('gender', 10)->nullable();
            $table->string('tax_code')->nullable();           // Mã số thuế
            $table->string('website')->nullable();
            $table->string('status')->default('active');      // Trạng thái
            $table->decimal('total_spend', 15, 2)->default(0); // Tổng chi tiêu
            $table->integer('total_orders')->default(0);      // Tổng số đơn hàng
            $table->string('customer_type')->nullable();       // Bán lẻ, đại lý, ...
            $table->string('person_in_charge')->nullable();    // Nhân viên phụ trách
            $table->text('tags')->nullable();
            $table->text('note')->nullable();
            $table->timestamps();

            $table->foreign('group_id')->references('id')->on('customer_groups'); 
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customers');
    }
};
