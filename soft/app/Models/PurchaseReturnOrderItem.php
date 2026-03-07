<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PurchaseReturnOrderItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'purchase_return_order_id',
        'purchase_receipt_id',
        'purchase_receipt_item_id',
        'product_id',
        'unit_id',
        'quantity',
        'max_returnable_quantity',
        'returned_quantity',
        'price',
        'total',
        'note',
        'return_reason',
        'condition_status',
        'lot_number',
        'expiry_date',
    ];

    protected $casts = [
        'expiry_date' => 'date',
        'price' => 'decimal:2',
        'total' => 'decimal:2',
    ];

    protected $appends = [
        'returnable_quantity'
    ];

    // Relationships
    public function purchaseReturnOrder()
    {
        return $this->belongsTo(PurchaseReturnOrder::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function unit()
    {
        return $this->belongsTo(Unit::class);
    }

    public function purchaseReceipt()
    {
        return $this->belongsTo(PurchaseReceipt::class);
    }

    public function purchaseReceiptItem()
    {
        return $this->belongsTo(PurchaseReceiptItem::class);
    }

    // Computed attributes
    public function getReturnableQuantityAttribute()
    {
        return $this->max_returnable_quantity - $this->returned_quantity;
    }

    // Boot method to calculate total
    protected static function boot()
    {
        parent::boot();

        static::saving(function ($item) {
            $item->total = $item->quantity * $item->price;
        });
    }
}