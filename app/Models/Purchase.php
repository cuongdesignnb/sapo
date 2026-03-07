<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Purchase extends Model
{
    protected $fillable = [
        'code',
        'supplier_id',
        'user_id',
        'total_amount',
        'discount',
        'paid_amount',
        'debt_amount',
        'note',
        'status',
    ];

    public function items()
    {
        return $this->hasMany(PurchaseItem::class);
    }

    public function supplier()
    {
        return $this->belongsTo(Customer::class, 'supplier_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
