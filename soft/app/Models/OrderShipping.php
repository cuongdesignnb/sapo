<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OrderShipping extends Model
{
    use HasFactory;

    protected $table = 'order_shipping';
    
    protected $fillable = [
        'order_id',
        'provider_id',
        'shipping_method',
        'tracking_number',
        'carrier',
        'shipping_fee',
        'payment_by',          // NEW FIELD
        'cost',
        'estimated_delivery',
        'actual_delivery',
        'delivery_address',
        'delivery_phone',
        'delivery_contact',
        'pickup_address',
        'pickup_phone',
        'weight',
        'dimensions',
        'cod_amount',
        'insurance_value',
        'status',
        'note',
    ];

    protected $casts = [
        'shipping_fee' => 'decimal:2',
        'cost' => 'decimal:2',
        'weight' => 'decimal:2',
        'cod_amount' => 'decimal:2',
        'insurance_value' => 'decimal:2',
        'estimated_delivery' => 'datetime',
        'actual_delivery' => 'datetime',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function provider(): BelongsTo
    {
        return $this->belongsTo(ShippingProvider::class, 'provider_id');
    }

    public function logs(): HasMany
    {
        return $this->hasMany(ShippingLog::class)->orderBy('logged_at', 'desc');
    }

    public function getStatusTextAttribute()
    {
        $statusMap = [
            'pending' => 'Chờ lấy hàng',
            'picked_up' => 'Đã lấy hàng',
            'in_transit' => 'Đang vận chuyển',
            'delivered' => 'Đã giao hàng',
            'failed' => 'Giao hàng thất bại',
        ];

        return $statusMap[$this->status] ?? $this->status;
    }

    // NEW METHOD: Get payment responsibility text
    public function getPaymentByTextAttribute()
    {
        return $this->payment_by === 'sender' ? 'Người gửi' : 'Người nhận';
    }

    // NEW METHOD: Check if sender pays shipping
    public function isSenderPay(): bool
    {
        return $this->payment_by === 'sender';
    }

    // NEW METHOD: Check if receiver pays shipping
    public function isReceiverPay(): bool
    {
        return $this->payment_by === 'receiver';
    }

    public function getProviderNameAttribute()
    {
        return $this->provider ? $this->provider->name : $this->carrier;
    }

    public function canUpdate()
    {
        return in_array($this->status, ['pending', 'picked_up']);
    }

    public function scopeByProvider($query, $providerId)
    {
        return $query->where('provider_id', $providerId);
    }

    public function scopeByStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    // NEW SCOPE: Filter by payment responsibility
    public function scopeByPaymentBy($query, $paymentBy)
    {
        return $query->where('payment_by', $paymentBy);
    }
}