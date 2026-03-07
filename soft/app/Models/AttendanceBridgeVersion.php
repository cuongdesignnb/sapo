<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Model quản lý phiên bản AttendanceBridge app cho auto-update
 * 
 * @property int $id
 * @property string $version Số phiên bản, vd: 1.0.3
 * @property string $channel Kênh phát hành: stable, beta
 * @property bool $mandatory Bắt buộc update?
 * @property string|null $min_supported Phiên bản tối thiểu được hỗ trợ
 * @property \Carbon\Carbon $released_at Ngày phát hành
 * @property string|null $notes Ghi chú cập nhật (changelog)
 * @property string $download_url URL tải file cài đặt
 * @property string $sha256 SHA256 hash của file để verify
 * @property int $size_bytes Kích thước file (bytes)
 * @property bool $is_active Còn hoạt động?
 */
class AttendanceBridgeVersion extends Model
{
    use HasFactory;

    protected $table = 'attendance_bridge_versions';

    protected $fillable = [
        'version',
        'channel',
        'mandatory',
        'min_supported',
        'released_at',
        'notes',
        'download_url',
        'sha256',
        'size_bytes',
        'is_active',
    ];

    protected $casts = [
        'mandatory' => 'boolean',
        'released_at' => 'datetime',
        'size_bytes' => 'integer',
        'is_active' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Scope lọc theo channel
     */
    public function scopeForChannel($query, string $channel = 'stable')
    {
        return $query->where('channel', $channel);
    }

    /**
     * Scope chỉ lấy các phiên bản active
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Lấy phiên bản mới nhất cho một channel
     */
    public static function getLatest(string $channel = 'stable'): ?self
    {
        return static::query()
            ->forChannel($channel)
            ->active()
            ->orderByDesc('released_at')
            ->first();
    }

    /**
     * So sánh version (semver)
     * @return int -1 nếu nhỏ hơn, 0 nếu bằng, 1 nếu lớn hơn
     */
    public static function compareVersions(string $v1, string $v2): int
    {
        return version_compare($v1, $v2);
    }

    /**
     * Kiểm tra phiên bản hiện tại có cần update không
     */
    public function needsUpdate(string $currentVersion): bool
    {
        return static::compareVersions($this->version, $currentVersion) > 0;
    }

    /**
     * Kiểm tra phiên bản hiện tại có còn được hỗ trợ không
     */
    public function isSupported(string $currentVersion): bool
    {
        if (empty($this->min_supported)) {
            return true;
        }

        return static::compareVersions($currentVersion, $this->min_supported) >= 0;
    }

    /**
     * Format kích thước file cho hiển thị
     */
    public function getFormattedSizeAttribute(): string
    {
        $bytes = $this->size_bytes;
        
        if ($bytes >= 1073741824) {
            return number_format($bytes / 1073741824, 2) . ' GB';
        }
        
        if ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 2) . ' MB';
        }
        
        if ($bytes >= 1024) {
            return number_format($bytes / 1024, 2) . ' KB';
        }
        
        return $bytes . ' bytes';
    }

    /**
     * Lấy thông tin download dưới dạng array
     */
    public function getDownloadInfoAttribute(): array
    {
        return [
            'url' => $this->download_url,
            'sha256' => $this->sha256,
            'size_bytes' => $this->size_bytes,
        ];
    }
}
