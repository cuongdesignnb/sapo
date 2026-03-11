<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\EmployeeSalarySetting;
use App\Models\Employee;
use Illuminate\Http\Request;

class EmployeeSalarySettingController extends Controller
{
    public function show($employeeId)
    {
        $employee = Employee::findOrFail($employeeId);
        $setting = EmployeeSalarySetting::where('employee_id', $employeeId)
            ->with('template.bonuses', 'template.commissions', 'template.allowances', 'template.deductions')
            ->first();

        return response()->json([
            'success' => true,
            'data' => $setting,
        ]);
    }

    public function upsert(Request $request, $employeeId)
    {
        $employee = Employee::findOrFail($employeeId);

        $data = $request->validate([
            'salary_type' => 'required|in:fixed,hourly',
            'base_salary' => 'required|numeric|min:0',
            'salary_template_id' => 'nullable|integer|exists:salary_templates,id',
        ]);

        $setting = EmployeeSalarySetting::updateOrCreate(
            ['employee_id' => $employeeId],
            $data
        );

        $setting->load('template.bonuses', 'template.commissions', 'template.allowances', 'template.deductions');

        return response()->json([
            'success' => true,
            'data' => $setting,
        ]);
    }
}
