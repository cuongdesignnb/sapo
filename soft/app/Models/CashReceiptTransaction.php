<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CashReceiptTransaction extends Model
{
    use HasFactory;

    // Tắt timestamps vì bảng chỉ có created_at
    public $timestamps = false;

    protected $fillable = [
        'receipt_id',
        'target_model',
        'target_id',
        'field_affected',
        'old_value',
        'new_value',
        'change_amount',
        'transaction_type',
    ];

    protected $casts = [
        'old_value' => 'decimal:2',
        'new_value' => 'decimal:2',
        'change_amount' => 'decimal:2',
        'created_at' => 'datetime',
    ];

    public function receipt()
    {
        return $this->belongsTo(CashReceipt::class, 'receipt_id');
    }

    // Override để chỉ set created_at
    public static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            $model->created_at = $model->created_at ?: now();
        });
    }
}