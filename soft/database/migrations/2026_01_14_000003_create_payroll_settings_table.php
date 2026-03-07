<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('payroll_settings')) {
            return;
        }

        Schema::create('payroll_settings', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('warehouse_id')->nullable();

            $table->string('pay_cycle', 20)->default('monthly'); // monthly|weekly|biweekly

            // For monthly cycle: define the day range.
            // Example Kiot-like: start_day=26, end_day=25, start_in_prev_month=true
            $table->unsignedTinyInteger('start_day')->default(26);
            $table->unsignedTinyInteger('end_day')->default(25);
            $table->boolean('start_in_prev_month')->default(true);

            // Suggested pay day of month (for reminders / UI)
            $table->unsignedTinyInteger('pay_day')->default(5);

            $table->boolean('default_recalculate_timekeeping')->default(true);
            $table->boolean('auto_generate_enabled')->default(false);

            $table->string('status')->default('active');
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();

            $table->unique(['warehouse_id'], 'payroll_settings_warehouse_uq');
            $table->index(['status']);
            $table->index(['pay_cycle']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payroll_settings');
    }
};
