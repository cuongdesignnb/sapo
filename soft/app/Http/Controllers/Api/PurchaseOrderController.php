<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\Supplier;
use App\Models\Warehouse;
use App\Models\Product;
use App\Models\SupplierDebt;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;

class PurchaseOrderController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        try {
            // Cho phép lọc theo loại: planned (order-only), actual (đã convert), all
            $type = $request->get('type', 'planned'); // planned|actual|all
            $query = PurchaseOrder::query();
            if ($type === 'planned') {
                $query->where('is_order_only', true);
            } elseif ($type === 'actual') {
                $query->where('is_order_only', false);
            }
            $query->with(['supplier:id,code,name', 'warehouse:id,code,name', 'creator:id,name']);

            // Filter by supplier
            if ($request->filled('supplier_id')) {
                $query->where('supplier_id', $request->supplier_id);
            }

            // Filter by warehouse
            if ($request->filled('warehouse_id')) {
                $query->where('warehouse_id', $request->warehouse_id);
            }

            // Filter by status (supports single, comma-separated, or array)
            if ($request->filled('status')) {
                $statusParam = $request->input('status');
                if (is_array($statusParam)) {
                    $query->whereIn('status', $statusParam);
                } else {
                    $parts = array_values(array_filter(array_map('trim', explode(',', (string)$statusParam))));
                    if (count($parts) > 1) {
                        $query->whereIn('status', $parts);
                    } else {
                        $query->where('status', $statusParam);
                    }
                }
            }

            // Search
            if ($request->filled('search')) {
                $search = $request->search;
                $query->where(function($q) use ($search) {
                    $q->where('code', 'LIKE', "%{$search}%")
                      ->orWhereHas('supplier', function($q) use ($search) {
                          $q->where('name', 'LIKE', "%{$search}%");
                      });
                });
            }

            // Date filters
            if ($request->filled('date_from')) {
                $query->whereDate('created_at', '>=', $request->date_from);
            }
            if ($request->filled('date_to')) {
                $query->whereDate('created_at', '<=', $request->date_to);
            }

            $orders = $query->orderBy('created_at', 'desc')
                          ->paginate($request->get('per_page', 20));

            // Add computed payment_status to each order
            $orders->getCollection()->transform(function ($order) {
                if ($order->is_order_only) {
                    $order->payment_status = 'planned';
                    $order->payment_progress = 0;
                } else {
                    $order->payment_status = $this->getPaymentStatus($order);
                    $order->payment_progress = $order->total > 0 ? round(($order->paid / $order->total) * 100, 2) : 0;
                }
                $order->is_convertible = $order->is_convertible; // accessor
                return $order;
            });

