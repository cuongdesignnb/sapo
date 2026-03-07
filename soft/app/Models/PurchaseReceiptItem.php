<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PurchaseReceiptItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'purchase_receipt_id',
        'purchase_order_item_id',
        'product_id',
        'quantity_received',
        'returned_quantity',
        'unit_cost',
        'total_cost',
        'expiry_date',
        'lot_number',
        'condition_status',
        'note',
    ];

    protected $casts = [
        'expiry_date' => 'date',
        'unit_cost' => 'decimal:2',
        'total_cost' => 'decimal:2',
    ];

    protected $appends = [
        'returnable_quantity',
        'returned_quantity_total'
    ];

    // Relationships
    public function purchaseReceipt()
    {
        return $this->belongsTo(PurchaseReceipt::class);
    }

    public function purchaseOrderItem()
    {
        return $this->belongsTo(PurchaseOrderItem::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function returnItems()
    {
        return $this->hasMany(PurchaseReturnOrderItem::class, 'purchase_receipt_item_id');
    }

    public function serials()
    {
        return $this->hasMany(ProductSerial::class, 'purchase_receipt_item_id');
    }

    // Computed attributes
    public function getReturnableQuantityAttribute()
    {
        return $this->quantity_received - $this->returned_quantity;
    }

    public function getReturnedQuantityTotalAttribute()
    {
        return $this->returnItems()->sum('quantity');
    }

    // Scopes
    public function scopeReturnable($query)
    {
        return $query->whereRaw('(quantity_received - returned_quantity) > 0');
    }

    public function scopeBySupplier($query, $supplierId)
    {
        return $query->whereHas('purchaseReceipt.purchaseOrder', function($q) use ($supplierId) {
            $q->where('supplier_id', $supplierId);
        });
    }

    // Boot method
    protected static function boot()
    {
        parent::boot();

        static::saving(function ($item) {
            $item->total_cost = $item->quantity_received * $item->unit_cost;
        });
    }
}