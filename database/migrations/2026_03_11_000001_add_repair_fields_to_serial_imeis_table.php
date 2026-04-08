<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('serial_imeis', function (Blueprint $table) {
            if (!Schema::hasColumn('serial_imeis', 'repair_status')) {
                $table->enum('repair_status', ['not_started', 'repairing', 'ready'])
                    ->nullable()->default(null)
                    ->after('status')
                    ->comment('Trạng thái sửa chữa (chỉ dùng khi module repair bật)');
            }
            if (!Schema::hasColumn('serial_imeis', 'cost_price')) {
                $table->decimal('cost_price', 18, 0)->default(0)
                    ->comment('Giá vốn riêng serial (cộng dồn khi lắp linh kiện)');
            }
        });
    }

    public function down(): void
    {
        $cols = array_filter(['repair_status', 'cost_price'], fn($c) => Schema::hasColumn('serial_imeis', $c));
        if ($cols) {
            Schema::table('serial_imeis', function (Blueprint $table) use ($cols) {
                $table->dropColumn(array_values($cols));
            });
        }
    }
};
