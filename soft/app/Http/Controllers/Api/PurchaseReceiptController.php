<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductSerial;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\PurchaseReceipt;
use App\Models\PurchaseReceiptItem;
use App\Models\SupplierDebt;
use App\Models\WarehouseProduct;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class PurchaseReceiptController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        try {
            $query = PurchaseReceipt::with([
                'purchaseOrder:id,code,supplier_id',
                'purchaseOrder.supplier:id,name',
                'supplier:id,name', // eager load direct supplier for independent receipts
                'warehouse:id,code,name',
                'receiver:id,name',
            ]);

            // Filters
            if ($request->filled('warehouse_id')) {
                $query->where('warehouse_id', $request->warehouse_id);
            }

            if ($request->filled('status')) {
                $query->where('status', $request->status);
            }

            if ($request->filled('search')) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('code', 'LIKE', "%{$search}%")
                        ->orWhereHas('purchaseOrder', function ($q) use ($search) {
                            $q->where('code', 'LIKE', "%{$search}%");
                        })
                        ->orWhereHas('supplier', function ($q) use ($search) {
                            $q->where('name', 'LIKE', "%{$search}%");
                        });
                });
            }

            $receipts = $query->orderBy('created_at', 'desc')
                ->paginate($request->get('per_page', 20));

            return response()->json([
                'success' => true,
                'data' => $receipts->items(),
                'pagination' => [
                    'current_page' => $receipts->currentPage(),
                    'last_page' => $receipts->lastPage(),
                    'per_page' => $receipts->perPage(),
                    'total' => $receipts->total(),
                ],
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Lỗi khi tải danh sách phiếu nhập: '.$e->getMessage(),
            ], 500);
        }
    }

    public function store(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'purchase_order_id' => 'nullable|exists:purchase_orders,id', // có thể tạo độc lập
                // nullable: nếu để trống (""), Laravel coi như null và bỏ qua exists khi đã có purchase_order_id
                'supplier_id' => 'nullable|required_without:purchase_order_id|exists:suppliers,id',
                'warehouse_id' => 'required|exists:warehouses,id',
                'items' => 'required|array|min:1',
                'items.*.purchase_order_item_id' => 'nullable|exists:purchase_order_items,id',
                // Cho phép null khi tạo theo đơn (có purchase_order_item_id)
                'items.*.product_id' => 'nullable|required_without:items.*.purchase_order_item_id|exists:products,id',
                'items.*.quantity_received' => 'required|integer|min:1',
                'items.*.unit_cost' => 'required|numeric|min:0',
                'items.*.condition_status' => 'nullable|in:good,damaged,expired',
                'items.*.lot_number' => 'nullable|string',
                'items.*.expiry_date' => 'nullable|date',
                'items.*.serial_numbers' => 'nullable|array',
                'items.*.serial_numbers.*' => 'nullable|string|max:255',
                'payment_type' => 'required|in:full,partial,debt',
                'paid' => 'nullable|numeric|min:0',
                'note' => 'nullable|string',
            ], [
                'supplier_id.required_without' => 'Chọn nhà cung cấp hoặc chọn đơn đặt hàng',
                'items.*.product_id.required_without' => 'Thiếu sản phẩm cho dòng chưa liên kết đơn đặt hàng',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Dữ liệu không hợp lệ',
                    'errors' => $validator->errors(),
                ], 422);
            }

            DB::beginTransaction();
            $code = $this->generateReceiptCode();

            // Determine supplier
            $purchaseOrder = null;
            $supplierId = $request->supplier_id;
            if ($request->purchase_order_id) {
                $purchaseOrder = PurchaseOrder::with('items')->findOrFail($request->purchase_order_id);
                $supplierId = $purchaseOrder->supplier_id;
            }

            $totalAmount = collect($request->items)->sum(fn ($i) => $i['quantity_received'] * $i['unit_cost']);

            // Normalize payment
            $paymentType = $request->payment_type; // full|partial|debt
            $paid = 0;
            $needPay = 0;
            if ($paymentType === 'full') {
                $paid = $totalAmount;
                $needPay = 0; // không lưu công nợ
            } elseif ($paymentType === 'partial') {
                $paid = min($request->paid ?? 0, $totalAmount);
                $needPay = $totalAmount - $paid; // ghi nhận phần còn lại
            } else { // debt
                $paid = 0;
                $needPay = $totalAmount;
            }

            // Persist only a real purchase_order_id (from loaded order) or NULL for independent mode
            $persistPurchaseOrderId = $purchaseOrder ? $purchaseOrder->id : null;

            $receipt = PurchaseReceipt::create([
                'code' => $code,
                'purchase_order_id' => $persistPurchaseOrderId,
                'supplier_id' => $supplierId,
                'warehouse_id' => $request->warehouse_id,
                'received_by' => auth()->id(),
                'received_at' => now(),
                'status' => 'pending',
                'total_amount' => $totalAmount,
                'payment_type' => $paymentType,
                'paid' => $paid,
                'need_pay' => $needPay,
                'note' => $request->note,
            ]);

            foreach ($request->items as $item) {
                // Safe access for manual mode where purchase_order_item_id may be absent
                $purchaseOrderItemId = $item['purchase_order_item_id'] ?? null;

                if ($purchaseOrderItemId) {
                    $orderItem = PurchaseOrderItem::findOrFail($purchaseOrderItemId);
                    if ($item['quantity_received'] > $orderItem->remaining_quantity) {
                        throw new \Exception("Số lượng nhập vượt quá số lượng còn lại của sản phẩm {$orderItem->product->name}");
                    }
                    $orderItem->received_quantity += $item['quantity_received'];
                    $orderItem->remaining_quantity -= $item['quantity_received'];
                    $orderItem->save();
                    $productId = $orderItem->product_id;
                } else {
                    // In independent mode product_id is required by validation
                    $productId = $item['product_id'] ?? null;
                }

                $totalCost = $item['quantity_received'] * $item['unit_cost'];
                $receiptItem = PurchaseReceiptItem::create([
                    'purchase_receipt_id' => $receipt->id,
                    'purchase_order_item_id' => $purchaseOrderItemId,
                    'product_id' => $productId,
                    'quantity_received' => $item['quantity_received'],
                    'unit_cost' => $item['unit_cost'],
                    'total_cost' => $totalCost,
                    'condition_status' => $item['condition_status'] ?? 'good',
                    'lot_number' => $item['lot_number'] ?? null,
                    'expiry_date' => $item['expiry_date'] ?? null,
                    'note' => $item['note'] ?? null,
                ]);

                // Serial/IMEI handling: create serial records if product has track_serial
                $product = Product::find($productId);
                if ($product && $product->track_serial) {
                    $serialNumbers = $item['serial_numbers'] ?? [];
                    if (empty($serialNumbers)) {
                        throw new \Exception("Sản phẩm '{$product->name}' yêu cầu nhập Serial/IMEI. Vui lòng cung cấp danh sách serial.");
                    }
                    if (count($serialNumbers) !== (int) $item['quantity_received']) {
                        throw new \Exception("Sản phẩm '{$product->name}': Số serial (" . count($serialNumbers) . ") phải bằng số lượng nhập ({$item['quantity_received']})");
                    }
                    // Check duplicates within this batch
                    $uniqueSerials = array_unique(array_map('trim', $serialNumbers));
                    if (count($uniqueSerials) !== count($serialNumbers)) {
                        throw new \Exception("Sản phẩm '{$product->name}': Có serial trùng lặp trong danh sách nhập");
                    }
                    // Check duplicates in database
                    $existingSerials = ProductSerial::where('product_id', $productId)
                        ->whereIn('serial_number', $uniqueSerials)
                        ->pluck('serial_number')->toArray();
                    if (!empty($existingSerials)) {
                        throw new \Exception("Sản phẩm '{$product->name}': Serial đã tồn tại: " . implode(', ', $existingSerials));
                    }
                    ProductSerial::bulkImport(
                        $uniqueSerials,
                        $productId,
                        $request->warehouse_id,
                        $item['unit_cost'],
                        $receiptItem->id
                    );
                }

                // Stock update
                $warehouseProduct = WarehouseProduct::where('warehouse_id', $request->warehouse_id)
                    ->where('product_id', $productId)
                    ->first();
                if ($warehouseProduct) {
                    $warehouseProduct->quantity += $item['quantity_received'];
                    $warehouseProduct->cost = $item['unit_cost'];
                    $warehouseProduct->last_import_date = now();
                    $warehouseProduct->save();
                } else {
                    WarehouseProduct::create([
                        'warehouse_id' => $request->warehouse_id,
                        'product_id' => $productId,
                        'quantity' => $item['quantity_received'],
                        'cost' => $item['unit_cost'],
                        'last_import_date' => now(),
                    ]);
                }
            }

            // Update purchase order status if linked
            if ($purchaseOrder) {
                $purchaseOrder->refresh();
                $totalReceived = $purchaseOrder->items->sum('received_quantity');
                $totalOrdered = $purchaseOrder->items->sum('quantity');
                $purchaseOrder->status = $totalReceived >= $totalOrdered ? 'received' : 'partial_received';
                $purchaseOrder->save();
            }

            DB::commit();
            $receipt->load(['purchaseOrder', 'supplier', 'warehouse', 'items.product']);

            return response()->json([
                'success' => true,
                'message' => 'Tạo phiếu nhập kho thành công (chờ duyệt)',
                'data' => $receipt,
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Lỗi khi tạo phiếu nhập: '.$e->getMessage(),
            ], 500);
        }
    }

    public function approve($id): JsonResponse
    {
        try {
            $receipt = PurchaseReceipt::with('purchaseOrder')->findOrFail($id);
            if ($receipt->status !== 'pending') {
                return response()->json(['success' => false, 'message' => 'Chỉ duyệt phiếu ở trạng thái pending'], 422);
            }

            DB::beginTransaction();
            $receipt->status = 'completed';
            $receipt->approved_at = now();
            $receipt->approved_by = auth()->id();
            $receipt->save();

            // Debt handling
            if ($receipt->payment_type === 'partial') {
                // + TỔNG GIÁ TRỊ ĐƠN vào công nợ (ghi nhận nợ mua hàng)
                SupplierDebt::createDebtRecord(
                    supplierId: $receipt->supplier_id,
                    amount: $receipt->total_amount,
                    type: 'purchase',
                    refCode: $receipt->code,
                    purchaseReceiptId: $receipt->id,
                    note: 'Công nợ từ phiếu nhập (partial) '.$receipt->code
                );
                // - Phần đã thanh toán (nếu có)
                if ($receipt->paid > 0) {
                    SupplierDebt::createPayment(
                        supplierId: $receipt->supplier_id,
                        amount: $receipt->paid,
                        refCode: $receipt->code.'-PAY',
                        purchaseOrderId: $receipt->purchase_order_id,
                        note: 'Thanh toán trên phiếu nhập '.$receipt->code
                    );
                }
            } elseif ($receipt->payment_type === 'debt') {
                SupplierDebt::createDebtRecord(
                    supplierId: $receipt->supplier_id,
                    amount: $receipt->total_amount,
                    type: 'purchase',
                    refCode: $receipt->code,
                    purchaseReceiptId: $receipt->id,
                    note: 'Công nợ từ phiếu nhập '.$receipt->code
                );
            } elseif ($receipt->payment_type === 'full') {
                // full: ghi nhận + và - ngay để không còn trong công nợ (nếu muốn lưu lịch sử)
                SupplierDebt::createDebtRecord(
                    supplierId: $receipt->supplier_id,
                    amount: $receipt->total_amount,
                    type: 'purchase',
                    refCode: $receipt->code,
                    purchaseReceiptId: $receipt->id,
                    note: 'Ghi nhận giá trị đơn (full paid) '.$receipt->code
                );
                SupplierDebt::createPayment(
                    supplierId: $receipt->supplier_id,
                    amount: $receipt->total_amount,
                    refCode: $receipt->code.'-PAY',
                    purchaseOrderId: $receipt->purchase_order_id,
                    note: 'Thanh toán toàn bộ phiếu nhập '.$receipt->code
                );
            }

            DB::commit();

            return response()->json(['success' => true, 'message' => 'Duyệt phiếu nhập thành công']);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json(['success' => false, 'message' => 'Lỗi khi duyệt: '.$e->getMessage()], 500);
        }
    }

    public function show($id): JsonResponse
    {
        try {
            $receipt = PurchaseReceipt::with([
                'purchaseOrder.supplier',
                'warehouse',
                'receiver',
                'items.product',
                'items.purchaseOrderItem',
                'items.serials:id,purchase_receipt_item_id,serial_number,status',
            ])->findOrFail($id);

            return response()->json([
                'success' => true,
                'data' => $receipt,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Lỗi khi tải chi tiết phiếu nhập: '.$e->getMessage(),
            ], 500);
        }
    }

    public function update(Request $request, $id): JsonResponse
    {
        try {
            $receipt = PurchaseReceipt::with('items')->findOrFail($id);
            if (! in_array($receipt->status, ['pending', 'partial'])) {
                return response()->json(['success' => false, 'message' => 'Chỉ sửa phiếu ở trạng thái pending/partial'], 422);
            }

            $validator = Validator::make($request->all(), [
                'warehouse_id' => 'required|exists:warehouses,id',
                'note' => 'nullable|string',
            ]);
            if ($validator->fails()) {
                return response()->json(['success' => false, 'message' => 'Dữ liệu không hợp lệ', 'errors' => $validator->errors()], 422);
            }

            $receipt->update([
                'warehouse_id' => $request->warehouse_id,
                'note' => $request->note,
            ]);

            return response()->json(['success' => true, 'message' => 'Cập nhật phiếu nhập thành công', 'data' => $receipt->fresh()]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Lỗi cập nhật: '.$e->getMessage()], 500);
        }
    }

    public function cancel(Request $request, $id): JsonResponse
    {
        try {
            $receipt = PurchaseReceipt::findOrFail($id);
            if (! in_array($receipt->status, ['pending', 'partial'])) {
                return response()->json(['success' => false, 'message' => 'Chỉ hủy phiếu ở trạng thái pending/partial'], 422);
            }
            $receipt->status = 'cancelled';
            $receipt->cancel_reason = $request->reason;
            $receipt->save();

            return response()->json(['success' => true, 'message' => 'Hủy phiếu nhập thành công']);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Lỗi hủy: '.$e->getMessage()], 500);
        }
    }

    public function destroy($id): JsonResponse
    {
        try {
            $receipt = PurchaseReceipt::findOrFail($id);
            if (! in_array($receipt->status, ['pending', 'cancelled'])) {
                return response()->json(['success' => false, 'message' => 'Chỉ xóa phiếu ở trạng thái pending/cancelled'], 422);
            }
            $receipt->delete();

            return response()->json(['success' => true, 'message' => 'Xóa phiếu nhập thành công']);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Lỗi xóa: '.$e->getMessage()], 500);
        }
    }

    public function print($id)
    {
        $receipt = PurchaseReceipt::with([
            'purchaseOrder.supplier',
            'supplier',
            'warehouse',
            'receiver',
            'items.product',
        ])->findOrFail($id);

        return view('purchase-receipts.print', compact('receipt'));
    }

    private function generateReceiptCode(): string
    {
        $prefix = 'PN'.date('ymd');
        $lastReceipt = PurchaseReceipt::where('code', 'LIKE', $prefix.'%')
            ->orderBy('id', 'desc')
            ->first();

        if ($lastReceipt) {
            $lastNumber = intval(substr($lastReceipt->code, -5));
            $newNumber = $lastNumber + 1;
        } else {
            $newNumber = 1;
        }

        return $prefix.str_pad($newNumber, 5, '0', STR_PAD_LEFT);
    }

    /**
     * Auto create supplier debt when purchase receipt is created
     */
    private function createSupplierDebtForReceipt(PurchaseOrder $purchaseOrder, $totalAmount): void
    {
        try {
            SupplierDebt::createDebtRecord(
                supplierId: $purchaseOrder->supplier_id,
                amount: $totalAmount,
                type: 'purchase',
                refCode: $purchaseOrder->code.'-RECEIPT',
                purchaseOrderId: $purchaseOrder->id,
                note: "Công nợ từ phiếu nhập hàng {$purchaseOrder->code}"
            );

            Log::info('Auto created supplier debt for Purchase Receipt', [
                'purchase_order_id' => $purchaseOrder->id,
                'supplier_id' => $purchaseOrder->supplier_id,
                'amount' => $totalAmount,
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to create supplier debt for Purchase Receipt', [
                'error' => $e->getMessage(),
                'purchase_order_id' => $purchaseOrder->id,
            ]);
            throw $e;
        }
    }
}
