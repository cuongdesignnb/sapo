<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Role extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'name',
        'display_name',
        'description',
        'permissions',
        'is_system'
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'permissions' => 'array',
        'is_system' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];

    /**
     * Relationship with Users
     */
    public function users()
    {
        return $this->hasMany(User::class);
    }

    /**
     * Check if role has specific permission
     */
    public function hasPermission(string $permission): bool
    {
        $permissions = $this->permissions ?? [];

        // Check for wildcard permission
        if (in_array('*', $permissions)) {
            return true;
        }

        return in_array($permission, $permissions);
    }

    /**
     * Add permission to role
     */
    public function addPermission(string $permission): void
    {
        $permissions = $this->permissions ?? [];
        
        if (!in_array($permission, $permissions)) {
            $permissions[] = $permission;
            $this->permissions = $permissions;
            $this->save();
        }
    }

    /**
     * Remove permission from role
     */
    public function removePermission(string $permission): void
    {
        $permissions = $this->permissions ?? [];
        
        $permissions = array_filter($permissions, function($p) use ($permission) {
            return $p !== $permission;
        });
        
        $this->permissions = array_values($permissions);
        $this->save();
    }

    /**
     * Set multiple permissions at once
     */
    public function setPermissions(array $permissions): void
    {
        $this->permissions = array_values(array_unique($permissions));
        $this->save();
    }

    /**
     * Get all available permissions in the system
     */
    public static function getAllAvailablePermissions(): array
    {
        return [
            // User Management
            'users.view',
            'users.create',
            'users.edit',
            'users.delete',
            'users.manage',

            // Role Management
            'roles.view',
            'roles.create',
            'roles.edit',
            'roles.delete',
            'roles.manage',

            // Product Management
            'products.view',
            'products.create',
            'products.edit',
            'products.delete',
            'products.manage',
            'products.import',
            'products.export',

            // Category Management
            'categories.view',
            'categories.create',
            'categories.edit',
            'categories.delete',
            'categories.manage',

            // Warehouse Management
            'warehouse.view',
            'warehouse.create',
            'warehouse.edit',
            'warehouse.delete',
            'warehouse.manage',

            // Stock Management
            'stock.view',
            'stock.adjust',
            'stock.transfer',
            'stock.manage',

            // Customer Management
            'customers.view',
            'customers.create',
            'customers.edit',
            'customers.delete',
            'customers.manage',

            // Supplier Management
            'suppliers.view',
            'suppliers.create',
            'suppliers.edit',
            'suppliers.delete',
            'suppliers.manage',

            // Order Management
            'orders.view',
            'orders.create',
            'orders.edit',
            'orders.delete',
            'orders.manage',

            // POS System
            'pos.use',
            'pos.manage',

            // Reports & Analytics
            'reports.view',
            'reports.export',
            'reports.manage',

            // Dashboard
            'dashboard.view',

            // System Settings
            'system.settings',
            'system.backup',
            'system.maintenance',

            // Staff Management (for warehouse managers)
            'staff.view',
            'staff.manage'
        ];
    }

    /**
     * Get permissions grouped by category
     */
    public static function getPermissionsByCategory(): array
    {
        return [
            'Quản lý người dùng' => [
                'users.view' => 'Xem danh sách người dùng',
                'users.create' => 'Tạo người dùng mới',
                'users.edit' => 'Chỉnh sửa người dùng',
                'users.delete' => 'Xóa người dùng',
                'users.manage' => 'Quản lý toàn bộ người dùng'
            ],
            'Quản lý vai trò' => [
                'roles.view' => 'Xem danh sách vai trò',
                'roles.create' => 'Tạo vai trò mới',
                'roles.edit' => 'Chỉnh sửa vai trò',
                'roles.delete' => 'Xóa vai trò',
                'roles.manage' => 'Quản lý toàn bộ vai trò'
            ],
            'Quản lý sản phẩm' => [
                'products.view' => 'Xem danh sách sản phẩm',
                'products.create' => 'Tạo sản phẩm mới',
                'products.edit' => 'Chỉnh sửa sản phẩm',
                'products.delete' => 'Xóa sản phẩm',
                'products.manage' => 'Quản lý toàn bộ sản phẩm',
                'products.import' => 'Import sản phẩm',
                'products.export' => 'Export sản phẩm'
            ],
            'Quản lý kho hàng' => [
                'warehouse.view' => 'Xem danh sách kho',
                'warehouse.create' => 'Tạo kho mới',
                'warehouse.edit' => 'Chỉnh sửa kho',
                'warehouse.delete' => 'Xóa kho',
                'warehouse.manage' => 'Quản lý toàn bộ kho'
            ],
            'Quản lý tồn kho' => [
                'stock.view' => 'Xem tồn kho',
                'stock.adjust' => 'Điều chỉnh tồn kho',
                'stock.transfer' => 'Chuyển kho',
                'stock.manage' => 'Quản lý toàn bộ tồn kho'
            ],
            'Quản lý khách hàng' => [
                'customers.view' => 'Xem danh sách khách hàng',
                'customers.create' => 'Tạo khách hàng mới',
                'customers.edit' => 'Chỉnh sửa khách hàng',
                'customers.delete' => 'Xóa khách hàng',
                'customers.manage' => 'Quản lý toàn bộ khách hàng'
            ],
            'Quản lý nhà cung cấp' => [
                'suppliers.view' => 'Xem danh sách nhà cung cấp',
                'suppliers.create' => 'Tạo nhà cung cấp mới',
                'suppliers.edit' => 'Chỉnh sửa nhà cung cấp',
                'suppliers.delete' => 'Xóa nhà cung cấp',
                'suppliers.manage' => 'Quản lý toàn bộ nhà cung cấp'
            ],
            'Quản lý đơn hàng' => [
                'orders.view' => 'Xem danh sách đơn hàng',
                'orders.create' => 'Tạo đơn hàng mới',
                'orders.edit' => 'Chỉnh sửa đơn hàng',
                'orders.delete' => 'Xóa đơn hàng',
                'orders.manage' => 'Quản lý toàn bộ đơn hàng'
            ],
            'Hệ thống bán hàng' => [
                'pos.use' => 'Sử dụng POS',
                'pos.manage' => 'Quản lý POS'
            ],
            'Báo cáo & Thống kê' => [
                'reports.view' => 'Xem báo cáo',
                'reports.export' => 'Xuất báo cáo',
                'reports.manage' => 'Quản lý báo cáo'
            ],
            'Hệ thống' => [
                'dashboard.view' => 'Xem dashboard',
                'system.settings' => 'Cài đặt hệ thống',
                'system.backup' => 'Sao lưu hệ thống',
                'system.maintenance' => 'Bảo trì hệ thống'
            ]
        ];
    }

    /**
     * Check if role is system role (cannot be deleted)
     */
    public function isSystemRole(): bool
    {
        return (bool) $this->is_system;
    }

    /**
     * Scope for non-system roles only
     */
    public function scopeNonSystem($query)
    {
        return $query->where('is_system', false);
    }

    /**
     * Scope for system roles only
     */
    public function scopeSystemRoles($query)
    {
        return $query->where('is_system', true);
    }

    /**
     * Get role by name
     */
    public static function findByName(string $name): ?self
    {
        return static::where('name', $name)->first();
    }

    /**
     * Get default roles for seeding
     */
    public static function getDefaultRoles(): array
    {
        return [
            [
                'name' => 'super_admin',
                'display_name' => 'Super Admin',
                'description' => 'Quản trị viên hệ thống - Full quyền',
                'permissions' => ['*'],
                'is_system' => true
            ],
            [
                'name' => 'admin',
                'display_name' => 'Quản trị viên',
                'description' => 'Quản trị viên công ty - Quản lý toàn bộ',
                'permissions' => [
                    'users.manage', 'roles.manage', 'warehouses.manage', 
                    'products.manage', 'orders.manage', 'customers.manage', 
                    'suppliers.manage', 'reports.view', 'system.settings'
                ],
                'is_system' => true
            ],
            [
                'name' => 'warehouse_manager',
                'display_name' => 'Quản lý kho',
                'description' => 'Quản lý toàn bộ hoạt động của một kho',
                'permissions' => [
                    'warehouse.manage', 'products.manage', 'stock.manage', 
                    'orders.manage', 'customers.manage', 'pos.use', 
                    'reports.view', 'staff.manage'
                ],
                'is_system' => true
            ],
            [
                'name' => 'warehouse_staff',
                'display_name' => 'Nhân viên kho',
                'description' => 'Nhân viên bán hàng và quản lý tồn kho',
                'permissions' => [
                    'products.view', 'stock.adjust', 'orders.create', 
                    'orders.view', 'customers.view', 'customers.create', 'pos.use'
                ],
                'is_system' => true
            ],
            [
                'name' => 'cashier',
                'display_name' => 'Thu ngân',
                'description' => 'Nhân viên thu ngân - Chỉ bán hàng',
                'permissions' => ['pos.use', 'orders.create', 'customers.view'],
                'is_system' => true
            ],
            [
                'name' => 'viewer',
                'display_name' => 'Xem báo cáo',
                'description' => 'Chỉ xem báo cáo và thống kê',
                'permissions' => ['reports.view', 'dashboard.view'],
                'is_system' => true
            ]
        ];
    }
}