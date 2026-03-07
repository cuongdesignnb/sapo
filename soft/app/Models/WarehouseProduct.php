<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WarehouseProduct extends Model
{
    use HasFactory;

    protected $fillable = [
        'warehouse_id',
        'product_id',
        'quantity',
        'cost',
        'min_stock',
        'max_stock',
        'reserved_quantity',
        'last_import_date',
        'last_export_date'
    ];

    protected $casts = [
        'cost' => 'decimal:2',
        'last_import_date' => 'datetime',
        'last_export_date' => 'datetime',
    ];

    // Relationships
    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    // Scopes
    public function scopeLowStock($query)
    {
        return $query->whereRaw('quantity <= min_stock AND min_stock > 0');
    }

    public function scopeOutOfStock($query)
    {
        return $query->where('quantity', '<=', 0);
    }

    public function scopeOverStock($query)
    {
        return $query->whereRaw('quantity > max_stock AND max_stock > 0');
    }

    public function scopeInStock($query)
    {
        return $query->where('quantity', '>', 0);
    }

    // Accessors
    public function getAvailableStockAttribute()
    {
        return max(0, $this->quantity - $this->reserved_quantity);
    }

    public function getStockStatusAttribute()
    {
        if ($this->quantity <= 0) {
            return 'OUT_OF_STOCK';
        }
        
        if ($this->min_stock > 0 && $this->quantity <= $this->min_stock) {
            return 'LOW_STOCK';
        }
        
        if ($this->max_stock > 0 && $this->quantity > $this->max_stock) {
            return 'OVER_STOCK';
        }
        
        return 'IN_STOCK';
    }

    public function getStockStatusLabelAttribute()
    {
        return match($this->stock_status) {
            'OUT_OF_STOCK' => 'Hết hàng',
            'LOW_STOCK' => 'Sắp hết',
            'OVER_STOCK' => 'Dư thừa',
            'IN_STOCK' => 'Bình thường',
            default => 'Không xác định'
        };
    }

    public function getStockStatusColorAttribute()
    {
        return match($this->stock_status) {
            'OUT_OF_STOCK' => 'danger',
            'LOW_STOCK' => 'warning',
            'OVER_STOCK' => 'info',
            'IN_STOCK' => 'success',
            default => 'secondary'
        };
    }

    public function getTotalValueAttribute()
    {
        return $this->quantity * $this->cost;
    }

    public function getReservedPercentAttribute()
    {
        if ($this->quantity <= 0) return 0;
        return round(($this->reserved_quantity / $this->quantity) * 100, 2);
    }
}