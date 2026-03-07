<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('shifts')) {
            return;
        }

        Schema::table('shifts', function (Blueprint $table) {
            if (!Schema::hasColumn('shifts', 'checkin_start_time')) {
                $table->time('checkin_start_time')->nullable()->after('end_time');
            }
            if (!Schema::hasColumn('shifts', 'checkin_end_time')) {
                $table->time('checkin_end_time')->nullable()->after('checkin_start_time');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('shifts')) {
            return;
        }

        Schema::table('shifts', function (Blueprint $table) {
            if (Schema::hasColumn('shifts', 'checkin_start_time')) {
                $table->dropColumn('checkin_start_time');
            }
            if (Schema::hasColumn('shifts', 'checkin_end_time')) {
                $table->dropColumn('checkin_end_time');
            }
        });
    }
};
