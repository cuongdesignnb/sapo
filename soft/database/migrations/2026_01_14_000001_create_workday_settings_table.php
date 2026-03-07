<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('workday_settings')) {
            return;
        }

        Schema::create('workday_settings', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('warehouse_id')->nullable();
            $table->json('week_days'); // { mon:true, tue:true, ... }
            $table->string('status')->default('active');
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();

            $table->unique(['warehouse_id'], 'workday_settings_warehouse_uq');
            $table->index(['status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workday_settings');
    }
};
