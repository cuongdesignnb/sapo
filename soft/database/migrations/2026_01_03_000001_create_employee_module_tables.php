<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // If this migration previously failed mid-run (e.g. due to MySQL index name limits),
        // some tables may already exist. Clean them up so we can re-run safely.
        Schema::dropIfExists('employee_commissions');
        Schema::dropIfExists('payrolls');
        Schema::dropIfExists('attendance_logs');
        Schema::dropIfExists('attendance_devices');
        Schema::dropIfExists('employee_work_schedules');
        Schema::dropIfExists('employees');

        Schema::create('employees', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('code')->unique();
            $table->string('name');
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->unsignedBigInteger('warehouse_id')->nullable();
            $table->string('status')->default('active');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['warehouse_id', 'status']);
        });

        Schema::create('employee_work_schedules', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('employee_id');
            $table->date('work_date');
            $table->time('start_time')->nullable();
            $table->time('end_time')->nullable();
            $table->string('shift_name')->nullable();
            $table->string('status')->default('planned');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['employee_id', 'work_date']);
            $table->index(['work_date', 'status']);
        });

        Schema::create('attendance_devices', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('warehouse_id')->nullable();
            $table->string('name');
            $table->string('model')->nullable();
            $table->string('serial_number')->nullable();
            $table->string('ip_address');
            $table->unsignedInteger('tcp_port')->default(4370);
            $table->unsignedInteger('comm_key')->default(0);
            $table->string('status')->default('active');
            $table->text('notes')->nullable();
            $table->timestamp('last_sync_at')->nullable();
            $table->timestamps();

            $table->index(['warehouse_id', 'status']);
            $table->unique(['ip_address', 'tcp_port']);
        });

        Schema::create('attendance_logs', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('attendance_device_id');
            $table->unsignedBigInteger('employee_id')->nullable();
            $table->string('device_user_id');
            $table->dateTime('punched_at');
            $table->string('event_type')->nullable();
            $table->json('raw')->nullable();
            $table->timestamps();

            $table->index(['employee_id', 'punched_at']);
            $table->index(['attendance_device_id', 'punched_at']);
            $table->unique(['attendance_device_id', 'device_user_id', 'punched_at'], 'att_logs_dev_user_punch_uq');
        });

        Schema::create('payrolls', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('employee_id');
            $table->date('period_start');
            $table->date('period_end');
            $table->decimal('base_salary', 15, 2)->default(0);
            $table->decimal('allowances', 15, 2)->default(0);
            $table->decimal('deductions', 15, 2)->default(0);
            $table->decimal('net_salary', 15, 2)->default(0);
            $table->string('status')->default('draft');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['period_start', 'period_end']);
            $table->unique(['employee_id', 'period_start', 'period_end']);
        });

        Schema::create('employee_commissions', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('employee_id');
            $table->unsignedBigInteger('order_id')->nullable();
            $table->string('order_code')->nullable();
            $table->date('earned_at')->nullable();
            $table->decimal('order_total', 15, 2)->default(0);
            $table->decimal('commission_rate', 7, 4)->nullable();
            $table->decimal('commission_amount', 15, 2)->default(0);
            $table->string('status')->default('draft');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['earned_at', 'status']);
            $table->index(['employee_id', 'earned_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_commissions');
        Schema::dropIfExists('payrolls');
        Schema::dropIfExists('attendance_logs');
        Schema::dropIfExists('attendance_devices');
        Schema::dropIfExists('employee_work_schedules');
        Schema::dropIfExists('employees');
    }
};
