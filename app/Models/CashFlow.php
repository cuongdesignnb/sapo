<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CashFlow extends Model
{
    protected $fillable = [
        'code',
        'type',
        'amount',
        'time',
        'category',
        'target_type',
        'target_id',
        'target_name',
        'accounting_result',
        'payment_method',
        'bank_account_id',
        'reference_type',
        'reference_code',
        'description',
    ];

    public function bankAccount()
    {
        return $this->belongsTo(BankAccount::class);
    }
}
