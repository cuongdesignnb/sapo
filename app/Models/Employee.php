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
        'email',
        'cccd',
        'branch_id',
        'department_id',
        'job_title_id',
        'is_active',
        'balance',
        'notes',
        'avatar',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'balance' => 'decimal:2',
    ];

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function department()
    {
        return $this->belongsTo(Department::class);
    }

    public function jobTitle()
    {
        return $this->belongsTo(JobTitle::class);
    }

    public function schedules()
    {
        return $this->hasMany(EmployeeWorkSchedule::class);
    }

    public function attendanceLogs()
    {
        return $this->hasMany(AttendanceLog::class);
    }
    public function timekeepingRecords()
    {
        return $this->hasMany(TimekeepingRecord::class);
    }

    public function salarySetting()
    {
        return $this->hasOne(EmployeeSalarySetting::class);
    }

    public function salaryComponents()
    {
        return $this->hasMany(EmployeeSalaryComponent::class);
    }

    /**
     * Tính tổng số công trong khoảng ngày
     */
    public function getWorkUnitsForRange($from, $to): float
    {
        return $this->timekeepingRecords()
            ->whereBetween('work_date', [$from, $to])
            ->where('attendance_type', 'work')
            ->sum('work_units');
    }

    /**
     * Tính lương dự kiến theo chấm công
     */
    public function calculateSalaryForRange($from, $to): array
    {
        $setting = $this->salarySetting;
        if (!$setting) return ['base' => 0, 'allowances' => 0, 'deductions' => 0, 'total' => 0, 'work_units' => 0];

        $records = $this->timekeepingRecords()
            ->whereBetween('work_date', [$from, $to])
            ->get();

        $workUnits = $records->where('attendance_type', 'work')->sum('work_units');
        $paidLeaveUnits = $records->where('attendance_type', 'leave_paid')->sum('work_units');
        $totalUnits = $workUnits + $paidLeaveUnits;
        $otMinutes = $records->sum('ot_minutes');

        // Tính lương cơ bản
        $baseSalary = $setting->base_salary;
        if ($setting->type === 'hourly') {
            $baseSalary = $totalUnits * 8 * $setting->base_salary; // base_salary = lương/giờ
        }

        // Phụ cấp & giảm trừ
        $components = $this->salaryComponents;
        $allowances = $components->where('type', 'allowance')->sum('amount');
        $deductions = $components->where('type', 'deduction')->sum('amount');

        $total = $baseSalary + $allowances - $deductions;

        return [
            'base' => round($baseSalary),
            'allowances' => round($allowances),
            'deductions' => round($deductions),
            'ot_minutes' => $otMinutes,
            'work_units' => $totalUnits,
            'paid_leave_units' => $paidLeaveUnits,
            'total' => round(max(0, $total)),
        ];
    }
}
