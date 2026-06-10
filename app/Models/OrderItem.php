<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OrderItem extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected $casts = [
        'serial_ids' => 'array',
        'fulfilled_quantity' => 'integer',
    ];

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function invoiceItems()
    {
        return $this->hasMany(InvoiceItem::class, 'order_item_id');
    }

    public function getRemainingQuantityAttribute(): int
    {
        return max(0, (int) $this->qty - (int) ($this->fulfilled_quantity ?? 0));
    }
}
