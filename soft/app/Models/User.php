<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'role_id',
        'warehouse_id',
        'employee_code',
        'phone',
        'avatar',
        'status',
        'last_login_at',
        'created_by',
        'notes'
    ];

    /**
     * The attributes that should be hidden for serialization.
     */
    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_secret',
        'two_factor_recovery_codes',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'last_login_at' => 'datetime',
        'password' => 'hashed',
        'two_factor_enabled_at' => 'datetime',
    ];

    /**
     * Relationship with Role
     */
    public function role()
    {
        return $this->belongsTo(Role::class);
    }

    /**
     * Relationship with Warehouse (default warehouse)
     */
    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class);
    }

    /**
     * Relationship with UserSession
     */
    public function sessions()
    {
        return $this->hasMany(UserSession::class);
    }

    /**
     * Relationship with warehouse access permissions
     */
    public function warehouseAccess()
    {
        return $this->hasMany(UserWarehouseAccess::class);
    }

    /**
     * User who created this user
     */
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Users created by this user
     */
    public function createdUsers()
    {
        return $this->hasMany(User::class, 'created_by');
    }

    /**
     * Check if user has specific permission
     */
    public function hasPermission(string $permission): bool
    {
        if (!$this->role) {
            return false;
        }

        // Super admin and admin have all permissions
        if (in_array($this->role->name, ['super_admin', 'admin'], true)) {
            return true;
        }

        $permissions = $this->role->permissions ?? [];
        
        // Check for wildcard permission
        if (in_array('*', $permissions)) {
            return true;
        }

        return in_array($permission, $permissions);
    }

    /**
     * Check if user has specific role
     */
    public function hasRole(string $role): bool
    {
        return $this->role && $this->role->name === $role;
    }

    /**
     * Check if user has any of the given roles
     */
    public function hasAnyRole(array $roles): bool
    {
        return $this->role && in_array($this->role->name, $roles);
    }

    /**
     * Get accessible warehouse IDs for this user
     */
    public function getAccessibleWarehouseIds(): array
    {
        // Super admin and admin can access all warehouses
        if ($this->hasAnyRole(['super_admin', 'admin'])) {
            return Warehouse::where('status', 'active')->pluck('id')->toArray();
        }

        // Get warehouse IDs from user_warehouse_access table
        $accessibleIds = $this->warehouseAccess()
            ->where('is_active', true)
            ->where(function ($query) {
                $query->whereNull('expires_at')
                      ->orWhere('expires_at', '>', now());
            })
            ->pluck('warehouse_id')
            ->toArray();

        // Add default warehouse if user has one
        if ($this->warehouse_id && !in_array($this->warehouse_id, $accessibleIds)) {
            $accessibleIds[] = $this->warehouse_id;
        }

        return array_unique($accessibleIds);
    }

    /**
     * Check if user has access to specific warehouse
     */
    public function hasWarehouseAccess(int $warehouseId): bool
    {
        return in_array($warehouseId, $this->getAccessibleWarehouseIds());
    }

    /**
     * Get user's warehouse access level for specific warehouse
     */
    public function getWarehouseAccessLevel(int $warehouseId): ?string
    {
        if ($this->hasAnyRole(['super_admin', 'admin'])) {
            return 'manage';
        }

        $access = $this->warehouseAccess()
            ->where('warehouse_id', $warehouseId)
            ->where('is_active', true)
            ->where(function ($query) {
                $query->whereNull('expires_at')
                      ->orWhere('expires_at', '>', now());
            })
            ->first();

        return $access ? $access->access_level : null;
    }
    /**
     * Get default warehouse ID for user (for auto-redirect after login)
     */
    public function getDefaultWarehouseId(): ?int
    {
        // Ưu tiên warehouse_id được gán trực tiếp trong bảng users
        if ($this->warehouse_id) {
            return $this->warehouse_id;
        }
        
        // Nếu không có, lấy warehouse đầu tiên trong user_warehouse_access
        $access = $this->warehouseAccess()
            ->where('is_active', true)
            ->where(function($query) {
                $query->whereNull('expires_at')
                      ->orWhere('expires_at', '>', now());
            })
            ->orderBy('created_at', 'asc')
            ->first();
            
        return $access ? $access->warehouse_id : null;
    }
    /**
     * Check if user is active
     */
    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    /**
     * Get user's full permissions (role permissions + warehouse specific permissions)
     */
    public function getAllPermissions(): array
    {
        $permissions = $this->role ? ($this->role->permissions ?? []) : [];

        // Add warehouse-specific permissions if any
        $warehousePermissions = $this->warehouseAccess()
            ->where('is_active', true)
            ->where(function ($query) {
                $query->whereNull('expires_at')
                      ->orWhere('expires_at', '>', now());
            })
            ->get()
            ->flatMap(function ($access) {
                return $access->permissions ?? [];
            })
            ->toArray();

        return array_unique(array_merge($permissions, $warehousePermissions));
    }

    /**
     * Scope for active users only
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    /**
     * Scope for users with specific role
     */
    public function scopeWithRole($query, string $roleName)
    {
        return $query->whereHas('role', function ($q) use ($roleName) {
            $q->where('name', $roleName);
        });
    }

    /**
     * Scope for users with access to specific warehouse
     */
    public function scopeWithWarehouseAccess($query, int $warehouseId)
    {
        return $query->where(function ($q) use ($warehouseId) {
            $q->where('warehouse_id', $warehouseId)
              ->orWhereHas('warehouseAccess', function ($access) use ($warehouseId) {
                  $access->where('warehouse_id', $warehouseId)
                         ->where('is_active', true);
              });
        });
    }
    // Thêm vào class User

