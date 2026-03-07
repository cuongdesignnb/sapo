<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserWarehouseAccess extends Model
{
    use HasFactory;

    protected $table = 'user_warehouse_access';

    protected $fillable = [
        'user_id',
        'warehouse_id',
        'access_level',
        'permissions',
        'granted_by',
        'granted_at',
        'expires_at',
        'is_active',
        'notes'
    ];

    protected $casts = [
        'permissions' => 'array',
        'granted_at' => 'datetime',
        'expires_at' => 'datetime',
        'is_active' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function grantedBy()
    {
        return $this->belongsTo(User::class, 'granted_by');
    }

    public function isExpired(): bool
    {
        return $this->expires_at && $this->expires_at->isPast();
    }

    public function isValid(): bool
    {
        return $this->is_active && !$this->isExpired();
    }

    public function hasPermission(string $permission): bool
    {
        $permissions = $this->permissions ?? [];
        return in_array($permission, $permissions) || in_array('*', $permissions);
    }

    public function addPermission(string $permission): void
    {
        $permissions = $this->permissions ?? [];
        if (!in_array($permission, $permissions)) {
            $permissions[] = $permission;
            $this->permissions = $permissions;
            $this->save();
        }
    }

    public function removePermission(string $permission): void
    {
        $permissions = $this->permissions ?? [];
        $permissions = array_filter($permissions, fn($p) => $p !== $permission);
        $this->permissions = array_values($permissions);
        $this->save();
    }

    public function revoke(): void
    {
        $this->update(['is_active' => false]);
    }

    public function extend(\DateTime $newExpiryDate): void
    {
        $this->update(['expires_at' => $newExpiryDate]);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeValid($query)
    {
        return $query->where('is_active', true)
                    ->where(function ($q) {
                        $q->whereNull('expires_at')
                          ->orWhere('expires_at', '>', now());
                    });
    }

    public function scopeExpired($query)
    {
        return $query->where('expires_at', '<', now());
    }

    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeForWarehouse($query, int $warehouseId)
    {
        return $query->where('warehouse_id', $warehouseId);
    }

    public function scopeWithAccessLevel($query, string $accessLevel)
    {
        return $query->where('access_level', $accessLevel);
    }

    public static function grantAccess(
        int $userId,
        int $warehouseId,
        string $accessLevel = 'read',
        array $permissions = [],
        int $grantedBy = null,
        \DateTime $expiresAt = null
    ): self {
        return static::create([
            'user_id' => $userId,
            'warehouse_id' => $warehouseId,
            'access_level' => $accessLevel,
            'permissions' => $permissions,
            'granted_by' => $grantedBy,
            'granted_at' => now(),
            'expires_at' => $expiresAt,
            'is_active' => true
        ]);
    }

    public static function revokeAccess(int $userId, int $warehouseId): bool
    {
        return static::where('user_id', $userId)
                    ->where('warehouse_id', $warehouseId)
                    ->update(['is_active' => false]) > 0;
    }

    public static function getUserAccess(int $userId, int $warehouseId): ?self
    {
        return static::valid()
                    ->where('user_id', $userId)
                    ->where('warehouse_id', $warehouseId)
                    ->first();
    }

    public static function cleanupExpired(): int
    {
        return static::expired()->update(['is_active' => false]);
    }
}