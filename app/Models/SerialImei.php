<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SerialImei extends Model
{
    protected $fillable = [
        'product_id',
        'serial_number',
        'status',
        'purchase_id',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
