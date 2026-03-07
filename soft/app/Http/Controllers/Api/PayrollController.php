<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Payroll;
use Illuminate\Http\Request;

class PayrollController extends Controller
{
    public function index(Request $request)
    {
        $query = Payroll::query()->with(['employee:id,code,name']);

        if ($request->filled('employee_id')) {
            $query->where('employee_id', $request->integer('employee_id'));
        }

        if ($request->filled('from')) {
            $query->whereDate('period_start', '>=', $request->date('from'));
        }

        if ($request->filled('to')) {
            $query->whereDate('period_end', '<=', $request->date('to'));
        }

        $perPage = (int) $request->get('per_page', 20);
        $payrolls = $query->orderByDesc('period_start')->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $payrolls->items(),
            'pagination' => [
                'current_page' => $payrolls->currentPage(),
                'last_page' => $payrolls->lastPage(),
                'per_page' => $payrolls->perPage(),
                'total' => $payrolls->total(),
                'from' => $payrolls->firstItem(),
                'to' => $payrolls->lastItem(),
            ],
        ]);
    }

    public function show(Payroll $payroll)
    {
        $payroll->load(['employee:id,code,name']);

        return response()->json([
            'success' => true,
            'data' => $payroll,
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'employee_id' => ['required', 'integer', 'exists:employees,id'],
            'period_start' => ['required', 'date'],
            'period_end' => ['required', 'date'],
            'base_salary' => ['nullable', 'numeric'],
            'allowances' => ['nullable', 'numeric'],
            'deductions' => ['nullable', 'numeric'],
            'net_salary' => ['nullable', 'numeric'],
            'status' => ['nullable', 'string', 'max:30'],
            'notes' => ['nullable', 'string'],
        ]);

        $payroll = Payroll::create([
            'employee_id' => $data['employee_id'],
            'period_start' => $data['period_start'],
            'period_end' => $data['period_end'],
            'base_salary' => $data['base_salary'] ?? 0,
            'allowances' => $data['allowances'] ?? 0,
            'deductions' => $data['deductions'] ?? 0,
            'net_salary' => $data['net_salary'] ?? (($data['base_salary'] ?? 0) + ($data['allowances'] ?? 0) - ($data['deductions'] ?? 0)),
            'status' => $data['status'] ?? 'draft',
            'notes' => $data['notes'] ?? null,
        ]);

        $payroll->load(['employee:id,code,name']);

        return response()->json([
            'success' => true,
            'message' => 'Tạo bảng lương thành công',
            'data' => $payroll,
        ]);
    }

    public function update(Request $request, Payroll $payroll)
    {
        $data = $request->validate([
            'period_start' => ['required', 'date'],
            'period_end' => ['required', 'date'],
            'base_salary' => ['nullable', 'numeric'],
            'allowances' => ['nullable', 'numeric'],
            'deductions' => ['nullable', 'numeric'],
            'net_salary' => ['nullable', 'numeric'],
            'status' => ['nullable', 'string', 'max:30'],
            'notes' => ['nullable', 'string'],
        ]);

        $base = $data['base_salary'] ?? $payroll->base_salary;
        $allowances = $data['allowances'] ?? $payroll->allowances;
        $deductions = $data['deductions'] ?? $payroll->deductions;

        $payroll->update([
            'period_start' => $data['period_start'],
            'period_end' => $data['period_end'],
            'base_salary' => $base,
            'allowances' => $allowances,
            'deductions' => $deductions,
            'net_salary' => $data['net_salary'] ?? ($base + $allowances - $deductions),
            'status' => $data['status'] ?? $payroll->status,
            'notes' => $data['notes'] ?? $payroll->notes,
        ]);

        $payroll->load(['employee:id,code,name']);

        return response()->json([
            'success' => true,
            'message' => 'Cập nhật bảng lương thành công',
            'data' => $payroll,
        ]);
    }

    public function destroy(Payroll $payroll)
    {
        $payroll->delete();

        return response()->json([
            'success' => true,
            'message' => 'Xóa bảng lương thành công',
        ]);
    }
}
