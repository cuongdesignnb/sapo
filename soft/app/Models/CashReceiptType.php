<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CashReceiptType extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'name',
        'recipient_type',
        'impact_type',
        'impact_action',
        'description',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function receipts()
    {
        return $this->hasMany(CashReceipt::class, 'type_id');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeByRecipientType($query, $type)
    {
        return $query->where('recipient_type', $type);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order')->orderBy('name');
    }
}