<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\EmployeeSalaryConfig;
use Illuminate\Http\Request;

class EmployeeSalaryConfigController extends Controller
{
    public function index(Request $request)
    {
        $query = EmployeeSalaryConfig::query()->with([
            'employee:id,code,name',
            'template:id,name',
            'payWarehouse:id,name',
        ]);

        if ($request->filled('employee_id')) {
            $query->where('employee_id', $request->integer('employee_id'));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }

        $configs = $query->orderByDesc('id')->get();

        return response()->json([
            'success' => true,
            'data' => $configs,
        ]);
    }

    public function show(EmployeeSalaryConfig $employeeSalaryConfig)
    {
        $employeeSalaryConfig->load([
            'employee:id,code,name',
            'template:id,name',
            'payWarehouse:id,name',
        ]);

        return response()->json([
            'success' => true,
            'data' => $employeeSalaryConfig,
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'employee_id' => ['required', 'integer', 'exists:employees,id', 'unique:employee_salary_configs,employee_id'],
            'salary_template_id' => ['required', 'integer', 'exists:salary_templates,id'],
            'pay_warehouse_id' => ['nullable', 'integer'],
            'effective_from' => ['nullable', 'date'],
            'base_salary_override' => ['nullable', 'numeric'],
            'overtime_hourly_rate_override' => ['nullable', 'numeric'],
            'commission_rate' => ['nullable', 'numeric'],
            'status' => ['nullable', 'string', 'max:30'],
            'notes' => ['nullable', 'string'],
        ]);

        $config = EmployeeSalaryConfig::create([
            'employee_id' => $data['employee_id'],
            'salary_template_id' => $data['salary_template_id'],
            'pay_warehouse_id' => $data['pay_warehouse_id'] ?? null,
            'effective_from' => $data['effective_from'] ?? null,
            'base_salary_override' => $data['base_salary_override'] ?? null,
            'overtime_hourly_rate_override' => $data['overtime_hourly_rate_override'] ?? null,
            'commission_rate' => $data['commission_rate'] ?? null,
            'status' => $data['status'] ?? 'active',
            'notes' => $data['notes'] ?? null,
        ]);

        $config->load([
            'employee:id,code,name',
            'template:id,name',
            'payWarehouse:id,name',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Tạo cấu hình lương nhân viên thành công',
            'data' => $config,
        ]);
    }

    public function update(Request $request, EmployeeSalaryConfig $employeeSalaryConfig)
    {
        $data = $request->validate([
            'salary_template_id' => ['required', 'integer', 'exists:salary_templates,id'],
            'pay_warehouse_id' => ['nullable', 'integer'],
            'effective_from' => ['nullable', 'date'],
            'base_salary_override' => ['nullable', 'numeric'],
            'overtime_hourly_rate_override' => ['nullable', 'numeric'],
            'commission_rate' => ['nullable', 'numeric'],
            'status' => ['nullable', 'string', 'max:30'],
            'notes' => ['nullable', 'string'],
        ]);

        $employeeSalaryConfig->update([
            'salary_template_id' => $data['salary_template_id'],
            'pay_warehouse_id' => $data['pay_warehouse_id'] ?? $employeeSalaryConfig->pay_warehouse_id,
            'effective_from' => $data['effective_from'] ?? $employeeSalaryConfig->effective_from,
            'base_salary_override' => $data['base_salary_override'] ?? $employeeSalaryConfig->base_salary_override,
            'overtime_hourly_rate_override' => $data['overtime_hourly_rate_override'] ?? $employeeSalaryConfig->overtime_hourly_rate_override,
            'commission_rate' => $data['commission_rate'] ?? $employeeSalaryConfig->commission_rate,
            'status' => $data['status'] ?? $employeeSalaryConfig->status,
            'notes' => $data['notes'] ?? $employeeSalaryConfig->notes,
        ]);

        $employeeSalaryConfig->load([
            'employee:id,code,name',
            'template:id,name',
            'payWarehouse:id,name',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Cập nhật cấu hình lương nhân viên thành công',
            'data' => $employeeSalaryConfig,
        ]);
    }

    public function destroy(EmployeeSalaryConfig $employeeSalaryConfig)
    {
        $employeeSalaryConfig->delete();

        return response()->json([
            'success' => true,
            'message' => 'Xóa cấu hình lương nhân viên thành công',
        ]);
    }
}
