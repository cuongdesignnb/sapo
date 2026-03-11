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
        'base_salary',
        'salary_type',
        'advanced_salary',
        'holiday_rate',
        'tet_rate',
        'has_overtime',
        'overtime_rate',
    ];

    protected $casts = [
        'base_salary' => 'integer',
        'advanced_salary' => 'boolean',
        'holiday_rate' => 'integer',
        'tet_rate' => 'integer',
        'has_overtime' => 'boolean',
        'overtime_rate' => 'integer',
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
