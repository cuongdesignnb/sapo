<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('timekeeping_records', function (Blueprint $table) {
            if (!Schema::hasColumn('timekeeping_records', 'attendance_type')) {
                $table->string('attendance_type')->default('work')->after('source'); // work|leave_paid|leave_unpaid
                $table->index(['attendance_type']);
            }

            if (!Schema::hasColumn('timekeeping_records', 'manual_override')) {
                $table->boolean('manual_override')->default(false)->after('attendance_type');
                $table->index(['manual_override']);
            }
        });
    }

    public function down(): void
    {
        Schema::table('timekeeping_records', function (Blueprint $table) {
            if (Schema::hasColumn('timekeeping_records', 'manual_override')) {
                $table->dropColumn('manual_override');
            }
            if (Schema::hasColumn('timekeeping_records', 'attendance_type')) {
                $table->dropColumn('attendance_type');
            }
        });
    }
};
