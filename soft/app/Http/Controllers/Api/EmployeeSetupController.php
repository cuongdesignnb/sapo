<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AttendanceDevice;
use App\Models\Employee;
use App\Models\EmployeeSalaryConfig;
use App\Models\EmployeeWorkSchedule;
use App\Models\PayrollSheet;
use App\Models\Shift;
use Illuminate\Http\Request;

class EmployeeSetupController extends Controller
{
    public function overview(Request $request)
    {
        $warehouseId = $request->filled('warehouse_id') ? $request->integer('warehouse_id') : null;

        $employeesQuery = Employee::query();
        $shiftsQuery = Shift::query();
        $schedulesQuery = EmployeeWorkSchedule::query();
        $devicesQuery = AttendanceDevice::query();
        $salaryConfigsQuery = EmployeeSalaryConfig::query();
        $payrollSheetsQuery = PayrollSheet::query();

        if ($warehouseId) {
            $employeesQuery->where('warehouse_id', $warehouseId);
            $shiftsQuery->where('warehouse_id', $warehouseId);
            $schedulesQuery->where('warehouse_id', $warehouseId);
            $devicesQuery->where('warehouse_id', $warehouseId);
        }

        $employeesTotal = $employeesQuery->count();
        $shiftsTotal = $shiftsQuery->count();
        $schedulesTotal = $schedulesQuery->count();
        $devicesTotal = $devicesQuery->count();
        $salaryConfigsTotal = $salaryConfigsQuery->count();
        $payrollSheetsTotal = $payrollSheetsQuery->count();
        $employeesScheduledDistinct = (clone $schedulesQuery)->distinct('employee_id')->count('employee_id');

        return response()->json([
            'success' => true,
            'data' => [
                'employees_total' => $employeesTotal,
                'shifts_total' => $shiftsTotal,
                'schedules_total' => $schedulesTotal,
                'devices_total' => $devicesTotal,
                'salary_configs_total' => $salaryConfigsTotal,
                'payroll_sheets_total' => $payrollSheetsTotal,
                'employees_scheduled_distinct' => $employeesScheduledDistinct,
            ],
        ]);
    }
}
