<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EmployeeWorkSchedule extends Model
{
    use HasFactory;

    protected $fillable = [
        'employee_id',
        'warehouse_id',
        'shift_id',
        'work_date',
        'slot',
        'start_time',
        'end_time',
        'shift_name',
        'status',
        'notes',
    ];

    protected $casts = [
        'work_date' => 'date:Y-m-d',
        'slot' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function shift()
    {
        return $this->belongsTo(Shift::class);
    }

    public function timekeepingRecord()
    {
        return $this->hasOne(TimekeepingRecord::class, 'employee_work_schedule_id');
    }
}
