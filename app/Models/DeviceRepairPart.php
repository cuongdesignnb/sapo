<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DeviceRepairPart extends Model
{
    protected $fillable = [
        'device_repair_id',
        'product_id',
        'quantity',
        'unit_cost',
        'total_cost',
        'exported_by',
        'notes',
    ];

    protected $casts = [
        'unit_cost'  => 'decimal:0',
        'total_cost' => 'decimal:0',
    ];

    public function deviceRepair()
    {
        return $this->belongsTo(DeviceRepair::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function exportedByUser()
    {
        return $this->belongsTo(\App\Models\User::class, 'exported_by');
    }
}
