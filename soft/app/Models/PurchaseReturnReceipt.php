<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PurchaseReturnReceipt extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'purchase_receipt_id',
        'purchase_return_order_id',
        'supplier_id',
        'warehouse_id',
        'returned_by',
        'returned_at',
        'reason',
        'status',
        'total_amount',
        'refund_amount',
        'note',
        'created_by',
        'approved_by',
        'approved_at'
    ];

    protected $casts = [
        'returned_at' => 'datetime',
        'approved_at' => 'datetime',
        'total_amount' => 'decimal:2',
        'refund_amount' => 'decimal:2',
    ];

    // Status constants
    const STATUS_PENDING = 'pending';
    const STATUS_APPROVED = 'approved';
    const STATUS_RETURNED = 'returned';
    const STATUS_COMPLETED = 'completed';
    const STATUS_CANCELLED = 'cancelled';

    public static function getStatuses()
    {
        return [
            self::STATUS_PENDING => 'Chờ duyệt',
            self::STATUS_APPROVED => 'Đã duyệt',
            self::STATUS_RETURNED => 'Đã trả hàng',
            self::STATUS_COMPLETED => 'Hoàn tất',
            self::STATUS_CANCELLED => 'Đã hủy',
        ];
    }

    // Relationships
    public function purchaseReceipt()
    {
        return $this->belongsTo(PurchaseReceipt::class);
    }

    public function purchaseReturnOrder()
    {
        return $this->belongsTo(PurchaseReturnOrder::class);
    }

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }

    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function returnedBy()
    {
        return $this->belongsTo(User::class, 'returned_by');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function items()
    {
        return $this->hasMany(PurchaseReturnItem::class, 'purchase_return_receipt_id');
    }

    // Helper methods
    public function getStatusLabelAttribute()
    {
        return self::getStatuses()[$this->status] ?? $this->status;
    }

    public function canEdit()
    {
        return in_array($this->status, [self::STATUS_PENDING]);
    }

    public function canApprove()
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function canCancel()
    {
        return in_array($this->status, [self::STATUS_PENDING, self::STATUS_APPROVED]);
    }
}