<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Purchase;
use App\Models\CashFlow;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\DB;

class SupplierController extends Controller
{
    public function index(Request $request)
    {
        $search = $request->input('search');

        $query = Customer::where('is_supplier', true);

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('code', 'like', "%{$search}%")
                    ->orWhere('name', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        if ($request->filled('customer_group')) {
            $query->where('customer_group', $request->customer_group);
        }

        if ($request->filled('date_filter')) {
            switch ($request->date_filter) {
                case 'today':
                    $query->whereDate('created_at', now()->today());
                    break;
                case 'this_month':
                    $query->whereMonth('created_at', now()->month)
                        ->whereYear('created_at', now()->year);
                    break;
            }
        }

        if ($request->filled('partner_type')) {
            if ($request->partner_type === 'supplier_only') {
                $query->where('is_customer', false);
            } elseif ($request->partner_type === 'both') {
                $query->where('is_customer', true);
            }
        }

        $suppliers = $query->latest()->paginate(50)->withQueryString();

        // Recalculate supplier_debt_amount from actual purchase data
        $supplierIds = $suppliers->pluck('id');
        $debts = Purchase::whereIn('supplier_id', $supplierIds)
            ->where('status', 'completed')
            ->groupBy('supplier_id')
            ->select('supplier_id', DB::raw('SUM(debt_amount) as real_debt'), DB::raw('SUM(total_amount) as real_total'))
            ->pluck('real_debt', 'supplier_id');
        $totals = Purchase::whereIn('supplier_id', $supplierIds)
            ->where('status', 'completed')
            ->groupBy('supplier_id')
            ->select('supplier_id', DB::raw('SUM(total_amount) as real_total'))
            ->pluck('real_total', 'supplier_id');

        $suppliers->getCollection()->transform(function ($s) use ($debts, $totals) {
            $s->supplier_debt_amount = $debts[$s->id] ?? 0;
            $s->total_bought = $totals[$s->id] ?? 0;
            return $s;
        });

        // Summary totals
        $summary = [
            'total_debt' => Purchase::where('status', 'completed')
                ->whereHas('supplier', fn($q) => $q->where('is_supplier', true))
                ->sum('debt_amount'),
            'total_bought' => Purchase::where('status', 'completed')
                ->whereHas('supplier', fn($q) => $q->where('is_supplier', true))
                ->sum('total_amount'),
        ];

        $groups = Customer::where('is_supplier', true)->whereNotNull('customer_group')->distinct()->pluck('customer_group');

        return Inertia::render('Suppliers/Index', [
            'suppliers' => $suppliers,
            'groups' => $groups,
            'filters' => $request->only(['search', 'customer_group', 'date_filter', 'partner_type']),
            'summary' => $summary,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'nullable|string|max:255|unique:customers,code',
            'phone' => 'nullable|string|max:255',
            'email' => 'nullable|email|max:255',
            'address' => 'nullable|string',
            'customer_group' => 'nullable|string',
            'note' => 'nullable|string',
            'is_customer' => 'boolean',
        ]);

        if (empty($validated['code'])) {
            $validated['code'] = 'NCC' . time() . rand(10, 99);
        }

        $validated['is_supplier'] = true;
        // If the toggle 'is_customer' is false, it means they are only a supplier.
        $validated['is_customer'] = $request->input('is_customer', false);

        Customer::create($validated);

        return redirect()->route('suppliers.index')->with('success', 'Tạo nhà cung cấp thành công.');
    }

    public function quickStore(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'phone' => 'nullable|string|max:255',
            'email' => 'nullable|email|max:255',
            'address' => 'nullable|string',
        ]);

        $validated['code'] = 'NCC' . time() . rand(10, 99);
        $validated['is_supplier'] = true;
        $validated['is_customer'] = false;

        $supplier = Customer::create($validated);

