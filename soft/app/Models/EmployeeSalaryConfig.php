<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EmployeeSalaryConfig extends Model
{
    use HasFactory;

    protected $fillable = [
        'employee_id',
        'salary_template_id',
        'pay_warehouse_id',
        'effective_from',
        'base_salary_override',
        'overtime_hourly_rate_override',
        'commission_rate',
        'status',
        'notes',
    ];

    protected $casts = [
        'effective_from' => 'date',
        'base_salary_override' => 'decimal:2',
        'overtime_hourly_rate_override' => 'decimal:2',
        'commission_rate' => 'decimal:4',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    public function template()
    {
        return $this->belongsTo(SalaryTemplate::class, 'salary_template_id');
    }

    public function payWarehouse()
    {
        return $this->belongsTo(Warehouse::class, 'pay_warehouse_id');
    }
}
