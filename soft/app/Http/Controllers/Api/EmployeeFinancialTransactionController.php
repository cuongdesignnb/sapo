<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\EmployeeFinancialTransaction;
use Illuminate\Http\Request;

class EmployeeFinancialTransactionController extends Controller
{
    public function index(Request $request)
    {
        $query = EmployeeFinancialTransaction::query()->with([
            'employee:id,code,name',
            'warehouse:id,name',
        ]);

        if ($request->filled('employee_id')) {
            $query->where('employee_id', $request->integer('employee_id'));
        }

        if ($request->filled('warehouse_id')) {
            $query->where('warehouse_id', $request->integer('warehouse_id'));
        }

        if ($request->filled('type')) {
            $query->where('type', (string) $request->get('type'));
        }

        if ($request->filled('from')) {
            $query->whereDate('occurred_at', '>=', $request->date('from'));
        }

        if ($request->filled('to')) {
            $query->whereDate('occurred_at', '<=', $request->date('to'));
        }

        // Summary balance
        $balance = (clone $query)->toBase()->sum('amount');

        $perPage = (int) $request->get('per_page', 50);
        $items = $query->orderByDesc('occurred_at')->orderByDesc('id')->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $items->items(),
            'summary' => [
                'balance' => (float) $balance,
            ],
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

    public function store(Request $request)
    {
        $data = $request->validate([
            'employee_id' => ['required', 'integer', 'exists:employees,id'],
            'warehouse_id' => ['nullable', 'integer'],
            'occurred_at' => ['required', 'date'],
            'type' => ['required', 'string', 'max:30', 'in:advance,repayment,adjustment'],
            'amount' => ['required', 'numeric'],
            'reference' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string'],
        ]);

        $tx = EmployeeFinancialTransaction::create([
            'employee_id' => $data['employee_id'],
            'warehouse_id' => $data['warehouse_id'] ?? null,
            'occurred_at' => $data['occurred_at'],
            'type' => $data['type'],
            'amount' => $data['amount'],
            'reference' => $data['reference'] ?? null,
            'notes' => $data['notes'] ?? null,
            'created_by' => auth()->id(),
        ]);

        $tx->load(['employee:id,code,name', 'warehouse:id,name']);

        return response()->json([
            'success' => true,
            'message' => 'Tạo giao dịch nợ/tạm ứng thành công',
            'data' => $tx,
        ]);
    }

    public function update(Request $request, EmployeeFinancialTransaction $employeeFinancialTransaction)
    {
        $data = $request->validate([
            'warehouse_id' => ['nullable', 'integer'],
            'occurred_at' => ['required', 'date'],
            'type' => ['required', 'string', 'max:30', 'in:advance,repayment,adjustment'],
            'amount' => ['required', 'numeric'],
            'reference' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string'],
        ]);

        $employeeFinancialTransaction->update([
            'warehouse_id' => $data['warehouse_id'] ?? $employeeFinancialTransaction->warehouse_id,
            'occurred_at' => $data['occurred_at'],
            'type' => $data['type'],
            'amount' => $data['amount'],
            'reference' => $data['reference'] ?? null,
            'notes' => $data['notes'] ?? null,
        ]);

        $employeeFinancialTransaction->load(['employee:id,code,name', 'warehouse:id,name']);

        return response()->json([
            'success' => true,
            'message' => 'Cập nhật giao dịch thành công',
            'data' => $employeeFinancialTransaction,
        ]);
    }

    public function destroy(EmployeeFinancialTransaction $employeeFinancialTransaction)
    {
        $employeeFinancialTransaction->delete();

        return response()->json([
            'success' => true,
            'message' => 'Xóa giao dịch thành công',
        ]);
    }
}
