<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WarehouseTransfer extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'warehouse_from',
        'warehouse_to',
        'created_by',
        'approved_by',
        'transfered_at',
        'status',
        'note'
    ];

    protected $casts = [
        'transfered_at' => 'datetime'
    ];

    public function warehouseFrom()
    {
        return $this->belongsTo(Warehouse::class, 'warehouse_from');
    }

    public function warehouseTo()
    {
        return $this->belongsTo(Warehouse::class, 'warehouse_to');
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
        return $this->hasMany(WarehouseTransferItem::class);
    }
}