        return response()->json(['success' => true, 'supplier' => $supplier]);
    }

    public function export(Request $request)
    {
        $suppliers = Customer::where('is_supplier', true)
            ->when($request->search, fn($q, $s) => $q->where('name', 'LIKE', "%{$s}%")->orWhere('code', 'LIKE', "%{$s}%")->orWhere('phone', 'LIKE', "%{$s}%"))
            ->latest()->get();

        return \App\Services\CsvService::export(
            ['Mã NCC', 'Tên NCC', 'Điện thoại', 'Email', 'Địa chỉ', 'Phường/Xã', 'Quận/Huyện', 'Tỉnh/TP', 'Công nợ NCC', 'Ghi chú'],
            $suppliers->map(fn($s) => [$s->code, $s->name, $s->phone, $s->email, $s->address, $s->ward, $s->district, $s->city, $s->supplier_debt_amount, $s->note]),
            'nha_cung_cap.csv'
        );
    }

    public function import(Request $request)
    {
        [$headers, $rows] = \App\Services\CsvService::parse($request);
        $count = 0;
        foreach ($rows as $row) {
            if (count($row) < 2 || empty(trim($row[1] ?? ''))) continue;
            Customer::updateOrCreate(
                ['code' => trim($row[0])],
                ['name' => trim($row[1]), 'phone' => trim($row[2] ?? ''), 'email' => trim($row[3] ?? ''), 'address' => trim($row[4] ?? ''), 'ward' => trim($row[5] ?? ''), 'district' => trim($row[6] ?? ''), 'city' => trim($row[7] ?? ''), 'note' => trim($row[9] ?? ''), 'is_supplier' => true]
            );
            $count++;
        }
        return back()->with('success', "Đã nhập {$count} nhà cung cấp từ file.");
    }

    /**
     * Lịch sử nhập / trả hàng của nhà cung cấp
     */
    public function purchaseHistory(Request $request, $id)
    {
        $supplier = Customer::where('is_supplier', true)->findOrFail($id);

        $query = Purchase::where('supplier_id', $supplier->id)
            ->with(['user:id,name'])
            ->latest('purchase_date');

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $purchases = $query->paginate(20);

        return response()->json($purchases);
    }

    /**
     * Chi tiết công nợ nhà cung cấp
     */
    public function debtDetails(Request $request, $id)
    {
        $supplier = Customer::where('is_supplier', true)->findOrFail($id);

        $query = Purchase::where('supplier_id', $supplier->id)
            ->where('status', 'completed')
            ->latest('purchase_date');

        $filter = $request->input('transaction_type', 'all');

        if ($filter === 'debt_only') {
            $query->where('debt_amount', '>', 0);
        } elseif ($filter === 'paid') {
            $query->where('debt_amount', 0);
        }

        $purchases = $query->get()->map(function ($p) {
            return [
                'id'         => $p->id,
                'code'       => $p->code,
                'date'       => $p->purchase_date?->format('d/m/Y H:i') ?? $p->created_at->format('d/m/Y H:i'),
                'type'       => 'Nhập hàng',
                'total'      => (float) $p->total_amount,
                'paid'       => (float) $p->paid_amount,
                'debt'       => (float) $p->debt_amount,
            ];
        });

        // Tổng nợ hiện tại
        $totalDebt = Purchase::where('supplier_id', $supplier->id)
            ->where('status', 'completed')
            ->sum('debt_amount');

        return response()->json([
            'items'      => $purchases,
            'total_debt' => (float) $totalDebt,
        ]);
    }

    /**
     * Thanh toán công nợ cho phiếu nhập
     */
    public function makePayment(Request $request, $id)
    {
        $request->validate([
            'purchase_id'    => 'required|exists:purchases,id',
            'amount'         => 'required|numeric|min:1',
            'payment_method' => 'nullable|string',
            'note'           => 'nullable|string',
        ]);

        $supplier = Customer::where('is_supplier', true)->findOrFail($id);
        $purchase = Purchase::where('supplier_id', $supplier->id)->findOrFail($request->purchase_id);

        $amount = min($request->amount, $purchase->debt_amount);

        DB::transaction(function () use ($purchase, $amount, $request, $supplier) {
            $purchase->paid_amount = (float) $purchase->paid_amount + $amount;
            $purchase->debt_amount = max(0, (float) $purchase->total_amount - (float) $purchase->paid_amount);
            $purchase->save();

            // Cập nhật tổng nợ NCC
            $supplier->supplier_debt_amount = Purchase::where('supplier_id', $supplier->id)
                ->where('status', 'completed')
                ->sum('debt_amount');
            $supplier->save();

            // Ghi cash flow nếu model tồn tại
            try {
                CashFlow::create([
                    'type'           => 'expense',
                    'amount'         => $amount,
                    'description'    => "Thanh toán NCC {$supplier->name} - Phiếu {$purchase->code}",
                    'reference_type' => 'purchase_payment',
                    'reference_id'   => $purchase->id,
                    'user_id'        => auth()->id(),
                    'branch_id'      => auth()->user()->branch_id,
                ]);
            } catch (\Throwable $e) {
                // CashFlow table might not exist
            }
        });

        return response()->json([
            'success' => true,
            'message' => 'Đã thanh toán ' . number_format($amount) . 'đ cho phiếu ' . $purchase->code,
            'purchase' => $purchase->fresh(),
        ]);
    }

    /**
     * Điều chỉnh công nợ
     */
    public function adjustDebt(Request $request, $id)
    {
        $request->validate([
            'purchase_id' => 'required|exists:purchases,id',
            'new_debt'    => 'required|numeric|min:0',
            'reason'      => 'nullable|string',
        ]);

        $supplier = Customer::where('is_supplier', true)->findOrFail($id);
        $purchase = Purchase::where('supplier_id', $supplier->id)->findOrFail($request->purchase_id);

        DB::transaction(function () use ($purchase, $request, $supplier) {
            $purchase->debt_amount = $request->new_debt;
            $purchase->paid_amount = (float) $purchase->total_amount - $request->new_debt;
            $purchase->save();

            $supplier->supplier_debt_amount = Purchase::where('supplier_id', $supplier->id)
                ->where('status', 'completed')
                ->sum('debt_amount');
            $supplier->save();
        });

        return response()->json([
            'success' => true,
            'message' => 'Đã điều chỉnh công nợ phiếu ' . $purchase->code,
            'purchase' => $purchase->fresh(),
        ]);
    }
}
