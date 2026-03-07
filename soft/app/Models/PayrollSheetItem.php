<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PayrollSheetItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'payroll_sheet_id',
        'employee_id',
        'warehouse_id',
        'base_salary',
        'standard_work_units',
        'worked_units',
        'overtime_minutes',
        'overtime_pay',
        'allowances',
        'deductions',
        'commissions',
        'gross_salary',
        'net_salary',
        'paid_amount',
        'breakdown',
    ];

    protected $casts = [
        'base_salary' => 'decimal:2',
        'standard_work_units' => 'decimal:2',
        'worked_units' => 'decimal:2',
        'overtime_minutes' => 'integer',
        'overtime_pay' => 'decimal:2',
        'allowances' => 'decimal:2',
        'deductions' => 'decimal:2',
        'commissions' => 'decimal:2',
        'gross_salary' => 'decimal:2',
        'net_salary' => 'decimal:2',
        'paid_amount' => 'decimal:2',
        'breakdown' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function sheet()
    {
        return $this->belongsTo(PayrollSheet::class, 'payroll_sheet_id');
    }

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function payments()
    {
        return $this->hasMany(PayrollSheetPayment::class, 'payroll_sheet_item_id');
    }
}
