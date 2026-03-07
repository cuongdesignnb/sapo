<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\EmployeeCommission;
use Illuminate\Http\Request;

class EmployeeCommissionController extends Controller
{
    public function index(Request $request)
    {
        $query = EmployeeCommission::query()->with(['employee:id,code,name']);

        if ($request->filled('employee_id')) {
            $query->where('employee_id', $request->integer('employee_id'));
        }

        if ($request->filled('from')) {
            $query->whereDate('earned_at', '>=', $request->date('from'));
        }

        if ($request->filled('to')) {
            $query->whereDate('earned_at', '<=', $request->date('to'));
        }

        $perPage = (int) $request->get('per_page', 20);
        $items = $query->orderByDesc('earned_at')->orderByDesc('id')->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $items->items(),
            'pagination' => [
                'current_page' => $items->currentPage(),
                'last_page' => $items->lastPage(),
                'per_page' => $items->perPage(),
                'total' => $items->total(),
                'from' => $items->firstItem(),
                'to' => $items->lastItem(),
            ],
        ]);
    }

    public function show(EmployeeCommission $commission)
    {
        $commission->load(['employee:id,code,name']);

        return response()->json([
            'success' => true,
            'data' => $commission,
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'employee_id' => ['required', 'integer', 'exists:employees,id'],
            'order_id' => ['nullable', 'integer'],
            'order_code' => ['nullable', 'string', 'max:100'],
            'earned_at' => ['nullable', 'date'],
            'order_total' => ['nullable', 'numeric'],
            'commission_rate' => ['nullable', 'numeric'],
            'commission_amount' => ['nullable', 'numeric'],
            'status' => ['nullable', 'string', 'max:30'],
            'notes' => ['nullable', 'string'],
        ]);

        $orderTotal = (float) ($data['order_total'] ?? 0);
        $rate = $data['commission_rate'];
        $amount = $data['commission_amount'];

        if ($amount === null && $rate !== null) {
            $amount = $orderTotal * ((float) $rate);
        }

        $commission = EmployeeCommission::create([
            'employee_id' => $data['employee_id'],
            'order_id' => $data['order_id'] ?? null,
            'order_code' => $data['order_code'] ?? null,
            'earned_at' => $data['earned_at'] ?? null,
            'order_total' => $orderTotal,
            'commission_rate' => $rate,
            'commission_amount' => $amount ?? 0,
            'status' => $data['status'] ?? 'draft',
            'notes' => $data['notes'] ?? null,
        ]);

        $commission->load(['employee:id,code,name']);

        return response()->json([
            'success' => true,
            'message' => 'Tạo hoa hồng thành công',
            'data' => $commission,
        ]);
    }

    public function update(Request $request, EmployeeCommission $commission)
    {
        $data = $request->validate([
            'order_id' => ['nullable', 'integer'],
            'order_code' => ['nullable', 'string', 'max:100'],
            'earned_at' => ['nullable', 'date'],
            'order_total' => ['nullable', 'numeric'],
            'commission_rate' => ['nullable', 'numeric'],
            'commission_amount' => ['nullable', 'numeric'],
            'status' => ['nullable', 'string', 'max:30'],
            'notes' => ['nullable', 'string'],
        ]);

        $orderTotal = $data['order_total'] ?? $commission->order_total;
        $rate = array_key_exists('commission_rate', $data) ? $data['commission_rate'] : $commission->commission_rate;

        $amount = $data['commission_amount'] ?? null;
        if ($amount === null && $rate !== null) {
            $amount = (float) $orderTotal * ((float) $rate);
        }

        $commission->update([
            'order_id' => $data['order_id'] ?? $commission->order_id,
            'order_code' => $data['order_code'] ?? $commission->order_code,
            'earned_at' => $data['earned_at'] ?? $commission->earned_at,
            'order_total' => $orderTotal,
            'commission_rate' => $rate,
            'commission_amount' => $amount ?? $commission->commission_amount,
            'status' => $data['status'] ?? $commission->status,
            'notes' => $data['notes'] ?? $commission->notes,
        ]);

        $commission->load(['employee:id,code,name']);

        return response()->json([
            'success' => true,
            'message' => 'Cập nhật hoa hồng thành công',
            'data' => $commission,
        ]);
    }

    public function destroy(EmployeeCommission $commission)
    {
        $commission->delete();

        return response()->json([
            'success' => true,
            'message' => 'Xóa hoa hồng thành công',
        ]);
    }
}
