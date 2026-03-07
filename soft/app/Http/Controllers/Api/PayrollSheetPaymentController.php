<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PayrollSheet;
use App\Models\PayrollSheetItem;
use App\Models\PayrollSheetPayment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PayrollSheetPaymentController extends Controller
{
    public function index(Request $request)
    {
        $query = PayrollSheetPayment::query()->with([
            'employee:id,code,name',
            'creator:id,name',
        ]);

        if ($request->filled('payroll_sheet_id')) {
            $query->where('payroll_sheet_id', $request->integer('payroll_sheet_id'));
        }

        if ($request->filled('employee_id')) {
            $query->where('employee_id', $request->integer('employee_id'));
        }

        if ($request->filled('from')) {
            $query->whereDate('paid_at', '>=', $request->date('from'));
        }

        if ($request->filled('to')) {
            $query->whereDate('paid_at', '<=', $request->date('to'));
        }

        $perPage = (int) $request->get('per_page', 50);
        $payments = $query->orderByDesc('paid_at')->orderByDesc('id')->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $payments->items(),
            'pagination' => [
                'current_page' => $payments->currentPage(),
                'last_page' => $payments->lastPage(),
                'per_page' => $payments->perPage(),
                'total' => $payments->total(),
                'from' => $payments->firstItem(),
                'to' => $payments->lastItem(),
            ],
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'payroll_sheet_id' => ['required', 'integer', 'exists:payroll_sheets,id'],
            'payment_method' => ['required', 'string', 'max:50'],
            'paid_at' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],

            // bulk items
            'items' => ['required', 'array', 'min:1'],
            'items.*.payroll_sheet_item_id' => ['required', 'integer', 'exists:payroll_sheet_items,id'],
            'items.*.amount' => ['required', 'numeric', 'min:0.01'],
        ]);

        $userId = $request->user()?->id;

        return DB::transaction(function () use ($data, $userId) {
            /** @var PayrollSheet $sheet */
            $sheet = PayrollSheet::query()->lockForUpdate()->findOrFail($data['payroll_sheet_id']);

            if (!in_array($sheet->status, ['locked', 'paid'], true)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Bảng lương phải ở trạng thái đã chốt (locked) trước khi chi trả',
                ], 422);
            }

            $createdPayments = [];

            foreach ($data['items'] as $it) {
                /** @var PayrollSheetItem $item */
                $item = PayrollSheetItem::query()->lockForUpdate()->findOrFail($it['payroll_sheet_item_id']);

                if ((int) $item->payroll_sheet_id !== (int) $sheet->id) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Có phiếu lương không thuộc bảng lương hiện tại',
                    ], 422);
                }

                $net = (float) ($item->net_salary ?? 0);
                $paid = (float) ($item->paid_amount ?? 0);
                $remaining = max(0, $net - $paid);
                $amount = (float) ($it['amount'] ?? 0);

                if ($amount <= 0) {
                    continue;
                }

                if ($amount - $remaining > 0.00001) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Số tiền chi vượt quá số còn cần trả',
                    ], 422);
                }

                $payment = PayrollSheetPayment::create([
                    'payroll_sheet_id' => $sheet->id,
                    'payroll_sheet_item_id' => $item->id,
                    'employee_id' => $item->employee_id,
                    'amount' => $amount,
                    'payment_method' => $data['payment_method'],
                    'status' => 'paid',
                    'paid_at' => $data['paid_at'] ?? now(),
                    'created_by' => $userId,
                    'notes' => $data['notes'] ?? null,
                ]);

                $payment->forceFill([
                    'code' => $payment->code ?: ('PCL' . str_pad((string) $payment->id, 6, '0', STR_PAD_LEFT)),
                ])->save();

                $item->forceFill([
                    'paid_amount' => $paid + $amount,
                ])->save();

                $createdPayments[] = $payment;
            }

            // Mark sheet paid if fully paid
            $totals = PayrollSheetItem::query()
                ->where('payroll_sheet_id', $sheet->id)
                ->selectRaw('COALESCE(SUM(net_salary),0) as total_net, COALESCE(SUM(paid_amount),0) as total_paid')
                ->first();
            $totalNet = (float) ($totals->total_net ?? 0);
            $totalPaid = (float) ($totals->total_paid ?? 0);
            if ($totalNet > 0 && ($totalNet - $totalPaid) <= 0.00001) {
                $sheet->forceFill(['status' => 'paid'])->save();
            }

            return response()->json([
                'success' => true,
                'message' => 'Đã ghi nhận chi trả lương',
                'data' => [
                    'sheet' => $sheet->fresh(),
                    'payments' => $createdPayments,
                ],
            ]);
        });
    }
}
