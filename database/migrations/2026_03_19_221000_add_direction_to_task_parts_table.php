<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('task_parts') || Schema::hasColumn('task_parts', 'direction')) {
            return;
        }
        Schema::table('task_parts', function (Blueprint $table) {
            $table->string('direction', 10)->default('export')->after('notes')
                  ->comment('export = xuất kho lắp vào máy, import = bóc từ máy nhập kho');
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('task_parts')) {
            return;
        }
        Schema::table('task_parts', function (Blueprint $table) {
            $table->dropColumn('direction');
        });
    }
};
