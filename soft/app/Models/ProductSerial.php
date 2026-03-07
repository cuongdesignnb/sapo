<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductSerial extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_id',
        'warehouse_id',
        'serial_number',
        'status',
        'cost_price',
        'purchase_receipt_item_id',
        'order_item_id',
        'note',
    ];

    protected $casts = [
        'cost_price' => 'decimal:2',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    // ========== STATUS CONSTANTS ==========
    const STATUS_IN_STOCK    = 'in_stock';
    const STATUS_SOLD        = 'sold';
    const STATUS_RETURNED    = 'returned';
    const STATUS_DEFECTIVE   = 'defective';
    const STATUS_TRANSFERRED = 'transferred';

    const STATUS_MAP = [
        self::STATUS_IN_STOCK    => 'Trong kho',
        self::STATUS_SOLD        => 'Đã bán',
        self::STATUS_RETURNED    => 'Đã trả',
        self::STATUS_DEFECTIVE   => 'Lỗi/Hỏng',
        self::STATUS_TRANSFERRED => 'Đang chuyển',
    ];

    const STATUS_COLORS = [
        self::STATUS_IN_STOCK    => 'success',
        self::STATUS_SOLD        => 'primary',
        self::STATUS_RETURNED    => 'warning',
        self::STATUS_DEFECTIVE   => 'danger',
        self::STATUS_TRANSFERRED => 'info',
    ];

    // ========== RELATIONSHIPS ==========
    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function purchaseReceiptItem()
    {
        return $this->belongsTo(PurchaseReceiptItem::class);
    }

    public function orderItem()
    {
        return $this->belongsTo(OrderItem::class);
    }

    public function histories()
    {
        return $this->hasMany(ProductSerialHistory::class)->orderByDesc('created_at');
    }

    // ========== ACCESSORS ==========
    public function getStatusTextAttribute()
    {
        return self::STATUS_MAP[$this->status] ?? 'Không xác định';
    }

    public function getStatusColorAttribute()
    {
        return self::STATUS_COLORS[$this->status] ?? 'secondary';
    }

    // ========== SCOPES ==========
    public function scopeInStock($query)
    {
        return $query->where('status', self::STATUS_IN_STOCK);
    }

    public function scopeSold($query)
    {
        return $query->where('status', self::STATUS_SOLD);
    }

    public function scopeAvailableInWarehouse($query, $warehouseId)
    {
        return $query->where('warehouse_id', $warehouseId)
                     ->where('status', self::STATUS_IN_STOCK);
    }

    public function scopeByProduct($query, $productId)
    {
        return $query->where('product_id', $productId);
    }

    public function scopeSearch($query, $search)
    {
        return $query->where('serial_number', 'like', "%{$search}%");
    }

    // ========== HELPER METHODS ==========

    /**
     * Mark this serial as sold and link to order item.
     */
    public function markAsSold($orderItemId, $userId = null)
    {
        $oldWarehouseId = $this->warehouse_id;

        $this->update([
            'status' => self::STATUS_SOLD,
            'order_item_id' => $orderItemId,
        ]);

        $this->recordHistory('sold', $oldWarehouseId, null, 'App\\Models\\OrderItem', $orderItemId, $userId);
    }

    /**
     * Mark this serial as returned (from a sale).
     */
    public function markAsReturned($warehouseId, $userId = null, $note = null)
    {
        $this->update([
            'status' => self::STATUS_IN_STOCK,
            'warehouse_id' => $warehouseId,
            'order_item_id' => null,
        ]);

        $this->recordHistory('returned', null, $warehouseId, null, null, $userId, $note);
    }

    /**
     * Transfer this serial to another warehouse.
     */
    public function transferTo($toWarehouseId, $userId = null, $note = null)
    {
        $fromWarehouseId = $this->warehouse_id;

        $this->update([
            'warehouse_id' => $toWarehouseId,
            'status' => self::STATUS_IN_STOCK,
        ]);

        $this->recordHistory('transferred', $fromWarehouseId, $toWarehouseId, null, null, $userId, $note);
    }

    /**
     * Mark this serial as defective.
     */
    public function markAsDefective($userId = null, $note = null)
    {
        $this->update(['status' => self::STATUS_DEFECTIVE]);

        $this->recordHistory('defective', $this->warehouse_id, null, null, null, $userId, $note);
    }

    /**
     * Record a history entry for this serial.
     */
    public function recordHistory($action, $fromWarehouseId = null, $toWarehouseId = null, $refType = null, $refId = null, $userId = null, $note = null)
    {
        return ProductSerialHistory::create([
            'product_serial_id' => $this->id,
            'action' => $action,
            'from_warehouse_id' => $fromWarehouseId,
            'to_warehouse_id' => $toWarehouseId,
            'reference_type' => $refType,
            'reference_id' => $refId,
            'user_id' => $userId ?? auth()->id(),
            'note' => $note,
        ]);
    }

    /**
     * Bulk create serials when importing goods.
     */
    public static function bulkImport(array $serialNumbers, int $productId, int $warehouseId, float $costPrice = 0, int $purchaseReceiptItemId = null)
    {
        $created = [];

        foreach ($serialNumbers as $serialNumber) {
            $serial = self::create([
                'product_id' => $productId,
                'warehouse_id' => $warehouseId,
                'serial_number' => trim($serialNumber),
                'status' => self::STATUS_IN_STOCK,
                'cost_price' => $costPrice,
                'purchase_receipt_item_id' => $purchaseReceiptItemId,
            ]);

            $serial->recordHistory(
                'imported',
                null,
                $warehouseId,
                $purchaseReceiptItemId ? 'App\\Models\\PurchaseReceiptItem' : null,
                $purchaseReceiptItemId
            );

            $created[] = $serial;
        }

        return $created;
    }

    /**
     * Count available serials for a product in a warehouse.
     */
    public static function countAvailable(int $productId, int $warehouseId = null)
    {
        $query = self::where('product_id', $productId)
                     ->where('status', self::STATUS_IN_STOCK);

        if ($warehouseId) {
            $query->where('warehouse_id', $warehouseId);
        }

        return $query->count();
    }
}
