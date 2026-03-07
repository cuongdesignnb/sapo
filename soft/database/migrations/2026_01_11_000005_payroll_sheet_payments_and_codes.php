<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('payroll_sheets')) {
            Schema::table('payroll_sheets', function (Blueprint $table) {
                if (!Schema::hasColumn('payroll_sheets', 'code')) {
                    $table->string('code')->nullable()->unique();
                }
                if (!Schema::hasColumn('payroll_sheets', 'name')) {
                    $table->string('name')->nullable();
                }
                if (!Schema::hasColumn('payroll_sheets', 'pay_cycle')) {
                    $table->string('pay_cycle')->default('monthly'); // monthly|weekly|custom
                }
            });
        }

        if (Schema::hasTable('payroll_sheet_items')) {
            Schema::table('payroll_sheet_items', function (Blueprint $table) {
                if (!Schema::hasColumn('payroll_sheet_items', 'code')) {
                    $table->string('code')->nullable()->unique();
                }
                if (!Schema::hasColumn('payroll_sheet_items', 'paid_amount')) {
                    $table->decimal('paid_amount', 15, 2)->default(0);
                }
            });
        }

        if (!Schema::hasTable('payroll_sheet_payments')) {
            Schema::create('payroll_sheet_payments', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->string('code')->nullable()->unique();

                $table->unsignedBigInteger('payroll_sheet_id');
                $table->unsignedBigInteger('payroll_sheet_item_id')->nullable();
                $table->unsignedBigInteger('employee_id');

                $table->decimal('amount', 15, 2);
                $table->string('payment_method')->default('cash'); // cash|bank|other
                $table->string('status')->default('paid');
                $table->timestamp('paid_at')->nullable();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->text('notes')->nullable();
                $table->timestamps();

                $table->index(['payroll_sheet_id', 'employee_id'], 'payroll_sheet_payments_sheet_emp_idx');
                $table->index(['paid_at'], 'payroll_sheet_payments_paid_at_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('payroll_sheet_payments');

        if (Schema::hasTable('payroll_sheet_items')) {
            Schema::table('payroll_sheet_items', function (Blueprint $table) {
                if (Schema::hasColumn('payroll_sheet_items', 'paid_amount')) {
                    $table->dropColumn('paid_amount');
                }
                if (Schema::hasColumn('payroll_sheet_items', 'code')) {
                    $table->dropColumn('code');
                }
            });
        }

        if (Schema::hasTable('payroll_sheets')) {
            Schema::table('payroll_sheets', function (Blueprint $table) {
                if (Schema::hasColumn('payroll_sheets', 'pay_cycle')) {
                    $table->dropColumn('pay_cycle');
                }
                if (Schema::hasColumn('payroll_sheets', 'name')) {
                    $table->dropColumn('name');
                }
                if (Schema::hasColumn('payroll_sheets', 'code')) {
                    $table->dropColumn('code');
                }
            });
        }
    }
};
