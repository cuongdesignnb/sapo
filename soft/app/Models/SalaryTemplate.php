<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SalaryTemplate extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'base_salary',
        'standard_work_units',
        'half_day_threshold_hours',
        'overtime_hourly_rate',
        'status',
        'notes',
    ];

    protected $casts = [
        'base_salary' => 'decimal:2',
        'standard_work_units' => 'decimal:2',
        'half_day_threshold_hours' => 'decimal:2',
        'overtime_hourly_rate' => 'decimal:2',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function items()
    {
        return $this->hasMany(SalaryTemplateItem::class);
    }
}
