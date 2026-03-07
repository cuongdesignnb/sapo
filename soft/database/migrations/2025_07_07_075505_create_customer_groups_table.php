<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_groups', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('code')->unique();            // Mã nhóm (BANLE, DL, etc)
            $table->string('name');                      // Tên nhóm (Bán lẻ, Đại lý...)
            $table->string('type')->nullable();          // Loại nhóm (cố định, động,...)
            $table->text('description')->nullable();     // Mô tả
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_groups');
    }
};
