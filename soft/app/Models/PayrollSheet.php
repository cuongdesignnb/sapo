<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PayrollSheet extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'name',
        'pay_cycle',
        'period_start',
        'period_end',
        'status',
        'generated_at',
        'generated_by',
        'notes',
    ];

    protected $casts = [
        'period_start' => 'date',
        'period_end' => 'date',
        'generated_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function items()
    {
        return $this->hasMany(PayrollSheetItem::class, 'payroll_sheet_id');
    }

    public function payments()
    {
        return $this->hasMany(PayrollSheetPayment::class, 'payroll_sheet_id');
    }
}