            return response()->json([
                'success' => true,
                'data' => $orders->items(),
                'pagination' => [
                    'current_page' => $orders->currentPage(),
                    'last_page' => $orders->lastPage(),
                    'per_page' => $orders->perPage(),
                    'total' => $orders->total(),
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Lỗi khi tải danh sách đơn đặt hàng: ' . $e->getMessage()
            ], 500);
        }
    }

    public function store(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'supplier_id' => 'required|exists:suppliers,id',
                'warehouse_id' => 'required|exists:warehouses,id',
                'expected_at' => 'nullable|date',
                'items' => 'required|array|min:1',
                'items.*.product_id' => 'required|exists:products,id',
                'items.*.quantity' => 'required|integer|min:1',
                'items.*.price' => 'required|numeric|min:0',
                'note' => 'nullable|string',
                'delivery_address' => 'nullable|string',
                'delivery_contact' => 'nullable|string',
                'delivery_phone' => 'nullable|string'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Dữ liệu không hợp lệ',
                    'errors' => $validator->errors()
                ], 422);
            }

            DB::beginTransaction();

            // Generate code
            $code = $this->generatePurchaseOrderCode();

            // Tính tổng dự kiến
            $totalAmount = 0;
            foreach ($request->items as $itemData) {
                $totalAmount += $itemData['quantity'] * $itemData['price'];
            }

            $purchaseOrder = PurchaseOrder::create([
                'code' => $this->generateCode(),
                'supplier_id' => $request->supplier_id,
                'warehouse_id' => $request->warehouse_id,
                'expected_at' => $request->expected_at,
                'delivery_contact' => $request->delivery_contact,
                'delivery_phone' => $request->delivery_phone,
                'delivery_address' => $request->delivery_address,
                'note' => $request->note,
                'total' => $totalAmount,
                // Đơn đặt hàng không thanh toán thực tế
                'paid' => 0,
                'need_pay' => 0,
                'status' => 'draft',
                'is_order_only' => true,
                'created_by' => auth()->id()
            ]);

            // Create items
            foreach ($request->items as $item) {
                $itemTotal = $item['quantity'] * $item['price'];
                
                PurchaseOrderItem::create([
                    'purchase_order_id' => $purchaseOrder->id,
                    'product_id' => $item['product_id'],
                    'quantity' => $item['quantity'],
                    'price' => $item['price'],
                    'total' => $itemTotal,
                    'remaining_quantity' => $item['quantity'],
                    'note' => $item['note'] ?? null,
                ]);
            }
            
            DB::commit();

            $purchaseOrder->load(['supplier', 'warehouse', 'items.product']);

            return response()->json([
                'success' => true,
                'message' => $request->submit_for_approval 
                    ? 'Tạo đơn đặt hàng thành công và đã gửi duyệt' 
                    : 'Tạo đơn đặt hàng thành công',
                'data' => $purchaseOrder
            ], 201);

        } catch (\Exception $e) {
            DB::rollback();
            return response()->json([
                'success' => false,
                'message' => 'Lỗi khi tạo đơn đặt hàng: ' . $e->getMessage()
            ], 500);
        }
    }

    public function show($id): JsonResponse
    {
        try {
            $purchaseOrder = PurchaseOrder::with([
                'supplier:id,code,name,phone,email',
                'warehouse:id,code,name,address',
                'creator:id,name',
                'items.product:id,sku,name,retail_price',
                'receipts' => function($q) {
                    $q->orderBy('created_at', 'desc');
                }
            ])->findOrFail($id);

            // Add computed fields
            if ($purchaseOrder->is_order_only) {
                $purchaseOrder->payment_status = 'planned';
                $purchaseOrder->payment_progress = 0;
                $purchaseOrder->is_convertible = $purchaseOrder->is_convertible;
            } else {
                $purchaseOrder->payment_status = $this->getPaymentStatus($purchaseOrder);
                $purchaseOrder->payment_progress = $purchaseOrder->total > 0 ? round(($purchaseOrder->paid / $purchaseOrder->total) * 100, 2) : 0;
            }

            return response()->json([
                'success' => true,
                'data' => $purchaseOrder
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Lỗi khi tải chi tiết đơn đặt hàng: ' . $e->getMessage()
            ], 500);
        }
    }

    public function update(Request $request, $id): JsonResponse
    {
        try {
            $purchaseOrder = PurchaseOrder::findOrFail($id);

            if (!in_array($purchaseOrder->status, ['draft', 'pending'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Không thể chỉnh sửa đơn hàng đã được phê duyệt'
                ], 422);
            }

            $validator = Validator::make($request->all(), [
                'supplier_id' => 'required|exists:suppliers,id',
                'warehouse_id' => 'required|exists:warehouses,id',
                'expected_at' => 'nullable|date',
                'items' => 'required|array|min:1',
                'items.*.product_id' => 'required|exists:products,id',
                'items.*.quantity' => 'required|integer|min:1',
                'items.*.price' => 'required|numeric|min:0',
                'note' => 'nullable|string',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Dữ liệu không hợp lệ',
                    'errors' => $validator->errors()
                ], 422);
            }

            DB::beginTransaction();

            // Calculate new total
            $total = collect($request->items)->sum(function($item) {
                return $item['quantity'] * $item['price'];
            });

            // Update purchase order
            $purchaseOrder->update([
                'supplier_id' => $request->supplier_id,
                'warehouse_id' => $request->warehouse_id,
                'expected_at' => $request->expected_at,
                'total' => $total,
                'need_pay' => $total - $purchaseOrder->paid, // Keep existing paid amount
                'note' => $request->note,
            ]);

            // Delete old items and create new ones
            $purchaseOrder->items()->delete();

            foreach ($request->items as $item) {
                $itemTotal = $item['quantity'] * $item['price'];
                
                PurchaseOrderItem::create([
                    'purchase_order_id' => $purchaseOrder->id,
                    'product_id' => $item['product_id'],
                    'quantity' => $item['quantity'],
                    'price' => $item['price'],
                    'total' => $itemTotal,
                    'remaining_quantity' => $item['quantity'],
                    'note' => $item['note'] ?? null,
                ]);
            }

            DB::commit();

            $purchaseOrder->load(['supplier', 'warehouse', 'items.product']);

            return response()->json([
                'success' => true,
                'message' => 'Cập nhật đơn đặt hàng thành công',
                'data' => $purchaseOrder
            ]);

        } catch (\Exception $e) {
            DB::rollback();
            return response()->json([
                'success' => false,
                'message' => 'Lỗi khi cập nhật đơn đặt hàng: ' . $e->getMessage()
            ], 500);
        }
    }

    public function updateStatus(Request $request, $id): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'status' => 'required|in:pending,approved,ordered,partial_received,received,completed,cancelled',
                'note' => 'nullable|string'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Dữ liệu không hợp lệ',
                    'errors' => $validator->errors()
                ], 422);
            }

            $purchaseOrder = PurchaseOrder::findOrFail($id);
            $oldStatus = $purchaseOrder->status;
            $newStatus = $request->status;

            DB::beginTransaction();

            // Update status
            $purchaseOrder->update([
                'status' => $newStatus,
                'internal_note' => $request->note
            ]);

            // AUTO DEBT MANAGEMENT (bỏ qua đối với đơn đặt hàng)
            if (!$purchaseOrder->is_order_only) {
                if ($newStatus === 'approved' && $oldStatus !== 'approved') {
                    $this->createSupplierDebt($purchaseOrder);
                }
                if ($newStatus === 'cancelled' && $oldStatus === 'approved') {
                    $this->cancelSupplierDebt($purchaseOrder);
                }
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Cập nhật trạng thái thành công',
                'data' => $purchaseOrder
            ]);

        } catch (\Exception $e) {
            DB::rollback();
            return response()->json([
                'success' => false,
                'message' => 'Lỗi khi cập nhật trạng thái: ' . $e->getMessage()
            ], 500);
        }
    }

    public function submitForApproval($id): JsonResponse
    {
        try {
            $purchaseOrder = PurchaseOrder::findOrFail($id);
            
            if ($purchaseOrder->status !== 'draft') {
                return response()->json([
                    'success' => false,
                    'message' => 'Chỉ có thể gửi duyệt đơn hàng ở trạng thái nháp'
                ], 400);
            }
            
            $purchaseOrder->update(['status' => 'pending']);
            
            return response()->json([
                'success' => true,
                'message' => 'Gửi đơn hàng duyệt thành công',
                'notice' => 'Đây là đơn đặt hàng kế hoạch. Sau khi được duyệt bạn có thể chuyển thành đơn thực tế để nhập kho.'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Lỗi khi gửi duyệt: ' . $e->getMessage()
            ], 500);
        }
    }

    public function destroy($id): JsonResponse
    {
        try {
            $purchaseOrder = PurchaseOrder::findOrFail($id);

            if (!in_array($purchaseOrder->status, ['draft', 'cancelled'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Chỉ có thể xóa đơn hàng ở trạng thái nháp hoặc đã hủy'
                ], 422);
            }

            $purchaseOrder->delete();

            return response()->json([
                'success' => true,
                'message' => 'Xóa đơn đặt hàng thành công'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Lỗi khi xóa đơn đặt hàng: ' . $e->getMessage()
            ], 500);
        }
    }

    // ===================================================
    // HELPER METHODS
    // ===================================================
    private function generateCode(): string
{
    $prefix = 'PON' . date('ymd');
    $lastOrder = PurchaseOrder::where('code', 'LIKE', $prefix . '%')
        ->orderBy('id', 'desc')
        ->first();

    if ($lastOrder) {
        $lastNumber = intval(substr($lastOrder->code, -5));
        $newNumber = $lastNumber + 1;
    } else {
        $newNumber = 1;
    }

    return $prefix . str_pad($newNumber, 5, '0', STR_PAD_LEFT);
}
    private function generatePurchaseOrderCode(): string
    {
        $prefix = 'PON' . date('ymd');
        $lastOrder = PurchaseOrder::where('code', 'LIKE', $prefix . '%')
            ->orderBy('id', 'desc')
            ->first();

        if ($lastOrder) {
            $lastNumber = intval(substr($lastOrder->code, -5));
            $newNumber = $lastNumber + 1;
        } else {
            $newNumber = 1;
        }

        return $prefix . str_pad($newNumber, 5, '0', STR_PAD_LEFT);
    }

    private function getPaymentStatus(PurchaseOrder $order): string
    {
        if ($order->is_order_only) return 'planned';
        if ($order->paid <= 0) return 'unpaid';
        if ($order->paid >= $order->total) return 'paid';
        return 'partial';
    }

    private function createSupplierDebt(PurchaseOrder $purchaseOrder): void
{
    try {
        // Only create debt for unpaid amount
        $debtAmount = $purchaseOrder->need_pay;
        
        if ($debtAmount > 0) {
            SupplierDebt::createPurchaseDebt($purchaseOrder, 
                "Công nợ từ đơn hàng {$purchaseOrder->code} (Còn nợ: " . number_format($debtAmount) . ")"
            );
        }

        Log::info("Supplier debt created for Purchase Order: {$purchaseOrder->code}", [
            'purchase_order_id' => $purchaseOrder->id,
            'supplier_id' => $purchaseOrder->supplier_id,
            'total_amount' => $purchaseOrder->total,
            'paid_amount' => $purchaseOrder->paid,
            'debt_amount' => $debtAmount
        ]);

    } catch (\Exception $e) {
        Log::error("Failed to create supplier debt for Purchase Order: {$purchaseOrder->code}", [
            'error' => $e->getMessage(),
            'purchase_order_id' => $purchaseOrder->id
        ]);
        throw $e;
    }
}

    private function cancelSupplierDebt(PurchaseOrder $purchaseOrder): void
    {
        try {
            // Find the original debt
            $debt = SupplierDebt::where('supplier_id', $purchaseOrder->supplier_id)
                              ->where('type', 'purchase')
                              ->where('ref_code', $purchaseOrder->code)
                              ->first();

            if ($debt) {
                // Create adjustment to cancel the debt
                // Create adjustment to cancel the debt
SupplierDebt::createAdjustment(
    supplierId: $purchaseOrder->supplier_id,
    amount: -$debt->amount,
    refCode: $purchaseOrder->code . '-CANCELLED',
    note: "Hủy công nợ do đơn hàng {$purchaseOrder->code} bị hủy"
);

                Log::info("Auto cancelled supplier debt for Purchase Order: {$purchaseOrder->code}", [
                    'purchase_order_id' => $purchaseOrder->id,
                    'supplier_id' => $purchaseOrder->supplier_id,
                    'cancelled_amount' => $debt->amount
                ]);
            }

        } catch (\Exception $e) {
            Log::error("Failed to cancel supplier debt for Purchase Order: {$purchaseOrder->code}", [
                'error' => $e->getMessage(),
                'purchase_order_id' => $purchaseOrder->id
            ]);
            throw $e;
        }
    }

    // ===================================================
    // PART 2: PAYMENT MANAGEMENT METHODS
    // ===================================================

    /**
     * Record payment for Purchase Order
     */
    public function recordPayment(Request $request, $id): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'amount' => 'required|numeric|min:0.01',
                'payment_method' => 'required|in:cash,transfer,bank',
                'reference_number' => 'nullable|string|max:255',
                'payment_date' => 'required|date',
                'note' => 'nullable|string'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Dữ liệu không hợp lệ',
                    'errors' => $validator->errors()
                ], 422);
            }

            $purchaseOrder = PurchaseOrder::findOrFail($id);

            if ($purchaseOrder->is_order_only) {
                return response()->json([
                    'success' => false,
                    'message' => 'Đơn đặt hàng không phát sinh thanh toán. Hãy tạo phiếu nhập để thanh toán.'
                ], 422);
            }

            if (!in_array($purchaseOrder->status, ['approved', 'ordered', 'partial_received', 'received'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Chỉ có thể thanh toán cho đơn hàng đã được duyệt'
                ], 422);
            }

            DB::beginTransaction();

            // Calculate remaining amount to pay
            $remainingAmount = $purchaseOrder->need_pay;

            if ($request->amount > $remainingAmount) {
                return response()->json([
                    'success' => false,
                    'message' => "Số tiền thanh toán vượt quá số tiền còn lại (" . number_format($remainingAmount) . " VNĐ)"
                ], 422);
            }

            // Update purchase order payment fields
            $newPaidAmount = $purchaseOrder->paid + $request->amount;
            $newNeedPayAmount = $purchaseOrder->total - $newPaidAmount;

            $purchaseOrder->update([
                'paid' => $newPaidAmount,
                'need_pay' => $newNeedPayAmount
            ]);

            // Create supplier debt payment record to reduce debt
