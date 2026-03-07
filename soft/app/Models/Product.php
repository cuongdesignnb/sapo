<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    use HasFactory;

    protected $fillable = [
        'sku',
        'name',
        'quantity',
        'unit_id',
        'cost_price',
        'wholesale_price', 
        'retail_price',
        'stock_in_warehouses',
        'supplier_id',
        'note',
        'barcode',
        'weight',
        'status',
        'track_serial',
        'category_name',
        'brand_name'
    ];

    protected $casts = [
        'cost_price' => 'decimal:0',
        'wholesale_price' => 'decimal:0',
        'retail_price' => 'decimal:0',
        'quantity' => 'integer',
        'track_serial' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];

    protected $appends = [
        'sellable_quantity',
        'sellable_unit',
        'stock_quantity', 
        'stock_unit',
        'formatted_retail_price',
        'formatted_cost_price',
        'available_stock',
        'can_sell_quantity',
        'has_quantity_mismatch'  // ✅ THÊM MỚI
    ];

    // Existing relationships
    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }

    public function unit()
    {
        return $this->belongsTo(Unit::class);
    }

    // Serial/IMEI relationship
    public function serials()
    {
        return $this->hasMany(ProductSerial::class);
    }

    public function availableSerials()
    {
        return $this->hasMany(ProductSerial::class)->where('status', ProductSerial::STATUS_IN_STOCK);
    }

    // Warehouse relationships
    public function warehouseProducts()
    {
        return $this->hasMany(WarehouseProduct::class);
    }

    public function warehouses()
    {
        return $this->belongsToManyThrough(Warehouse::class, WarehouseProduct::class);
    }

    // ✅ SỬA ACCESSORS CHÍNH - DÙNG WAREHOUSE STOCK
    public function getSellableQuantityAttribute()
    {
        // Số lượng có thể bán = tổng warehouse stock
        return $this->total_warehouse_stock ?? 0;
    }

    public function getStockQuantityAttribute()
    {
        // Tồn kho hiển thị = tổng warehouse stock  
        return $this->total_warehouse_stock ?? 0;
    }

    public function getSellableUnitAttribute()
    {
        return '1 phiên bán';
    }

    public function getStockUnitAttribute()
    {
        return '1 phiên bán';
    }

    public function getFormattedRetailPriceAttribute()
    {
        return number_format($this->retail_price, 0, '.', '.');
    }

    public function getFormattedCostPriceAttribute()
    {
        return number_format($this->cost_price, 0, '.', '.');
    }

    public function getCategoryAttribute()
    {
        return $this->category_name;
    }

    public function getBrandAttribute()
    {
        return $this->brand_name;
    }

    // ✅ SỬA WAREHOUSE ACCESSOR - OPTIMIZED
    public function getTotalWarehouseStockAttribute()
    {
        // Ưu tiên lấy từ joined data (nhanh hơn)
        if (isset($this->attributes['total_warehouse_stock'])) {
            return (int) $this->attributes['total_warehouse_stock'];
        }
        
        // Fallback: query relationship
        return $this->warehouseProducts()->sum('quantity');
    }

    public function getTotalWarehouseValueAttribute()
    {
        return $this->warehouseProducts()->selectRaw('SUM(quantity * cost) as total')->value('total') ?? 0;
    }

    public function getWarehouseCountAttribute()
    {
        return $this->warehouseProducts()->where('quantity', '>', 0)->count();
    }

    public function getLowestStockWarehouseAttribute()
    {
        return $this->warehouseProducts()
            ->with('warehouse')
            ->where('quantity', '>', 0)
            ->orderBy('quantity', 'asc')
            ->first();
    }

    public function getHighestStockWarehouseAttribute()
    {
        return $this->warehouseProducts()
            ->with('warehouse')
            ->where('quantity', '>', 0)
            ->orderBy('quantity', 'desc')
            ->first();
    }

    // ✅ EXISTING ACCESSORS - GIỮ NGUYÊN LOGIC
    public function getAvailableStockAttribute()
    {
        return $this->warehouseProducts()
            ->selectRaw('SUM(quantity - COALESCE(reserved_quantity, 0)) as available')
            ->value('available') ?? 0;
    }

    public function getCanSellQuantityAttribute()
    {
        // Giữ logic cũ: min giữa product.quantity và available stock
        $warehouseAvailable = $this->available_stock;
        $productQuantity = $this->quantity ?? 0;
        
        return min($productQuantity, $warehouseAvailable);
    }

    // ✅ THÊM ACCESSOR MỚI - DETECT MISMATCH
    public function getHasQuantityMismatchAttribute()
    {
        $productQty = $this->quantity ?? 0;
        $warehouseStock = $this->total_warehouse_stock;
        
        return $productQty != $warehouseStock;
    }

    // ✅ VALIDATION METHODS - GIỮ NGUYÊN
    public function hasEnoughStock($requestedQuantity)
    {
        return $this->available_stock >= $requestedQuantity;
    }

    public function validateQuantityAgainstStock($newQuantity)
    {
        $totalStock = $this->total_warehouse_stock;
        
        if ($newQuantity > $totalStock) {
            return [
                'valid' => false,
                'message' => "Số lượng bán ({$newQuantity}) vượt quá tồn kho thực tế ({$totalStock})",
                'max_allowed' => $totalStock,
                'current_stock' => $totalStock
            ];
        }
        
        return ['valid' => true];
    }

    public function getStockByWarehouse($warehouseId = null)
    {
        if ($warehouseId) {
            return $this->getStockInWarehouse($warehouseId);
        }
        
        return $this->total_warehouse_stock;
    }

    // ✅ SYNC METHODS - GIỮ NGUYÊN
    public function syncQuantityWithWarehouse()
    {
        $totalStock = $this->total_warehouse_stock;
        
        // ✅ SỬA: Sync khi có bất kỳ mismatch nào (không chỉ quantity > stock)
        if ($this->quantity != $totalStock) {
            $oldQuantity = $this->quantity;
            $this->update(['quantity' => $totalStock]);
            
            \Log::info("Product {$this->sku}: Synced quantity from {$oldQuantity} to {$totalStock}");
            return true;
        }
        
        return false;
    }

    public function hasQuantityMismatch()
    {
        return $this->quantity != $this->total_warehouse_stock;
    }

    // Existing scopes for filtering
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeSearch($query, $search)
    {
        return $query->where(function($q) use ($search) {
            $q->where('name', 'like', "%{$search}%")
              ->orWhere('sku', 'like', "%{$search}%")  
              ->orWhere('barcode', 'like', "%{$search}%");
        });
    }

    public function scopeByCategory($query, $category)
    {
        return $query->where('category_name', $category);
    }

    public function scopeByBrand($query, $brand)
    {
        return $query->where('brand_name', $brand);
    }

    // Warehouse scopes
    public function scopeInWarehouse($query, $warehouseId)
    {
        return $query->whereHas('warehouseProducts', function($q) use ($warehouseId) {
            $q->where('warehouse_id', $warehouseId)->where('quantity', '>', 0);
        });
    }

    public function scopeLowStockInAnyWarehouse($query)
    {
        return $query->whereHas('warehouseProducts', function($q) {
            $q->whereRaw('quantity <= min_stock AND min_stock > 0');
        });
    }

    public function scopeOutOfStockInAnyWarehouse($query)
    {
        return $query->whereHas('warehouseProducts', function($q) {
            $q->where('quantity', '<=', 0);
        });
    }

    // ✅ SỬA SCOPE - DETECT ANY MISMATCH
    public function scopeQuantityMismatch($query)
    {
        return $query->whereRaw('products.quantity != COALESCE((
            SELECT SUM(warehouse_products.quantity) 
            FROM warehouse_products 
            WHERE warehouse_products.product_id = products.id
        ), 0)');
    }

    // Existing helper methods for warehouse operations
    public function getStockInWarehouse($warehouseId)
    {
        $warehouseProduct = $this->warehouseProducts()
            ->where('warehouse_id', $warehouseId)
            ->first();
            
        return $warehouseProduct ? $warehouseProduct->quantity : 0;
    }

    public function getAvailableStockInWarehouse($warehouseId)
    {
        $warehouseProduct = $this->warehouseProducts()
            ->where('warehouse_id', $warehouseId)
            ->first();
            
        return $warehouseProduct ? $warehouseProduct->available_stock : 0;
    }

    public function hasLowStockInAnyWarehouse()
    {
        return $this->warehouseProducts()
            ->whereRaw('quantity <= min_stock AND min_stock > 0')
            ->exists();
    }

    public function isOutOfStockInAnyWarehouse()
    {
        return $this->warehouseProducts()
            ->where('quantity', '<=', 0)
            ->exists();
    }

    // ✅ STATIC METHODS - GIỮ NGUYÊN
    public static function syncAllQuantityMismatches()
    {
        $products = self::quantityMismatch()->get();
        $syncedCount = 0;
        
        foreach ($products as $product) {
            if ($product->syncQuantityWithWarehouse()) {
                $syncedCount++;
            }
        }
        
        return $syncedCount;
    }

    public static function withStockInfo()
    {
        return self::with(['warehouseProducts.warehouse'])
            ->selectRaw('products.*, 
                (SELECT SUM(wp.quantity) FROM warehouse_products wp WHERE wp.product_id = products.id) as warehouse_total_stock,
                (SELECT SUM(wp.quantity - COALESCE(wp.reserved_quantity, 0)) FROM warehouse_products wp WHERE wp.product_id = products.id) as warehouse_available_stock
            ');
    }
}