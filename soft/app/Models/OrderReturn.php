<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OrderReturn extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'order_id',
        'customer_id',
        'warehouse_id',
        'created_by',
        'returned_at',
        'status',
        'total',
        'refunded',
        'return_reason',
        'note',
        'internal_note',
        'approved_by',
        'approved_at'
    ];

    protected $casts = [
        'returned_at' => 'datetime',
        'approved_at' => 'datetime',
        'total' => 'decimal:2',
        'refunded' => 'decimal:2',
    ];

    // Status constants
    const STATUS_PENDING = 'pending';           // Chưa nhận hàng
    const STATUS_RECEIVED = 'received';         // Đã nhận hàng
    const STATUS_WAREHOUSED = 'warehoused';     // Đã nhập kho
    const STATUS_REFUNDED = 'refunded';         // Đã hoàn tiền
    const STATUS_CANCELLED = 'cancelled';       // Đã hủy

    public static function getStatuses()
    {
        return [
            self::STATUS_PENDING => 'Chưa nhận hàng',
            self::STATUS_RECEIVED => 'Đã nhận hàng',
            self::STATUS_WAREHOUSED => 'Đã nhập kho',
            self::STATUS_REFUNDED => 'Đã hoàn tiền',
            self::STATUS_CANCELLED => 'Đã hủy',
        ];
    }

    // Relationships
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderReturnItem::class);
    }

    // Accessors
    public function getStatusTextAttribute()
    {
        return self::getStatuses()[$this->status] ?? $this->status;
    }

    public function getStatusColorAttribute()
    {
        $colors = [
            self::STATUS_PENDING => 'yellow',      // Chưa nhận hàng (vàng)
            self::STATUS_RECEIVED => 'blue',       // Đã nhận hàng (xanh dương)
            self::STATUS_WAREHOUSED => 'purple',   // Đã nhập kho (tím)
            self::STATUS_REFUNDED => 'green',      // Đã hoàn tiền (xanh lá)
            self::STATUS_CANCELLED => 'red',       // Đã hủy (đỏ)
        ];

        return $colors[$this->status] ?? 'gray';
    }

    public function getRemainingRefundAttribute()
    {
        return $this->total - $this->refunded;
    }

    // Business logic methods
    public function canReceive(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function canWarehouse(): bool
    {
        return $this->status === self::STATUS_RECEIVED;
    }

    public function canRefund(): bool
    {
        return $this->status === self::STATUS_WAREHOUSED && $this->remaining_refund > 0;
    }

    public function canCancel(): bool
    {
        return in_array($this->status, [self::STATUS_PENDING, self::STATUS_RECEIVED]);
    }

    public function updateStatus($newStatus, $note = null, $userId = null)
    {
        $oldStatus = $this->status;
        
        $this->update(['status' => $newStatus]);

        // Log status change
        \Log::info("OrderReturn #{$this->code} status changed from {$oldStatus} to {$newStatus}", [
            'order_return_id' => $this->id,
            'old_status' => $oldStatus,
            'new_status' => $newStatus,
            'note' => $note,
            'changed_by' => $userId ?? auth()->id()
        ]);

        return $this;
    }

    // Generate unique code
    public static function generateCode(): string
    {
        $prefix = 'RT';
        $date = now()->format('ymd');
        
        $lastReturn = self::whereDate('created_at', now())
            ->latest('id')
            ->first();
            
        $sequence = $lastReturn ? (int)substr($lastReturn->code, -4) + 1 : 1;
        
        return $prefix . $date . str_pad($sequence, 4, '0', STR_PAD_LEFT);
    }

    // Boot method to auto-generate code
    protected static function boot()
    {
        parent::boot();
        
        static::creating(function ($model) {
            if (empty($model->code)) {
                $model->code = self::generateCode();
            }
        });
    }
}