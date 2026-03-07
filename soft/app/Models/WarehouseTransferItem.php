<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WarehouseTransferItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'warehouse_transfer_id',
        'product_id',
        'unit_id',
        'quantity',
        'cost',
        'note'
    ];

    protected $casts = [
        'cost' => 'decimal:2',
        'quantity' => 'integer'
    ];

    // Relationships
    public function warehouseTransfer()
    {
        return $this->belongsTo(WarehouseTransfer::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function unit()
    {
        return $this->belongsTo(Unit::class);
    }

    // Accessors
    public function getTotalAttribute()
    {
        return $this->quantity * $this->cost;
    }

    public function getFormattedCostAttribute()
    {
        return number_format($this->cost, 0, '.', '.');
    }

    public function getFormattedTotalAttribute()
    {
        return number_format($this->total, 0, '.', '.');
    }
}