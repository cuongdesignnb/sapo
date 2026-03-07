<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class UserSession extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'user_id',
        'session_token',
        'ip_address',
        'user_agent',
        'warehouse_id',
        'login_at',
        'last_activity',
        'logout_at',
        'is_active'
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'login_at' => 'datetime',
        'last_activity' => 'datetime',
        'logout_at' => 'datetime',
        'is_active' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];

    /**
     * Relationship with User
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Relationship with Warehouse (current active warehouse)
     */
    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class);
    }

    /**
     * Mark session as logged out
     */
    public function logout(): void
    {
        $this->update([
            'logout_at' => now(),
            'is_active' => false
        ]);
    }

    /**
     * Update last activity timestamp
     */
    public function updateActivity(): void
    {
        $this->update([
            'last_activity' => now()
        ]);
    }

    /**
     * Switch to different warehouse
     */
    public function switchWarehouse(int $warehouseId): void
    {
        $this->update([
            'warehouse_id' => $warehouseId,
            'last_activity' => now()
        ]);
    }

    /**
     * Check if session is expired
     */
    public function isExpired(int $timeoutMinutes = 120): bool
    {
        if (!$this->is_active) {
            return true;
        }

        return $this->last_activity->diffInMinutes(now()) > $timeoutMinutes;
    }

    /**
     * Get session duration in minutes
     */
    public function getDurationMinutes(): int
    {
        $endTime = $this->logout_at ?? $this->last_activity ?? now();
        return $this->login_at->diffInMinutes($endTime);
    }

    /**
     * Get formatted session duration
     */
    public function getFormattedDuration(): string
    {
        $minutes = $this->getDurationMinutes();
        
        if ($minutes < 60) {
            return "{$minutes} phút";
        }
        
        $hours = floor($minutes / 60);
        $remainingMinutes = $minutes % 60;
        
        if ($remainingMinutes === 0) {
            return "{$hours} giờ";
        }
        
        return "{$hours} giờ {$remainingMinutes} phút";
    }

    /**
     * Get browser name from user agent
     */
    public function getBrowserName(): string
    {
        $userAgent = $this->user_agent ?? '';
        
        if (str_contains($userAgent, 'Chrome')) {
            return 'Chrome';
        } elseif (str_contains($userAgent, 'Firefox')) {
            return 'Firefox';
        } elseif (str_contains($userAgent, 'Safari') && !str_contains($userAgent, 'Chrome')) {
            return 'Safari';
        } elseif (str_contains($userAgent, 'Edge')) {
            return 'Edge';
        } elseif (str_contains($userAgent, 'Opera')) {
            return 'Opera';
        }
        
        return 'Unknown';
    }

    /**
     * Get operating system from user agent
     */
    public function getOperatingSystem(): string
    {
        $userAgent = $this->user_agent ?? '';
        
        if (str_contains($userAgent, 'Windows NT')) {
            return 'Windows';
        } elseif (str_contains($userAgent, 'Mac OS X')) {
            return 'macOS';
        } elseif (str_contains($userAgent, 'Linux')) {
            return 'Linux';
        } elseif (str_contains($userAgent, 'Android')) {
            return 'Android';
        } elseif (str_contains($userAgent, 'iPhone') || str_contains($userAgent, 'iPad')) {
            return 'iOS';
        }
        
        return 'Unknown';
    }

    /**
     * Scope for active sessions only
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope for sessions of specific user
     */
    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Scope for sessions in specific warehouse
     */
    public function scopeInWarehouse($query, int $warehouseId)
    {
        return $query->where('warehouse_id', $warehouseId);
    }

    /**
     * Scope for expired sessions
     */
    public function scopeExpired($query, int $timeoutMinutes = 120)
    {
        return $query->where('is_active', true)
                    ->where('last_activity', '<', now()->subMinutes($timeoutMinutes));
    }

    /**
     * Scope for sessions within date range
     */
    public function scopeWithinDateRange($query, Carbon $startDate, Carbon $endDate)
    {
        return $query->whereBetween('login_at', [$startDate, $endDate]);
    }

    /**
     * Clean up expired sessions
     */
    public static function cleanupExpiredSessions(int $timeoutMinutes = 120): int
    {
        return static::expired($timeoutMinutes)->update([
            'logout_at' => now(),
            'is_active' => false
        ]);
    }

    /**
     * Get session statistics for user
     */
    public static function getStatsForUser(int $userId, int $days = 30): array
    {
        $startDate = now()->subDays($days)->startOfDay();
        $endDate = now()->endOfDay();

        $sessions = static::forUser($userId)
            ->withinDateRange($startDate, $endDate)
            ->get();

        $totalSessions = $sessions->count();
        $totalDuration = $sessions->sum(function ($session) {
            return $session->getDurationMinutes();
        });

        $avgDuration = $totalSessions > 0 ? round($totalDuration / $totalSessions) : 0;

        return [
            'total_sessions' => $totalSessions,
            'total_duration_minutes' => $totalDuration,
            'avg_duration_minutes' => $avgDuration,
            'total_duration_formatted' => static::formatMinutes($totalDuration),
            'avg_duration_formatted' => static::formatMinutes($avgDuration)
        ];
    }

    /**
     * Format minutes to human readable string
     */
    private static function formatMinutes(int $minutes): string
    {
        if ($minutes < 60) {
            return "{$minutes} phút";
        }
        
        $hours = floor($minutes / 60);
        $remainingMinutes = $minutes % 60;
        
        if ($remainingMinutes === 0) {
            return "{$hours} giờ";
        }
        
        return "{$hours} giờ {$remainingMinutes} phút";
    }

    /**
     * Create new session record
     */
    public static function createSession(
        int $userId, 
        string $sessionToken, 
        string $ipAddress, 
        string $userAgent, 
        ?int $warehouseId = null
    ): self {
        return static::create([
            'user_id' => $userId,
            'session_token' => $sessionToken,
            'ip_address' => $ipAddress,
            'user_agent' => $userAgent,
            'warehouse_id' => $warehouseId,
            'login_at' => now(),
            'last_activity' => now(),
            'is_active' => true
        ]);
    }

    /**
     * Find session by token
     */
    public static function findByToken(string $token): ?self
    {
        return static::where('session_token', $token)
                    ->where('is_active', true)
                    ->first();
    }
}