public function createdCashReceipts()
{
    return $this->hasMany(CashReceipt::class, 'created_by');
}

public function approvedCashReceipts()
{
    return $this->hasMany(CashReceipt::class, 'approved_by');
}

public function createdCashPayments()
{
    return $this->hasMany(CashPayment::class, 'created_by');
}

public function approvedCashPayments()
{
    return $this->hasMany(CashPayment::class, 'approved_by');
}
public function accessibleWarehouses()
    {
        return $this->belongsToMany(
            \App\Models\Warehouse::class,
            'user_warehouse_access',
            'user_id',
            'warehouse_id'
        )->wherePivot('is_active', true)
         ->withPivot(['access_level', 'permissions', 'granted_at']);
    }

    // ========================================
    // TWO FACTOR AUTHENTICATION METHODS
    // ========================================
    
    /**
     * Check if user has 2FA enabled
     */
    public function hasTwoFactorEnabled(): bool
    {
        // Support legacy column name `two_factor_confirmed_at` if it exists in DB.
        $enabledAt = $this->two_factor_enabled_at ?? $this->getAttribute('two_factor_confirmed_at');

        return !is_null($enabledAt) && !is_null($this->two_factor_secret);
    }
    
    /**
     * Get decrypted 2FA secret
     */
    public function getTwoFactorSecret(): ?string
    {
        if (!$this->two_factor_secret) {
            return null;
        }

        // Preferred: encrypted secret stored with Laravel encrypt().
        try {
            return decrypt($this->two_factor_secret);
        } catch (\Illuminate\Contracts\Encryption\DecryptException $e) {
            // Backward-compat: some deployments stored the Base32 secret in plaintext.
            // Only accept values that look like a TOTP Base32 secret.
            $value = (string) $this->two_factor_secret;
            if (preg_match('/^[A-Z2-7]+=*$/', $value)) {
                return $value;
            }

            return null;
        }
    }
    
    /**
     * Set encrypted 2FA secret
     */
    public function setTwoFactorSecret(string $secret): void
    {
        $this->two_factor_secret = encrypt($secret);
    }
    
    /**
     * Get recovery codes as array
     */
    public function getRecoveryCodes(): array
    {
        return $this->two_factor_recovery_codes 
            ? json_decode($this->two_factor_recovery_codes, true) 
            : [];
    }
    
    /**
     * Set recovery codes from array
     */
    public function setRecoveryCodes(array $codes): void
    {
        $this->two_factor_recovery_codes = json_encode($codes);
    }
    
    /**
     * Use a recovery code (remove it from the list)
     */
    public function useRecoveryCode(string $code): bool
    {
        $codes = $this->getRecoveryCodes();
        $hashedCode = hash('sha256', $code);
        
        if (in_array($hashedCode, $codes)) {
            $remainingCodes = array_values(array_diff($codes, [$hashedCode]));
            $this->setRecoveryCodes($remainingCodes);
            $this->save();
            return true;
        }
        
        return false;
    }
    
    /**
     * Disable 2FA completely
     */
    public function disable2FA(): void
    {
        $this->two_factor_secret = null;
        $this->two_factor_enabled_at = null;
        $this->two_factor_recovery_codes = null;
        $this->save();
    }

    /**
     * Enable 2FA with secret and recovery codes
     */
    public function enableTwoFactor(string $secret): void
    {
        $this->setTwoFactorSecret($secret);
        $this->two_factor_enabled_at = now();

        // Initialize recovery codes (hashed). Use regenerateRecoveryCodes() to get plain codes.
        if (empty($this->two_factor_recovery_codes)) {
            $this->regenerateRecoveryCodes();
            return;
        }

        $this->save();
    }

    /**
     * Disable 2FA (alias for disable2FA)
     */
    public function disableTwoFactor(): void
    {
        $this->disable2FA();
    }

    /**
     * Regenerate recovery codes
     */
    public function regenerateRecoveryCodes(): array
    {
        $recoveryCodes = [];
        for ($i = 0; $i < 8; $i++) {
            $code = strtolower(bin2hex(random_bytes(5)));
            $recoveryCodes[] = $code;
        }
        
        // Store hashed versions
        $hashedCodes = array_map(fn($code) => hash('sha256', $code), $recoveryCodes);
        $this->two_factor_recovery_codes = json_encode($hashedCodes);
        $this->save();
        
        // Return plain codes for user to save
        return $recoveryCodes;
    }

    public function getCurrentWarehouse()
    {
        $warehouseId = session('current_warehouse_id');
        
        if (!$warehouseId) {
            $userSession = \App\Models\UserSession::where('user_id', $this->id)
                ->where('is_active', true)
                ->latest('last_activity')
                ->first();
                
            $warehouseId = $userSession?->warehouse_id;
        }

        return $warehouseId ? \App\Models\Warehouse::find($warehouseId) : null;
    }
}