SupplierDebt::createPayment(
    supplierId: $purchaseOrder->supplier_id,
    amount: $request->amount,
    refCode: $purchaseOrder->code . '-PAY-' . date('ymdHis'),
    purchaseOrderId: $purchaseOrder->id,
    note: "Thanh toán cho đơn hàng {$purchaseOrder->code}" . ($request->note ? " - {$request->note}" : "")
);

            // Log payment activity
            Log::info("Payment recorded for Purchase Order: {$purchaseOrder->code}", [
                'purchase_order_id' => $purchaseOrder->id,
                'amount' => $request->amount,
                'payment_method' => $request->payment_method,
                'paid_total' => $newPaidAmount,
                'remaining' => $newNeedPayAmount
            ]);

            DB::commit();

            $purchaseOrder->load(['supplier', 'warehouse']);

            return response()->json([
                'success' => true,
                'message' => 'Ghi nhận thanh toán thành công',
                'data' => [
                    'purchase_order' => $purchaseOrder,
                    'payment_info' => [
                        'amount_paid' => $request->amount,
                        'total_paid' => $newPaidAmount,
                        'remaining_amount' => $newNeedPayAmount,
                        'payment_progress' => $purchaseOrder->total > 0 ? round(($newPaidAmount / $purchaseOrder->total) * 100, 2) : 0
                    ]
                ]
            ]);

        } catch (\Exception $e) {
            DB::rollback();
            return response()->json([
                'success' => false,
                'message' => 'Lỗi khi ghi nhận thanh toán: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get payment history for Purchase Order
     */
    public function getPaymentHistory($id): JsonResponse
    {
        try {
            $purchaseOrder = PurchaseOrder::with(['supplier:id,name'])->findOrFail($id);

            if ($purchaseOrder->is_order_only) {
                return response()->json([
                    'success' => false,
                    'message' => 'Đơn đặt hàng không có lịch sử thanh toán'
                ], 422);
            }

            // Get payment history from supplier_debts table
            $payments = SupplierDebt::where('supplier_id', $purchaseOrder->supplier_id)
                                  ->where('type', 'payment')
                                  ->where(function($q) use ($purchaseOrder) {
                                      $q->where('ref_code', 'LIKE', $purchaseOrder->code . '-PAY-%')
                                        ->orWhere('note', 'LIKE', '%' . $purchaseOrder->code . '%');
                                  })
                                  ->orderBy('recorded_at', 'desc')
                                  ->get();

            return response()->json([
                'success' => true,
                'data' => [
                    'purchase_order' => [
                        'id' => $purchaseOrder->id,
                        'code' => $purchaseOrder->code,
                        'supplier' => $purchaseOrder->supplier,
                        'total' => $purchaseOrder->total,
                        'paid' => $purchaseOrder->paid,
                        'need_pay' => $purchaseOrder->need_pay,
                        'payment_status' => $this->getPaymentStatus($purchaseOrder),
                        'payment_progress' => $purchaseOrder->total > 0 ? round(($purchaseOrder->paid / $purchaseOrder->total) * 100, 2) : 0
                    ],
                    'payments' => $payments,
                    'summary' => [
                        'total_amount' => $purchaseOrder->total,
                        'paid_amount' => $purchaseOrder->paid,
                        'remaining_amount' => $purchaseOrder->need_pay,
                        'payment_progress' => $purchaseOrder->total > 0 ? round(($purchaseOrder->paid / $purchaseOrder->total) * 100, 2) : 0,
                        'total_payments' => $payments->count()
                    ]
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Lỗi khi tải lịch sử thanh toán: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get Purchase Orders payment overview
     */
    public function getPaymentOverview(Request $request): JsonResponse
    {
        try {
            $query = PurchaseOrder::with(['supplier:id,name', 'warehouse:id,name'])
                                 ->where('is_order_only', false)
                                 ->whereIn('status', ['approved', 'ordered', 'partial_received', 'received', 'completed']);

            // Filter by payment status (computed)
            if ($request->filled('payment_status')) {
                $status = $request->payment_status;
                if ($status === 'unpaid') {
                    $query->where('paid', '<=', 0);
                } elseif ($status === 'paid') {
                    $query->whereColumn('paid', '>=', 'total');
                } elseif ($status === 'partial') {
                    $query->where('paid', '>', 0)->whereColumn('paid', '<', 'total');
                }
            }

            // Filter by supplier
            if ($request->filled('supplier_id')) {
                $query->where('supplier_id', $request->supplier_id);
            }

            // Filter by date range
            if ($request->filled('date_from')) {
                $query->whereDate('created_at', '>=', $request->date_from);
            }
            if ($request->filled('date_to')) {
                $query->whereDate('created_at', '<=', $request->date_to);
            }

            $orders = $query->orderBy('created_at', 'desc')
                          ->paginate($request->get('per_page', 20));

            // Add computed payment_status to each order
            $orders->getCollection()->transform(function ($order) {
                $order->payment_status = $this->getPaymentStatus($order);
                $order->payment_progress = $order->total > 0 ? round(($order->paid / $order->total) * 100, 2) : 0;
                return $order;
            });

            // Calculate summary statistics
            $allOrdersQuery = clone $query;
            $allOrders = $allOrdersQuery->get();
            
            $unpaidCount = $allOrders->where('paid', '<=', 0)->count();
            $paidCount = $allOrders->filter(function($order) { return $order->paid >= $order->total; })->count();
            $partialCount = $allOrders->count() - $unpaidCount - $paidCount;

            $summary = [
                'total_orders' => $allOrders->count(),
                'total_amount' => $allOrders->sum('total'),
                'paid_amount' => $allOrders->sum('paid'),
                'unpaid_amount' => $allOrders->sum('need_pay'),
                'payment_statuses' => [
                    'unpaid' => $unpaidCount,
                    'partial' => $partialCount,
                    'paid' => $paidCount
                ]
            ];

            return response()->json([
                'success' => true,
                'data' => $orders->items(),
                'pagination' => [
                    'current_page' => $orders->currentPage(),
                    'last_page' => $orders->lastPage(),
                    'per_page' => $orders->perPage(),
                    'total' => $orders->total(),
                ],
                'summary' => $summary
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Lỗi khi tải tổng quan thanh toán: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Bulk payment for multiple Purchase Orders
     */
    public function bulkPayment(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'orders' => 'required|array|min:1',
                'orders.*.id' => 'required|exists:purchase_orders,id',
                'orders.*.amount' => 'required|numeric|min:0.01',
                'payment_method' => 'required|in:cash,transfer,bank',
                'reference_number' => 'nullable|string|max:255',
                'payment_date' => 'required|date',
                'note' => 'nullable|string'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Dữ liệu không hợp lệ',
                    'errors' => $validator->errors()
                ], 422);
            }

            DB::beginTransaction();

            $processedOrders = [];
            $totalPaid = 0;

            foreach ($request->orders as $orderData) {
                $purchaseOrder = PurchaseOrder::findOrFail($orderData['id']);

                if ($purchaseOrder->is_order_only) {
                    DB::rollback();
                    return response()->json([
                        'success' => false,
                        'message' => "Đơn {$purchaseOrder->code} là đơn đặt hàng – không thanh toán hàng loạt"
                    ], 422);
                }

                if (!in_array($purchaseOrder->status, ['approved', 'ordered', 'partial_received', 'received'])) {
                    DB::rollback();
                    return response()->json([
                        'success' => false,
                        'message' => "Đơn hàng {$purchaseOrder->code} không thể thanh toán (trạng thái: {$purchaseOrder->status})"
                    ], 422);
                }

                if ($orderData['amount'] > $purchaseOrder->need_pay) {
                    DB::rollback();
                    return response()->json([
                        'success' => false,
                        'message' => "Số tiền thanh toán cho đơn {$purchaseOrder->code} vượt quá số tiền còn lại"
                    ], 422);
                }

                // Update purchase order payment
                $newPaidAmount = $purchaseOrder->paid + $orderData['amount'];
                $newNeedPayAmount = $purchaseOrder->total - $newPaidAmount;

                $purchaseOrder->update([
                    'paid' => $newPaidAmount,
                    'need_pay' => $newNeedPayAmount
                ]);

                // Create supplier debt payment record
                // Create supplier debt payment record
SupplierDebt::createPayment(
    supplierId: $purchaseOrder->supplier_id,
    amount: $orderData['amount'],
    refCode: $purchaseOrder->code . '-BULK-PAY-' . date('ymdHis'),
    purchaseOrderId: $purchaseOrder->id,
    note: "Thanh toán hàng loạt cho đơn {$purchaseOrder->code}" . ($request->note ? " - {$request->note}" : "")
);

                $processedOrders[] = [
                    'id' => $purchaseOrder->id,
                    'code' => $purchaseOrder->code,
                    'amount_paid' => $orderData['amount'],
                    'total_paid' => $newPaidAmount,
                    'remaining' => $newNeedPayAmount
                ];

                $totalPaid += $orderData['amount'];
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => "Thanh toán hàng loạt thành công cho " . count($processedOrders) . " đơn hàng",
                'data' => [
                    'processed_orders' => $processedOrders,
                    'total_paid' => $totalPaid,
                    'payment_info' => [
                        'payment_method' => $request->payment_method,
                        'reference_number' => $request->reference_number,
                        'payment_date' => $request->payment_date
                    ]
                ]
            ]);

        } catch (\Exception $e) {
            DB::rollback();
            return response()->json([
                'success' => false,
                'message' => 'Lỗi khi thanh toán hàng loạt: ' . $e->getMessage()
            ], 500);
        }
    }

    // ===================================================
    // STATISTICS & REPORTS
    // ===================================================

    /**
     * Get purchase orders statistics
     */
    public function getStatistics(Request $request): JsonResponse
    {
        try {
            $query = PurchaseOrder::with(['supplier:id,name']);

            // Filter by date range
            if ($request->filled('date_from')) {
                $query->whereDate('created_at', '>=', $request->date_from);
            }
            if ($request->filled('date_to')) {
                $query->whereDate('created_at', '<=', $request->date_to);
            }

            // Filter by warehouse
            if ($request->filled('warehouse_id')) {
                $query->where('warehouse_id', $request->warehouse_id);
            }

            // Filter by supplier
            if ($request->filled('supplier_id')) {
                $query->where('supplier_id', $request->supplier_id);
            }

            $orders = $query->get();

            $planned = $orders->where('is_order_only', true);
            $actual = $orders->where('is_order_only', false);

            $stats = [
                'overview' => [
                    'total_orders' => $actual->count(),
                    'total_amount' => $actual->sum('total'),
                    'total_paid' => $actual->sum('paid'),
                    'total_unpaid' => $actual->sum('need_pay'),
                    'payment_progress' => $actual->sum('total') > 0 ? round(($actual->sum('paid') / $actual->sum('total')) * 100, 2) : 0
                ],
                'planned_overview' => [
                    'planned_orders' => $planned->count(),
                    'planned_amount' => $planned->sum('total')
                ],
                'by_status' => [
                    'draft' => $planned->where('status', 'draft')->count(),
                    'pending' => $planned->where('status', 'pending')->count(),
                    'approved' => $planned->where('status', 'approved')->count(),
                    'ordered' => $planned->where('status', 'ordered')->count(),
                    'partial_received' => $actual->where('status', 'partial_received')->count(),
                    'received' => $actual->where('status', 'received')->count(),
                    'completed' => $actual->where('status', 'completed')->count(),
                    'cancelled' => $orders->where('status', 'cancelled')->count()
                ],
                'by_payment_status' => [
                    'unpaid' => $actual->where('paid', '<=', 0)->count(),
                    'partial' => $actual->filter(function($order) { return $order->paid > 0 && $order->paid < $order->total; })->count(),
                    'paid' => $actual->filter(function($order) { return $order->paid >= $order->total; })->count(),
                    'planned' => $planned->count()
                ],
                'amounts_by_status' => [
                    'draft' => $planned->where('status', 'draft')->sum('total'),
                    'pending' => $planned->where('status', 'pending')->sum('total'),
                    'approved' => $planned->where('status', 'approved')->sum('total'),
                    'ordered' => $planned->where('status', 'ordered')->sum('total'),
                    'partial_received' => $actual->where('status', 'partial_received')->sum('total'),
                    'received' => $actual->where('status', 'received')->sum('total'),
                    'completed' => $actual->where('status', 'completed')->sum('total'),
                    'cancelled' => $orders->where('status', 'cancelled')->sum('total')
                ]
            ];

            $topSuppliers = $orders->groupBy('supplier_id')
                ->map(function ($supplierOrders) {
                    $supplier = $supplierOrders->first()->supplier;
                    return [
                        'supplier' => $supplier,
                        'order_count' => $supplierOrders->count(),
                        'total_amount' => $supplierOrders->sum('total'),
                        'paid_amount' => $supplierOrders->sum('paid'),
                        'unpaid_amount' => $supplierOrders->sum('need_pay'),
                        'planned_amount' => $supplierOrders->where('is_order_only', true)->sum('total')
                    ];
                })
                ->sortByDesc('total_amount')
                ->take(10)
                ->values();

            return response()->json([
                'success' => true,
                'data' => [
                    'statistics' => $stats,
                    'top_suppliers' => $topSuppliers,
                    'period' => [
                        'from' => $request->date_from ?? $orders->min('created_at'),
                        'to' => $request->date_to ?? $orders->max('created_at')
                    ]
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Lỗi khi tải thống kê: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Export purchase orders to Excel
     */
    public function export(Request $request): JsonResponse
    {
        try {
            $query = PurchaseOrder::with(['supplier:id,code,name', 'warehouse:id,code,name', 'creator:id,name']);

            // Lọc theo loại planned/actual/all giống index
            if ($request->filled('type')) {
                if ($request->type === 'planned') {
                    $query->where('is_order_only', true);
                } elseif ($request->type === 'actual') {
                    $query->where('is_order_only', false);
                }
            }

            // Apply same filters as index method
            if ($request->filled('supplier_id')) {
                $query->where('supplier_id', $request->supplier_id);
            }
            if ($request->filled('warehouse_id')) {
                $query->where('warehouse_id', $request->warehouse_id);
            }
            if ($request->filled('status')) {
                $query->where('status', $request->status);
            }
            if ($request->filled('date_from')) {
                $query->whereDate('created_at', '>=', $request->date_from);
            }
            if ($request->filled('date_to')) {
                $query->whereDate('created_at', '<=', $request->date_to);
            }

            $orders = $query->orderBy('created_at', 'desc')->get();

            // Transform data for export
            $exportData = $orders->map(function ($order) {
                $isPlanned = (bool) $order->is_order_only;
                $paymentStatus = $isPlanned ? 'planned' : $this->getPaymentStatus($order);
                return [
                    'code' => $order->code,
                    'type' => $isPlanned ? 'Sắp nhập' : 'Thực tế',
                    'supplier_name' => $order->supplier->name,
                    'warehouse_name' => $order->warehouse->name,
                    'status' => $order->status,
                    'total' => $order->total,
                    'paid' => $isPlanned ? 0 : $order->paid,
                    'need_pay' => $isPlanned ? 0 : $order->need_pay,
                    'payment_status' => $paymentStatus,
                    'payment_progress' => $isPlanned ? 0 : ($order->total > 0 ? round(($order->paid / $order->total) * 100, 2) : 0),
                    'is_convertible' => $isPlanned && $order->status === 'approved',
                    'expected_at' => $order->expected_at,
                    'created_by' => $order->creator->name,
                    'created_at' => $order->created_at->format('d/m/Y H:i')
                ];
            });

            return response()->json([
                'success' => true,
                'message' => 'Dữ liệu export đã được chuẩn bị',
                'data' => $exportData
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Lỗi khi export: ' . $e->getMessage()
            ], 500);
        }
    }

    // ===================================================
    // CONVERSION: Planned (order-only) -> Actual Purchase Order
    // ===================================================
    /**
     * Convert a planned purchase order (is_order_only = true) into an actual purchase order
     * so that it starts participating in debt / payment statistics.
     * Conditions:
     *  - Order must currently be order-only
     *  - Status must be 'approved' (already reviewed)
     * Effects:
     *  - Set is_order_only = false
     *  - Recalculate need_pay = total - paid (paid normally 0 for planned orders)
     *  - Create supplier debt record if not existing yet
     */
    public function convertToActual(Request $request, $id): JsonResponse
    {
        try {
            $purchaseOrder = PurchaseOrder::with('supplier')->findOrFail($id);

            if (!$purchaseOrder->is_order_only) {
                return response()->json([
                    'success' => false,
                    'message' => 'Đơn hàng này đã là đơn nhập thực tế'
                ], 422);
            }

            if ($purchaseOrder->status !== 'approved') {
                return response()->json([
                    'success' => false,
                    'message' => 'Chỉ có thể chuyển đổi đơn đặt hàng đã được duyệt (trạng thái: approved)'
                ], 422);
            }

            DB::beginTransaction();

            // Update flags & payment fields
            $purchaseOrder->is_order_only = false;
            // For planned orders paid always 0; ensure need_pay reflects remaining
            $purchaseOrder->need_pay = $purchaseOrder->total - $purchaseOrder->paid;
            $purchaseOrder->save();

            // Create initial supplier debt (if any amount to pay and debt not yet created)
            if ($purchaseOrder->need_pay > 0) {
                $existingDebt = SupplierDebt::where('supplier_id', $purchaseOrder->supplier_id)
                    ->where('type', 'purchase')
                    ->where('ref_code', $purchaseOrder->code)
                    ->exists();
                if (!$existingDebt) {
                    $this->createSupplierDebt($purchaseOrder);
                }
            }

            DB::commit();

            // Reload relations and add computed fields similar to show()
            $purchaseOrder->load(['supplier:id,code,name', 'warehouse:id,code,name', 'creator:id,name']);
            $purchaseOrder->payment_status = $this->getPaymentStatus($purchaseOrder);
            $purchaseOrder->payment_progress = $purchaseOrder->total > 0 ? round(($purchaseOrder->paid / $purchaseOrder->total) * 100, 2) : 0;

            return response()->json([
                'success' => true,
                'message' => 'Chuyển đổi đơn đặt hàng thành công. Bây giờ có thể tạo phiếu nhập và ghi nhận thanh toán.',
                'data' => $purchaseOrder
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Lỗi khi chuyển đổi đơn đặt hàng: ' . $e->getMessage()
            ], 500);
        }
    }
}