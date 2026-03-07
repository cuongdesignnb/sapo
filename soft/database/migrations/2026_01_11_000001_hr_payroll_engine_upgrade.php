<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ===== Shifts =====
        if (!Schema::hasTable('shifts')) {
            Schema::create('shifts', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('warehouse_id')->nullable();
                $table->string('name');
                $table->time('start_time');
                $table->time('end_time');
                $table->unsignedSmallInteger('allow_late_minutes')->default(0);
                $table->unsignedSmallInteger('allow_early_minutes')->default(0);
                $table->unsignedSmallInteger('rounding_minutes')->default(15);
                $table->boolean('is_overnight')->default(false);
                $table->string('status')->default('active');
                $table->text('notes')->nullable();
                $table->timestamps();

                $table->index(['warehouse_id', 'status']);
            });
        }

        // ===== Enhance employee_work_schedules =====
        if (Schema::hasTable('employee_work_schedules')) {
            Schema::table('employee_work_schedules', function (Blueprint $table) {
                if (!Schema::hasColumn('employee_work_schedules', 'warehouse_id')) {
                    $table->unsignedBigInteger('warehouse_id')->nullable()->after('employee_id');
                    $table->index(['warehouse_id']);
                }

                if (!Schema::hasColumn('employee_work_schedules', 'shift_id')) {
                    $table->unsignedBigInteger('shift_id')->nullable()->after('warehouse_id');
                    $table->index(['shift_id']);
                }

                if (!Schema::hasColumn('employee_work_schedules', 'slot')) {
                    $table->unsignedSmallInteger('slot')->default(1)->after('work_date');
                }
            });

            // Allow multiple schedules per day (slot-based)
            Schema::table('employee_work_schedules', function (Blueprint $table) {
                try {
                    $table->dropUnique(['employee_id', 'work_date']);
                } catch (Throwable $e) {
                    // ignore if constraint name differs / already dropped
                }
            });

            Schema::table('employee_work_schedules', function (Blueprint $table) {
                $table->unique(['employee_id', 'work_date', 'slot'], 'emp_sched_emp_date_slot_uq');
            });
        }

        // ===== Timekeeping Records =====
        if (!Schema::hasTable('timekeeping_records')) {
            Schema::create('timekeeping_records', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('employee_id');
                $table->unsignedBigInteger('employee_work_schedule_id')->nullable();
                $table->unsignedBigInteger('warehouse_id')->nullable();
                $table->unsignedBigInteger('shift_id')->nullable();
                $table->date('work_date');
                $table->unsignedSmallInteger('slot')->default(1);

                $table->dateTime('scheduled_start_at')->nullable();
                $table->dateTime('scheduled_end_at')->nullable();
                $table->dateTime('check_in_at')->nullable();
                $table->dateTime('check_out_at')->nullable();
                $table->string('source')->default('device'); // device|qr|manual

                $table->integer('late_minutes')->default(0);
                $table->integer('early_minutes')->default(0);
                $table->integer('ot_minutes')->default(0);
                $table->integer('worked_minutes')->default(0);
                $table->decimal('work_units', 6, 2)->default(0); // computed later (0/0.5/1...)

                $table->boolean('is_holiday')->default(false);
                $table->decimal('holiday_multiplier', 6, 2)->default(1);

                $table->json('raw')->nullable();
                $table->text('notes')->nullable();
                $table->timestamps();

                $table->index(['employee_id', 'work_date']);
                $table->index(['warehouse_id', 'work_date']);
                $table->unique(['employee_work_schedule_id'], 'timekeeping_schedule_uq');
            });
        }

        // ===== Salary Templates =====
        if (!Schema::hasTable('salary_templates')) {
            Schema::create('salary_templates', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->string('name');
                $table->decimal('base_salary', 15, 2)->default(0);
                $table->decimal('standard_work_units', 6, 2)->default(26); // e.g. 26 days
                $table->decimal('half_day_threshold_hours', 6, 2)->default(4.5);
                $table->decimal('overtime_hourly_rate', 15, 2)->default(0);
                $table->string('status')->default('active');
                $table->text('notes')->nullable();
                $table->timestamps();

                $table->index(['status']);
            });
        }

        if (!Schema::hasTable('salary_template_items')) {
            Schema::create('salary_template_items', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('salary_template_id');
                $table->string('type'); // allowance|deduction|bonus|penalty
                $table->string('name');
                $table->decimal('amount', 15, 2)->default(0);
                $table->string('status')->default('active');
                $table->text('notes')->nullable();
                $table->timestamps();

                $table->index(['salary_template_id', 'type']);
            });
        }

        if (!Schema::hasTable('employee_salary_configs')) {
            Schema::create('employee_salary_configs', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('employee_id');
                $table->unsignedBigInteger('salary_template_id');
                $table->unsignedBigInteger('pay_warehouse_id')->nullable();
                $table->date('effective_from')->nullable();

                $table->decimal('base_salary_override', 15, 2)->nullable();
                $table->decimal('overtime_hourly_rate_override', 15, 2)->nullable();

                $table->decimal('commission_rate', 7, 4)->nullable();
                $table->string('status')->default('active');
                $table->text('notes')->nullable();
                $table->timestamps();

                $table->unique(['employee_id'], 'emp_salary_cfg_employee_uq');
                $table->index(['salary_template_id', 'status']);
                $table->index(['pay_warehouse_id']);
            });
        }

        // ===== Holidays =====
        if (!Schema::hasTable('holidays')) {
            Schema::create('holidays', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->date('holiday_date');
                $table->string('name');
                $table->decimal('multiplier', 6, 2)->default(1);
                $table->boolean('paid_leave')->default(false);
                $table->string('status')->default('active');
                $table->text('notes')->nullable();
                $table->timestamps();

                $table->unique(['holiday_date'], 'holidays_date_uq');
                $table->index(['status']);
            });
        }

        // ===== Payroll Sheets =====
        if (!Schema::hasTable('payroll_sheets')) {
            Schema::create('payroll_sheets', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->date('period_start');
                $table->date('period_end');
                $table->string('status')->default('draft'); // draft|locked|paid
                $table->timestamp('generated_at')->nullable();
                $table->unsignedBigInteger('generated_by')->nullable();
                $table->text('notes')->nullable();
                $table->timestamps();

                $table->unique(['period_start', 'period_end'], 'payroll_sheet_period_uq');
                $table->index(['status', 'period_start']);
            });
        }

        if (!Schema::hasTable('payroll_sheet_items')) {
            Schema::create('payroll_sheet_items', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('payroll_sheet_id');
                $table->unsignedBigInteger('employee_id');
                $table->unsignedBigInteger('warehouse_id')->nullable();

                $table->decimal('base_salary', 15, 2)->default(0);
                $table->decimal('standard_work_units', 6, 2)->default(0);
                $table->decimal('worked_units', 6, 2)->default(0);

                $table->integer('overtime_minutes')->default(0);
                $table->decimal('overtime_pay', 15, 2)->default(0);

                $table->decimal('allowances', 15, 2)->default(0);
                $table->decimal('deductions', 15, 2)->default(0);
                $table->decimal('commissions', 15, 2)->default(0);

                $table->decimal('gross_salary', 15, 2)->default(0);
                $table->decimal('net_salary', 15, 2)->default(0);

                $table->json('breakdown')->nullable();
                $table->timestamps();

                $table->unique(['payroll_sheet_id', 'employee_id'], 'payroll_sheet_item_uq');
                $table->index(['employee_id']);
            });
        }
    }

    public function down(): void
    {
        // Keep existing legacy tables intact; only drop newly introduced ones.
        Schema::dropIfExists('payroll_sheet_items');
        Schema::dropIfExists('payroll_sheets');
        Schema::dropIfExists('holidays');
        Schema::dropIfExists('employee_salary_configs');
        Schema::dropIfExists('salary_template_items');
        Schema::dropIfExists('salary_templates');
        Schema::dropIfExists('timekeeping_records');
        Schema::dropIfExists('shifts');

        // best-effort revert of schedule unique
        if (Schema::hasTable('employee_work_schedules')) {
            Schema::table('employee_work_schedules', function (Blueprint $table) {
                try {
                    $table->dropUnique('emp_sched_emp_date_slot_uq');
                } catch (Throwable $e) {
                    // ignore
                }
            });
        }
    }
};
