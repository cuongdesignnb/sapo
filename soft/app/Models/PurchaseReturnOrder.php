<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PurchaseReturnOrder extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'supplier_id', 
        'warehouse_id',
        'created_by',
        'returned_at',
        'status',
        'total',
        'refunded',
        'note',
        'return_reason',
        'supplier_invoice_code',
        'internal_note',
        'discount',
        'tax',
        'need_refund',
        'approved_by',
        'approved_at'
    ];

    protected $casts = [
        'returned_at' => 'datetime',
        'approved_at' => 'datetime',
        'total' => 'decimal:2',
        'refunded' => 'decimal:2',
        'discount' => 'decimal:2',
        'tax' => 'decimal:2',
        'need_refund' => 'decimal:2',
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
    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }

    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class);
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
        return $this->hasMany(PurchaseReturnOrderItem::class);
    }

    // Scopes
    public function scopeBySupplier($query, $supplierId)
    {
        return $query->where('supplier_id', $supplierId);
    }

    public function scopeByWarehouse($query, $warehouseId)
    {
        return $query->where('warehouse_id', $warehouseId);
    }

    public function scopeByStatus($query, $status)
    {
        return $query->where('status', $status);
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

    public function canReturn()
    {
        return $this->status === self::STATUS_APPROVED;
    }

    public function canCancel()
    {
        return in_array($this->status, [self::STATUS_PENDING, self::STATUS_APPROVED]);
    }

    // Additional helpers referenced by controller
    public function canUpdate(): bool
    {
        return in_array($this->status, [self::STATUS_PENDING]);
    }

    public function canUpdateStatus(string $to): bool
    {
        $allowed = [
            self::STATUS_PENDING => [self::STATUS_APPROVED, self::STATUS_CANCELLED],
            self::STATUS_APPROVED => [self::STATUS_RETURNED, self::STATUS_CANCELLED],
            self::STATUS_RETURNED => [self::STATUS_COMPLETED],
        ];
        return isset($allowed[$this->status]) && in_array($to, $allowed[$this->status]);
    }

    public function canDelete(): bool
    {
        return in_array($this->status, [self::STATUS_PENDING, self::STATUS_CANCELLED]);
    }
}