<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DebtOffset extends Model
{
    protected $fillable = [
        'code',
        'customer_id',
        'amount',
        'receivable_before',
        'payable_before',
        'receivable_after',
        'payable_after',
        'is_auto',
        'note',
        'user_id',
        'status',
        'cancelled_at',
        'cancelled_by',
        'cancel_reason',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'receivable_before' => 'decimal:2',
        'payable_before' => 'decimal:2',
        'receivable_after' => 'decimal:2',
        'payable_after' => 'decimal:2',
        'is_auto' => 'boolean',
        'cancelled_at' => 'datetime',
    ];

    // ====== RELATIONSHIPS ======

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function cancelledByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    // ====== ACCESSORS ======

    public function getFormattedAmountAttribute(): string
    {
        return number_format($this->amount, 0, ',', '.') . ' VNĐ';
    }

    public function getStatusTextAttribute(): string
    {
        return match($this->status) {
            'active' => 'Đang hiệu lực',
            'cancelled' => 'Đã hủy',
            default => 'Không xác định'
        };
    }

    // ====== CODE GENERATION ======

    public static function generateCode(): string
    {
        $today = now()->format('ymd');
        $lastOffset = self::where('code', 'like', "OFS{$today}%")
            ->orderBy('id', 'desc')
            ->first();

        if ($lastOffset) {
            $lastSeq = (int) substr($lastOffset->code, -3);
            $nextSeq = str_pad($lastSeq + 1, 3, '0', STR_PAD_LEFT);
        } else {
            $nextSeq = '001';
        }

        return "OFS{$today}{$nextSeq}";
    }
}
