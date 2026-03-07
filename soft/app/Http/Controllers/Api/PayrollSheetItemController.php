<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PayrollSheetItem;
use Illuminate\Http\Request;

class PayrollSheetItemController extends Controller
{
    public function index(Request $request)
    {
        $query = PayrollSheetItem::query()->with([
            'sheet:id,code,name,pay_cycle,period_start,period_end,status,generated_at,generated_by,notes',
            'employee:id,code,name',
            'warehouse:id,name',
        ]);

        if ($request->filled('payroll_sheet_id')) {
            $query->where('payroll_sheet_id', $request->integer('payroll_sheet_id'));
        }

        if ($request->filled('employee_id')) {
            $query->where('employee_id', $request->integer('employee_id'));
        }

        if ($request->filled('warehouse_id')) {
            $query->where('warehouse_id', $request->integer('warehouse_id'));
        }

        if ($request->filled('from') || $request->filled('to')) {
            $from = $request->filled('from') ? $request->date('from') : null;
            $to = $request->filled('to') ? $request->date('to') : null;

            $query->whereHas('sheet', function ($q) use ($from, $to) {
                if ($from) {
                    $q->whereDate('period_start', '>=', $from);
                }
                if ($to) {
                    $q->whereDate('period_end', '<=', $to);
                }
            });
        }

        $perPage = (int) $request->get('per_page', 50);
        $items = $query->orderByDesc('id')->paginate($perPage);

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
}
