<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Employee extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'attendance_code',
        'name',
        'phone',
        'id_number',
        'email',
        'birth_date',
        'gender',
        'department',
        'title',
        'start_work_date',
        'avatar_path',
        'warehouse_id',
        'status',
        'notes',
    ];

    protected $casts = [
        'birth_date' => 'date',
        'start_work_date' => 'date',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function workWarehouses()
    {
        return $this->belongsToMany(Warehouse::class, 'employee_work_warehouses');
    }

    public function salaryConfig()
    {
        return $this->hasOne(EmployeeSalaryConfig::class);
    }

    public function schedules()
    {
        return $this->hasMany(EmployeeWorkSchedule::class);
    }

    public function timekeepingRecords()
    {
        return $this->hasMany(TimekeepingRecord::class);
    }

    public function financialTransactions()
    {
        return $this->hasMany(EmployeeFinancialTransaction::class);
    }
}
