<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('employees')) {
            Schema::table('employees', function (Blueprint $table) {
                if (!Schema::hasColumn('employees', 'attendance_code')) {
                    $table->string('attendance_code')->nullable()->after('code');
                    $table->index(['attendance_code']);
                }

                if (!Schema::hasColumn('employees', 'id_number')) {
                    $table->string('id_number')->nullable()->after('phone');
                    $table->index(['id_number']);
                }

                if (!Schema::hasColumn('employees', 'birth_date')) {
                    $table->date('birth_date')->nullable()->after('email');
                }

                if (!Schema::hasColumn('employees', 'gender')) {
                    $table->string('gender')->nullable()->after('birth_date');
                }

                if (!Schema::hasColumn('employees', 'department')) {
                    $table->string('department')->nullable()->after('gender');
                    $table->index(['department']);
                }

                if (!Schema::hasColumn('employees', 'title')) {
                    $table->string('title')->nullable()->after('department');
                    $table->index(['title']);
                }

                if (!Schema::hasColumn('employees', 'start_work_date')) {
                    $table->date('start_work_date')->nullable()->after('title');
                }

                if (!Schema::hasColumn('employees', 'avatar_path')) {
                    $table->string('avatar_path')->nullable()->after('start_work_date');
                }
            });
        }

        if (!Schema::hasTable('employee_work_warehouses')) {
            Schema::create('employee_work_warehouses', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('employee_id');
                $table->unsignedBigInteger('warehouse_id');
                $table->timestamps();

                $table->unique(['employee_id', 'warehouse_id'], 'emp_work_wh_uq');
                $table->index(['warehouse_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_work_warehouses');

        if (Schema::hasTable('employees')) {
            Schema::table('employees', function (Blueprint $table) {
                foreach (['attendance_code', 'id_number', 'birth_date', 'gender', 'department', 'title', 'start_work_date', 'avatar_path'] as $col) {
                    if (Schema::hasColumn('employees', $col)) {
                        $table->dropColumn($col);
                    }
                }
            });
        }
    }
};
