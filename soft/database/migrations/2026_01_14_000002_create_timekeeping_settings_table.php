<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('timekeeping_settings')) {
            return;
        }

        Schema::create('timekeeping_settings', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('warehouse_id')->nullable();

            $table->decimal('standard_hours_per_day', 6, 2)->default(8);

            // Grace minutes for late/early; can be overridden by shift allowances
            $table->boolean('use_shift_allowances')->default(true);
            $table->unsignedSmallInteger('late_grace_minutes')->default(0);
            $table->unsignedSmallInteger('early_grace_minutes')->default(0);

            // How to interpret logs when employee has multiple shifts in a day
            $table->boolean('allow_multiple_shifts_one_inout')->default(false);

            // If enabled, only accept logs within each shift's check-in window
            $table->boolean('enforce_shift_checkin_window')->default(false);

            // OT rounding and threshold
            $table->unsignedSmallInteger('ot_rounding_minutes')->default(0);
            $table->unsignedSmallInteger('ot_after_minutes')->default(0);

            $table->string('status')->default('active');
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();

            $table->unique(['warehouse_id'], 'timekeeping_settings_warehouse_uq');
            $table->index(['status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('timekeeping_settings');
    }
};
