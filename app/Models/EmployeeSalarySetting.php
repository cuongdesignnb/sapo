<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EmployeeSalarySetting extends Model
{
    use HasFactory;

    protected $fillable = [
        'employee_id',
        'salary_template_id',
        'type',
        'base_salary',
        'has_bonus',
        'has_commission',
        'has_allowance',
        'has_deduction',
    ];

    protected $casts = [
        'base_salary' => 'decimal:2',
        'has_bonus' => 'boolean',
        'has_commission' => 'boolean',
        'has_allowance' => 'boolean',
        'has_deduction' => 'boolean',
    ];

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    public function template()
    {
        return $this->belongsTo(SalaryTemplate::class, 'salary_template_id');
    }
}
