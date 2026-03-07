<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CustomerDebt;
use App\Models\Customer;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\Rule;
use Carbon\Carbon;

class CustomerDebtController extends Controller
{
    /**
     * Display a listing of customer debts
     */
    public function index(Request $request): JsonResponse
    {
        $query = CustomerDebt::query()
            ->withCustomer()
            ->withOrder()
            ->withCreator()
            ->latest();

        // Filter by customer
        if ($request->filled('customer_id')) {
            $query->byCustomer($request->customer_id);
        }

        // Filter by customer name
        if ($request->filled('customer_name')) {
            $query->whereHas('customer', function ($q) use ($request) {
                $q->where('name', 'like', '%' . $request->customer_name . '%');
            });
        }

        // Filter by date range
        if ($request->filled('start_date') && $request->filled('end_date')) {
            $query->byDateRange($request->start_date, $request->end_date);
        }

        // Filter by type (debt/payment)
        if ($request->filled('type')) {
            $query->byType($request->type);
        }

        // Filter by ref_code
        if ($request->filled('ref_code')) {
            $query->where('ref_code', 'like', '%' . $request->ref_code . '%');
        }

        // Filter by amount range
        if ($request->filled('min_amount')) {
            $query->where('amount', '>=', $request->min_amount);
        }
        if ($request->filled('max_amount')) {
            $query->where('amount', '<=', $request->max_amount);
        }

        $perPage = $request->get('per_page', 15);
        $debts = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $debts
        ]);
    }

    /**
     * Store a newly created customer debt
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'customer_id' => 'required|exists:customers,id',
            'order_id' => 'nullable|exists:orders,id',
            'ref_code' => 'nullable|string|max:255',
            'amount' => 'required|numeric',
            'note' => 'nullable|string',
            'recorded_at' => 'nullable|date'
        ]);

        $validated['created_by'] = auth()->id();
        $validated['recorded_at'] = $validated['recorded_at'] ?? now();

        $debt = CustomerDebt::create($validated);
        $debt->load(['customer', 'order', 'creator']);

        return response()->json([
            'success' => true,
            'message' => 'Customer debt created successfully',
            'data' => $debt
        ], 201);
    }

    /**
     * Display the specified customer debt
     */
    public function show(CustomerDebt $customerDebt): JsonResponse
    {
        $customerDebt->load(['customer', 'order', 'creator']);

        return response()->json([
            'success' => true,
            'data' => $customerDebt
        ]);
    }

    /**
     * Update the specified customer debt
     */
    public function update(Request $request, CustomerDebt $customerDebt): JsonResponse
    {
        $validated = $request->validate([
            'customer_id' => 'required|exists:customers,id',
            'order_id' => 'nullable|exists:orders,id',
            'ref_code' => 'nullable|string|max:255',
            'amount' => 'required|numeric',
            'note' => 'nullable|string',
            'recorded_at' => 'required|date'
        ]);

        $customerDebt->update($validated);
        $customerDebt->load(['customer', 'order', 'creator']);

        return response()->json([
            'success' => true,
            'message' => 'Customer debt updated successfully',
            'data' => $customerDebt
        ]);
    }

    /**
     * Remove the specified customer debt
     */
    public function destroy(CustomerDebt $customerDebt): JsonResponse
    {
        $customerDebt->delete();

        return response()->json([
            'success' => true,
            'message' => 'Customer debt deleted successfully'
        ]);
    }

    /**
     * Bulk delete customer debts
     */
    public function bulkDelete(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'exists:customer_debts,id'
        ]);

        $deletedCount = CustomerDebt::whereIn('id', $validated['ids'])->delete();

        return response()->json([
            'success' => true,
            'message' => "Deleted {$deletedCount} customer debt records"
        ]);
    }

    /**
     * Create payment transaction
     */
    public function payment(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'customer_id' => 'required|exists:customers,id',
            'amount' => 'required|numeric|min:0.01',
            'note' => 'nullable|string',
            'ref_code' => 'nullable|string|max:255'
        ]);

        $payment = CustomerDebt::createPayment(
            $validated['customer_id'],
            $validated['amount'],
            $validated['note'] ?? 'Thanh toán',
            $validated['ref_code']
        );

        $payment->load(['customer', 'creator']);

        return response()->json([
            'success' => true,
            'message' => 'Payment recorded successfully',
            'data' => $payment
        ], 201);
    }

    /**
     * Create adjustment transaction
     */
    public function adjustment(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'customer_id' => 'required|exists:customers,id',
            'amount' => 'required|numeric',
            'note' => 'required|string',
            'ref_code' => 'nullable|string|max:255'
        ]);

        $adjustment = CustomerDebt::createAdjustment(
            $validated['customer_id'],
            $validated['amount'],
            $validated['note'],
            $validated['ref_code']
        );

        $adjustment->load(['customer', 'creator']);

        return response()->json([
            'success' => true,
            'message' => 'Debt adjustment recorded successfully',
            'data' => $adjustment
        ], 201);
    }

    /**
     * Get debt summary for a customer
     */
    public function customerSummary(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'customer_id' => 'required|exists:customers,id'
        ]);

        $summary = CustomerDebt::getCustomerDebtSummary($validated['customer_id']);
        $customer = Customer::find($validated['customer_id']);

        return response()->json([
            'success' => true,
            'data' => [
                'customer' => $customer,
                'summary' => $summary
            ]
        ]);
    }

    /**
     * Get debt timeline for a customer
     */
    public function customerTimeline(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'customer_id' => 'required|exists:customers,id',
            'limit' => 'nullable|integer|min:1|max:100'
        ]);

        $timeline = CustomerDebt::where('customer_id', $validated['customer_id'])
            ->withOrder()
            ->withCreator()
            ->latest()
            ->limit($validated['limit'] ?? 50)
            ->get();

        return response()->json([
            'success' => true,
            'data' => $timeline
        ]);
    }

    /**
     * Get statistics
     */
    public function statistics(Request $request): JsonResponse
    {
        $startDate = $request->get('start_date', now()->startOfMonth());
        $endDate = $request->get('end_date', now()->endOfMonth());

        $stats = CustomerDebt::selectRaw('
            COUNT(*) as total_transactions,
            SUM(CASE WHEN amount > 0 THEN amount ELSE 0 END) as total_debt_amount,
            SUM(CASE WHEN amount < 0 THEN ABS(amount) ELSE 0 END) as total_payment_amount,
            COUNT(CASE WHEN amount > 0 THEN 1 END) as debt_transactions,
            COUNT(CASE WHEN amount < 0 THEN 1 END) as payment_transactions,
            COUNT(DISTINCT customer_id) as unique_customers
        ')
        ->byDateRange($startDate, $endDate)
        ->first();

        $topDebtors = CustomerDebt::getTopDebtors(5);

        return response()->json([
            'success' => true,
            'data' => [
                'period' => [
                    'start_date' => $startDate,
                    'end_date' => $endDate
                ],
                'statistics' => $stats,
                'top_debtors' => $topDebtors
            ]
        ]);
    }

    /**
     * Export customer debt data
     */
    public function export(Request $request): JsonResponse
    {
        $query = CustomerDebt::query()
            ->withCustomer()
            ->withOrder()
            ->withCreator()
            ->latest();

        // Apply same filters as index
        if ($request->filled('customer_id')) {
            $query->byCustomer($request->customer_id);
        }

        if ($request->filled('start_date') && $request->filled('end_date')) {
            $query->byDateRange($request->start_date, $request->end_date);
        }

        if ($request->filled('type')) {
            $query->byType($request->type);
        }

        $debts = $query->get();

        // Transform for export
        $exportData = $debts->map(function ($debt) {
            return [
                'Mã tham chiếu' => $debt->ref_code,
                'Khách hàng' => $debt->customer->name,
                'Mã khách hàng' => $debt->customer->code,
                'Số tiền' => $debt->amount,
                'Tổng nợ' => $debt->debt_total,
                'Loại' => $debt->isDebt() ? 'Nợ' : 'Thanh toán',
                'Ghi chú' => $debt->note,
                'Ngày ghi nhận' => $debt->recorded_at->format('d/m/Y H:i'),
                'Người tạo' => $debt->creator->name ?? '',
                'Ngày tạo' => $debt->created_at->format('d/m/Y H:i')
            ];
        });

        return response()->json([
            'success' => true,
            'message' => 'Export data prepared',
            'data' => $exportData,
            'total_records' => $exportData->count()
        ]);
    }

    /**
     * Import customer debt data
     */
    public function import(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'data' => 'required|array',
            'data.*.customer_id' => 'required|exists:customers,id',
            'data.*.amount' => 'required|numeric',
            'data.*.ref_code' => 'nullable|string|max:255',
            'data.*.note' => 'nullable|string',
            'data.*.recorded_at' => 'nullable|date'
        ]);

        $imported = 0;
        $errors = [];

        foreach ($validated['data'] as $index => $debtData) {
            try {
                $debtData['created_by'] = auth()->id();
                $debtData['recorded_at'] = $debtData['recorded_at'] ?? now();
                
                CustomerDebt::create($debtData);
                $imported++;
            } catch (\Exception $e) {
                $errors[] = "Row {$index}: " . $e->getMessage();
            }
        }

        return response()->json([
            'success' => count($errors) === 0,
            'message' => "Imported {$imported} records",
            'imported_count' => $imported,
            'error_count' => count($errors),
            'errors' => $errors
        ], count($errors) > 0 ? 422 : 201);
    }

    /**
     * Get customers with debt balance
     */
    public function customersWithDebt(Request $request): JsonResponse
    {
        $customers = Customer::whereHas('customerDebts')
            ->withSum('customerDebts', 'amount')
            ->having('customer_debts_sum_amount', '>', 0)
            ->orderBy('customer_debts_sum_amount', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $customers
        ]);
    }
}