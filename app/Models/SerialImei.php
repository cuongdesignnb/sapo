<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SerialImei extends Model
{
    protected $fillable = [
        'product_id',
        'variant_id',
        'serial_number',
        'status',
        'purchase_id',
        'cost_price',
    ];

    protected $casts = [
        'cost_price' => 'decimal:0',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function variant()
    {
        return $this->belongsTo(ProductVariant::class, 'variant_id');
    }
}

