<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migration cho Attendance Bridge Agent
 * 
 * Tạo các bảng hỗ trợ sync giữa C# AttendanceBridge app và Web server:
 * - attendance_agent_sync_logs: Lưu trữ lịch sử sync
 * - attendance_bridge_versions: Quản lý phiên bản app để auto-update
 */
return new class extends Migration
{
    public function up(): void
    {
        // Bảng lưu lịch sử sync từ AttendanceBridge agent
        Schema::create('attendance_agent_sync_logs', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('device_id', 100)->comment('ID thiết bị/agent, vd: ronaldjack-1');
            $table->string('app_version', 20)->nullable()->comment('Phiên bản app agent');
            $table->string('sync_type', 50)->comment('Loại sync: users, logs, full');
            $table->dateTime('started_at')->comment('Thời điểm bắt đầu sync');
            $table->dateTime('finished_at')->nullable()->comment('Thời điểm kết thúc sync');
            $table->string('result', 20)->comment('Kết quả: ok, partial, failed');
            $table->json('counts')->nullable()->comment('Số liệu: fetched, created, updated, skipped, failed');
            $table->json('errors')->nullable()->comment('Chi tiết lỗi nếu có');
            $table->timestamps();

            // Indexes
            $table->index('device_id');
            $table->index(['device_id', 'sync_type']);
            $table->index('started_at');
        });

        // Bảng quản lý phiên bản AttendanceBridge app cho auto-update
        Schema::create('attendance_bridge_versions', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('version', 20)->unique()->comment('Số phiên bản, vd: 1.0.3');
            $table->string('channel', 20)->default('stable')->comment('Kênh phát hành: stable, beta');
            $table->boolean('mandatory')->default(false)->comment('Bắt buộc update?');
            $table->string('min_supported', 20)->nullable()->comment('Phiên bản tối thiểu được hỗ trợ');
            $table->dateTime('released_at')->comment('Ngày phát hành');
            $table->text('notes')->nullable()->comment('Ghi chú cập nhật (changelog)');
            $table->string('download_url', 500)->comment('URL tải file cài đặt');
            $table->string('sha256', 64)->comment('SHA256 hash của file để verify');
            $table->unsignedBigInteger('size_bytes')->comment('Kích thước file (bytes)');
            $table->boolean('is_active')->default(true)->comment('Còn hoạt động?');
            $table->timestamps();

            // Indexes
            $table->index(['channel', 'is_active']);
            $table->index('released_at');
        });

        // Thêm cột device_id string vào attendance_devices nếu chưa có
        if (!Schema::hasColumn('attendance_devices', 'device_id')) {
            Schema::table('attendance_devices', function (Blueprint $table) {
                $table->string('device_id', 100)->nullable()->unique()->after('id')
                    ->comment('ID định danh cho agent, vd: ronaldjack-1');
            });
        }
    }

    public function down(): void
    {
        // Drop device_id column nếu đã thêm
        if (Schema::hasColumn('attendance_devices', 'device_id')) {
            Schema::table('attendance_devices', function (Blueprint $table) {
                $table->dropColumn('device_id');
            });
        }

        Schema::dropIfExists('attendance_bridge_versions');
        Schema::dropIfExists('attendance_agent_sync_logs');
    }
};
