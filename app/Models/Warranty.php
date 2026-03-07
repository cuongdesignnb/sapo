<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Warranty extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'invoice_code',
        'product_id',
        'customer_name',
        'serial_imei',
        'warranty_period',
        'purchase_date',
        'warranty_end_date',
        'has_reminder_off',
        'maintenance_note',
    ];

    protected $casts = [
        'purchase_date' => 'datetime',
        'warranty_end_date' => 'datetime',
        'has_reminder_off' => 'boolean',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
