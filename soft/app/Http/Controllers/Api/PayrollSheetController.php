<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PayrollSheet;
use App\Services\PayrollEngineService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class PayrollSheetController extends Controller
{
    public function __construct(private readonly PayrollEngineService $payrollEngineService)
    {
    }

    public function index(Request $request)
    {
        $query = PayrollSheet::query()
            ->withCount(['items as employees_count'])
            ->withSum('items as total_salary', 'net_salary')
            ->withSum('items as total_paid', 'paid_amount');

        if ($request->filled('from')) {
            $query->whereDate('period_start', '>=', $request->date('from'));
        }

        if ($request->filled('to')) {
            $query->whereDate('period_end', '<=', $request->date('to'));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }

        if ($request->filled('statuses')) {
            $statuses = $request->input('statuses');
            if (is_array($statuses) && count($statuses)) {
                $query->whereIn('status', $statuses);
            }
        }

        $perPage = (int) $request->get('per_page', 20);
        $sheets = $query->orderByDesc('period_start')->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => collect($sheets->items())->map(function ($s) {
                $totalSalary = (float) ($s->total_salary ?? 0);
                $totalPaid = (float) ($s->total_paid ?? 0);
                $s->total_remaining = max(0, $totalSalary - $totalPaid);
                return $s;
            })->values(),
            'pagination' => [
                'current_page' => $sheets->currentPage(),
                'last_page' => $sheets->lastPage(),
                'per_page' => $sheets->perPage(),
                'total' => $sheets->total(),
                'from' => $sheets->firstItem(),
                'to' => $sheets->lastItem(),
            ],
        ]);
    }

    public function show(PayrollSheet $payrollSheet)
    {
        $payrollSheet = $payrollSheet->fresh();

        // Best-effort ensure code/name exist
        if (!$payrollSheet->code) {
            $payrollSheet->forceFill([
                'code' => 'BL' . str_pad((string) $payrollSheet->id, 6, '0', STR_PAD_LEFT),
            ])->save();
        }
        if (!$payrollSheet->name) {
            $month = $payrollSheet->period_end?->format('m/Y');
            $payrollSheet->forceFill([
                'name' => $month ? ('Bảng lương tháng ' . $month) : null,
            ])->save();
        }

        $payrollSheet->load([
            'items.employee:id,code,name',
            'items.warehouse:id,name',
        ]);

        // Ensure item codes exist
        foreach ($payrollSheet->items as $item) {
            if (!$item->code) {
                $item->forceFill([
                    'code' => 'PL' . str_pad((string) $item->id, 6, '0', STR_PAD_LEFT),
                ])->save();
            }
        }

        $totals = DB::table('payroll_sheet_items')
            ->where('payroll_sheet_id', $payrollSheet->id)
            ->selectRaw('COALESCE(SUM(net_salary),0) as total_salary, COALESCE(SUM(paid_amount),0) as total_paid')
            ->first();
        $totalSalary = (float) ($totals->total_salary ?? 0);
        $totalPaid = (float) ($totals->total_paid ?? 0);
        $payrollSheet->total_salary = $totalSalary;
        $payrollSheet->total_paid = $totalPaid;
        $payrollSheet->total_remaining = max(0, $totalSalary - $totalPaid);
        $payrollSheet->employees_count = $payrollSheet->items->count();

        return response()->json([
            'success' => true,
            'data' => $payrollSheet,
        ]);
    }

    public function generate(Request $request)
    {
        $data = $request->validate([
            'period_start' => ['required', 'date'],
            'period_end' => ['required', 'date'],
            'recalculate_timekeeping' => ['nullable', 'boolean'],
            'pay_cycle' => ['nullable', 'string', Rule::in(['monthly', 'weekly', 'biweekly'])],
            'scope' => ['nullable', 'string', Rule::in(['all', 'custom'])],
            'employee_ids' => ['nullable', 'array'],
            'employee_ids.*' => ['integer', 'exists:employees,id'],
        ]);

        $scope = $data['scope'] ?? 'all';
        $employeeIds = null;
        if ($scope === 'custom') {
            $employeeIds = array_values(array_unique(array_map('intval', $data['employee_ids'] ?? [])));
            if (!count($employeeIds)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Vui lòng chọn ít nhất 1 nhân viên',
                ], 422);
            }
        }

        $sheet = $this->payrollEngineService->generateSheet(
            Carbon::parse($data['period_start']),
            Carbon::parse($data['period_end']),
            (bool) ($data['recalculate_timekeeping'] ?? true),
            $request->user()?->id,
            $data['pay_cycle'] ?? 'monthly',
            $employeeIds
        );

        $sheet->load([
            'items.employee:id,code,name',
            'items.warehouse:id,name',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Đã tạo bảng lương tự động theo công + hoa hồng',
            'data' => $sheet,
        ]);
    }

    public function lock(PayrollSheet $payrollSheet)
    {
        if ($payrollSheet->status !== 'draft') {
            return response()->json([
                'success' => false,
                'message' => 'Chỉ có thể chốt bảng lương ở trạng thái draft',
            ], 422);
        }

        $payrollSheet->forceFill(['status' => 'locked'])->save();

        return response()->json([
            'success' => true,
            'message' => 'Đã chốt bảng lương',
            'data' => $payrollSheet->fresh(),
        ]);
    }

    public function markPaid(PayrollSheet $payrollSheet)
    {
        if (!in_array($payrollSheet->status, ['locked', 'paid'], true)) {
            return response()->json([
                'success' => false,
                'message' => 'Bảng lương phải ở trạng thái locked trước khi chi trả',
            ], 422);
        }

        $payrollSheet->forceFill(['status' => 'paid'])->save();

        return response()->json([
            'success' => true,
            'message' => 'Đã đánh dấu đã chi trả',
            'data' => $payrollSheet->fresh(),
        ]);
    }
}
