<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SalaryTemplate extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'type',
        'base_salary',
        'description',
    ];

    protected $casts = [
        'base_salary' => 'decimal:2',
    ];

    public function employeeSettings()
    {
        return $this->hasMany(EmployeeSalarySetting::class, 'salary_template_id');
    }
}
