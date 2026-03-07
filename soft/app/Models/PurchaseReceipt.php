<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PurchaseReceipt extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'purchase_order_id',
    'supplier_id',
        'warehouse_id',
        'received_by',
        'received_at',
    'approved_by',
    'approved_at',
        'status',
        'total_amount',
    'payment_type', // full|partial|debt
    'paid',
    'need_pay',
        'note'
    ];

    protected $casts = [
        'received_at' => 'datetime',
    'approved_at' => 'datetime',
        'total_amount' => 'decimal:2',
    'paid' => 'decimal:2',
    'need_pay' => 'decimal:2',
    ];

    public function purchaseOrder()
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }

    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function receiver()
    {
        return $this->belongsTo(User::class, 'received_by');
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function items()
    {
        return $this->hasMany(PurchaseReceiptItem::class);
    }
}