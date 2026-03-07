<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Shift extends Model
{
    use HasFactory;

    protected $fillable = [
        'warehouse_id',
        'name',
        'start_time',
        'end_time',
        'checkin_start_time',
        'checkin_end_time',
        'allow_late_minutes',
        'allow_early_minutes',
        'rounding_minutes',
        'is_overnight',
        'status',
        'notes',
    ];

    protected $appends = [
        'duration_minutes',
        'duration_text',
        'work_time_text',
        'checkin_time_text',
    ];

    protected $casts = [
        'is_overnight' => 'boolean',
        'allow_late_minutes' => 'integer',
        'allow_early_minutes' => 'integer',
        'rounding_minutes' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function getDurationMinutesAttribute(): int
    {
        return self::diffMinutesWithOvernight($this->start_time, $this->end_time);
    }

    public function getDurationTextAttribute(): string
    {
        return self::formatMinutes($this->duration_minutes);
    }

    public function getWorkTimeTextAttribute(): string
    {
        if (!$this->start_time || !$this->end_time) {
            return '';
        }

        return trim((string) $this->start_time) . ' - ' . trim((string) $this->end_time);
    }

    public function getCheckinTimeTextAttribute(): ?string
    {
        if (!$this->checkin_start_time || !$this->checkin_end_time) {
            return null;
        }

        return trim((string) $this->checkin_start_time) . ' - ' . trim((string) $this->checkin_end_time);
    }

    private static function timeToMinutes(?string $time): ?int
    {
        if (!$time) {
            return null;
        }

        $time = trim($time);
        if (!preg_match('/^(\d{1,2}):(\d{2})(?::(\d{2}))?$/', $time, $m)) {
            return null;
        }

        $hours = (int) $m[1];
        $minutes = (int) $m[2];

        if ($hours < 0 || $hours > 23 || $minutes < 0 || $minutes > 59) {
            return null;
        }

        return $hours * 60 + $minutes;
    }

    private static function diffMinutesWithOvernight(?string $start, ?string $end): int
    {
        $startMin = self::timeToMinutes($start);
        $endMin = self::timeToMinutes($end);

        if ($startMin === null || $endMin === null) {
            return 0;
        }

        $diff = $endMin - $startMin;
        if ($diff <= 0) {
            $diff += 24 * 60;
        }

        return $diff;
    }

    private static function formatMinutes(int $minutes): string
    {
        if ($minutes <= 0) {
            return '0 giờ';
        }

        $hours = intdiv($minutes, 60);
        $mins = $minutes % 60;

        if ($mins === 0) {
            return $hours . ' giờ';
        }

        return $hours . ' giờ ' . $mins . ' phút';
    }

    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class);
    }
}
