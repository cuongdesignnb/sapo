<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Model lưu trữ lịch sử sync từ AttendanceBridge agent
 * 
 * @property int $id
 * @property string $device_id ID thiết bị/agent, vd: ronaldjack-1
 * @property string|null $app_version Phiên bản app agent
 * @property string $sync_type Loại sync: users, logs, full
 * @property \Carbon\Carbon $started_at Thời điểm bắt đầu sync
 * @property \Carbon\Carbon|null $finished_at Thời điểm kết thúc sync
 * @property string $result Kết quả: ok, partial, failed
 * @property array|null $counts Số liệu: fetched, created, updated, skipped, failed
 * @property array|null $errors Chi tiết lỗi nếu có
 */
class AttendanceAgentSyncLog extends Model
{
    use HasFactory;

    protected $table = 'attendance_agent_sync_logs';

    protected $fillable = [
        'device_id',
        'app_version',
        'sync_type',
        'started_at',
        'finished_at',
        'result',
        'counts',
        'errors',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
        'counts' => 'array',
        'errors' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Scope lọc theo device_id
     */
    public function scopeForDevice($query, string $deviceId)
    {
        return $query->where('device_id', $deviceId);
    }

    /**
     * Scope lọc theo sync_type
     */
    public function scopeOfType($query, string $syncType)
    {
        return $query->where('sync_type', $syncType);
    }

    /**
     * Scope lọc các sync thành công
     */
    public function scopeSuccessful($query)
    {
        return $query->where('result', 'ok');
    }

    /**
     * Scope lọc các sync thất bại
     */
    public function scopeFailed($query)
    {
        return $query->where('result', 'failed');
    }

    /**
     * Lấy tổng số records đã fetch
     */
    public function getTotalFetchedAttribute(): int
    {
        return $this->counts['fetched'] ?? 0;
    }

    /**
     * Lấy tổng số records đã tạo mới
     */
    public function getTotalCreatedAttribute(): int
    {
        return $this->counts['created'] ?? 0;
    }

    /**
     * Lấy tổng số records đã update
     */
    public function getTotalUpdatedAttribute(): int
    {
        return $this->counts['updated'] ?? 0;
    }

    /**
     * Lấy tổng số records đã skip
     */
    public function getTotalSkippedAttribute(): int
    {
        return $this->counts['skipped'] ?? 0;
    }

    /**
     * Lấy tổng số records thất bại
     */
    public function getTotalFailedAttribute(): int
    {
        return $this->counts['failed'] ?? 0;
    }

    /**
     * Kiểm tra sync có lỗi không
     */
    public function hasErrors(): bool
    {
        return !empty($this->errors);
    }

    /**
     * Tính thời gian sync (seconds)
     */
    public function getDurationSecondsAttribute(): ?int
    {
        if (!$this->started_at || !$this->finished_at) {
            return null;
        }

        return $this->finished_at->diffInSeconds($this->started_at);
    }
}
