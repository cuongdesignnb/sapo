<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PurchaseReturnItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'purchase_return_receipt_id',
        'purchase_return_order_item_id',
        'product_id',
        'unit_id',
        'quantity_returned',
        'unit_cost',
        'total_cost',
        'return_reason',
        'condition_status',
        'lot_number',
        'expiry_date',
        'note'
    ];

    protected $casts = [
        'quantity_returned' => 'integer',
        'unit_cost' => 'decimal:2',
        'total_cost' => 'decimal:2',
        'expiry_date' => 'date',
    ];

    // Condition status constants
    const CONDITION_GOOD = 'good';
    const CONDITION_DAMAGED = 'damaged';
    const CONDITION_EXPIRED = 'expired';
    const CONDITION_WRONG_ITEM = 'wrong_item';
    const CONDITION_EXCESS = 'excess';
    const CONDITION_DEFECTIVE = 'defective';

    public static function getConditionStatuses()
    {
        return [
            self::CONDITION_GOOD => 'Tốt',
            self::CONDITION_DAMAGED => 'Hỏng',
            self::CONDITION_EXPIRED => 'Hết hạn',
            self::CONDITION_WRONG_ITEM => 'Sai sản phẩm',
            self::CONDITION_EXCESS => 'Thừa',
            self::CONDITION_DEFECTIVE => 'Lỗi sản xuất',
        ];
    }

    // Relationships
    public function purchaseReturnReceipt()
    {
        return $this->belongsTo(PurchaseReturnReceipt::class, 'purchase_return_receipt_id');
    }

    public function purchaseReturnOrderItem()
    {
        return $this->belongsTo(PurchaseReturnOrderItem::class, 'purchase_return_order_item_id');
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function unit()
    {
        return $this->belongsTo(Unit::class);
    }

    // Helper methods
    public function getConditionStatusLabelAttribute()
    {
        return self::getConditionStatuses()[$this->condition_status] ?? $this->condition_status;
    }

    // Boot method to auto-calculate total
    protected static function boot()
    {
        parent::boot();

        static::saving(function ($item) {
            $item->total_cost = $item->quantity_returned * $item->unit_cost;
        });
    }
}