<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Warehouse extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'name',
        'address',
        'manager_name',
        'phone',
        'email',
        'capacity',
        'current_value',
        'status',
        'note'
    ];

    protected $casts = [
        'capacity' => 'decimal:2',
        'current_value' => 'decimal:2',
    ];

    // Relationships
    public function warehouseProducts()
    {
        return $this->hasMany(WarehouseProduct::class);
    }

    public function products()
    {
        return $this->belongsToManyThrough(Product::class, WarehouseProduct::class);
    }

    public function stockExports()
    {
        return $this->hasMany(StockExport::class);
    }

    public function transfersFrom()
    {
        return $this->hasMany(WarehouseTransfer::class, 'warehouse_from');
    }

    public function transfersTo()
    {
        return $this->hasMany(WarehouseTransfer::class, 'warehouse_to');
    }

    public function purchaseOrders()
    {
        return $this->hasMany(PurchaseOrder::class);
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeInactive($query)
    {
        return $query->where('status', 'inactive');
    }

    public function scopeMaintenance($query)
    {
        return $query->where('status', 'maintenance');
    }

    // Accessors
    public function getCapacityUsagePercentAttribute()
    {
        if ($this->capacity <= 0) return 0;
        return round(($this->current_value / $this->capacity) * 100, 2);
    }

    public function getStatusLabelAttribute()
    {
        return match($this->status) {
            'active' => 'Hoạt động',
            'inactive' => 'Ngưng hoạt động',
            'maintenance' => 'Bảo trì',
            default => 'Không xác định'
        };
    }

    public function getTotalProductsAttribute()
    {
        return $this->warehouseProducts()->sum('quantity');
    }

    public function getTotalProductTypesAttribute()
    {
        return $this->warehouseProducts()->count();
    }

    // Mutators
    public function setPhoneAttribute($value)
    {
        $this->attributes['phone'] = preg_replace('/[^0-9]/', '', $value);
    }

    public function setEmailAttribute($value)
    {
        $this->attributes['email'] = strtolower(trim($value));
    }
    // Thêm vào class Warehouse

public function cashReceipts()
{
    return $this->hasMany(CashReceipt::class);
}

public function cashPayments()
{
    return $this->hasMany(CashPayment::class);
}
}