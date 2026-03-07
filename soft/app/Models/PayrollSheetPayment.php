<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PayrollSheetPayment extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'payroll_sheet_id',
        'payroll_sheet_item_id',
        'employee_id',
        'amount',
        'payment_method',
        'status',
        'paid_at',
        'created_by',
        'notes',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'paid_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function sheet()
    {
        return $this->belongsTo(PayrollSheet::class, 'payroll_sheet_id');
    }

    public function item()
    {
        return $this->belongsTo(PayrollSheetItem::class, 'payroll_sheet_item_id');
    }

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
