<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PurchaseReturnReceipt;
use App\Models\PurchaseReturnItem;
use App\Models\PurchaseReturnOrder;
use App\Models\PurchaseReturnOrderItem;
use App\Models\WarehouseProduct;
use App\Models\SupplierDebt;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;

class PurchaseReturnReceiptController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        try {
            $query = PurchaseReturnReceipt::with([
                'purchaseReturnOrder:id,code,supplier_id',
                'purchaseReturnOrder.supplier:id,name',
                'supplier:id,name',
                'warehouse:id,code,name',
                'returnedBy:id,name',
                'creator:id,name'
            ]);

            // Filters
            if ($request->filled('warehouse_id')) {
                $query->where('warehouse_id', $request->warehouse_id);
            }

            if ($request->filled('supplier_id')) {
                $query->where('supplier_id', $request->supplier_id);
            }

            if ($request->filled('status')) {
                $query->where('status', $request->status);
            }

            if ($request->filled('search')) {
                $search = $request->search;
                $query->where(function($q) use ($search) {
                    $q->where('code', 'LIKE', "%{$search}%")
                      ->orWhereHas('purchaseReturnOrder', function($q) use ($search) {
                          $q->where('code', 'LIKE', "%{$search}%");
                      })
                      ->orWhereHas('supplier', function($q) use ($search) {
                          $q->where('name', 'LIKE', "%{$search}%");
                      });
                });
            }

            // Date filters
            if ($request->filled('date_from')) {
                $query->whereDate('returned_at', '>=', $request->date_from);
            }
            if ($request->filled('date_to')) {
                $query->whereDate('returned_at', '<=', $request->date_to);
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
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Lỗi khi tải danh sách phiếu trả hàng: ' . $e->getMessage()
            ], 500);
        }
    }

    public function store(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'purchase_return_order_id' => 'required|exists:purchase_return_orders,id',
                'warehouse_id' => 'required|exists:warehouses,id',
                'supplier_id' => 'required|exists:suppliers,id',
                'items' => 'required|array|min:1',
                'items.*.purchase_return_order_item_id' => 'required|exists:purchase_return_order_items,id',
                'items.*.quantity_returned' => 'required|integer|min:1',
                'items.*.unit_cost' => 'required|numeric|min:0',
                'items.*.condition_status' => 'nullable|in:good,damaged,expired,wrong_item,excess,defective',
                'items.*.return_reason' => 'nullable|string',
                'returned_at' => 'nullable|date',
                'status' => 'nullable|in:draft,pending',
                'reason' => 'nullable|string',
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

            // Generate code
            $code = $this->generateReceiptCode();

            // Calculate total
            $totalAmount = collect($request->items)->sum(function($item) {
                return $item['quantity_returned'] * $item['unit_cost'];
            });

            // Determine initial status
            $initialStatus = in_array($request->status, ['draft', 'pending'])
                ? $request->status
                : PurchaseReturnReceipt::STATUS_PENDING;

            // Create return receipt
            $receipt = PurchaseReturnReceipt::create([
                'code' => $code,
                'purchase_return_order_id' => $request->purchase_return_order_id,
                'supplier_id' => $request->supplier_id,
                'warehouse_id' => $request->warehouse_id,
                'returned_by' => auth()->id(),
                'created_by' => auth()->id(),
                'returned_at' => $request->returned_at ? Carbon::parse($request->returned_at) : now(),
                // Respect provided status (draft/pending); stock/debt changes happen on approve
                'status' => $initialStatus,
                'total_amount' => $totalAmount,
                'reason' => $request->reason,
                'note' => $request->note,
            ]);

            // Create receipt items (do NOT update inventory here; will be done on approval)
            foreach ($request->items as $item) {
                $orderItem = PurchaseReturnOrderItem::find($item['purchase_return_order_item_id']);

                $totalCost = $item['quantity_returned'] * $item['unit_cost'];

                // Create return item
                PurchaseReturnItem::create([
                    'purchase_return_receipt_id' => $receipt->id,
                    'purchase_return_order_item_id' => $item['purchase_return_order_item_id'],
                    'product_id' => $orderItem->product_id,
                    'quantity_returned' => $item['quantity_returned'],
                    'unit_cost' => $item['unit_cost'],
                    'total_cost' => $totalCost,
                    'condition_status' => $item['condition_status'] ?? 'good',
                    'return_reason' => $item['return_reason'] ?? null,
                    'lot_number' => $item['lot_number'] ?? null,
                    'note' => $item['note'] ?? null,
                ]);
            }

            // Do not update order status or inventory here. Await approval step.

            DB::commit();

            $receipt->load(['purchaseReturnOrder', 'supplier', 'warehouse', 'items.product']);

            return response()->json([
                'success' => true,
                'message' => 'Tạo phiếu trả hàng thành công',
                'data' => $receipt
            ], 201);

        } catch (\Exception $e) {
            DB::rollback();
            return response()->json([
                'success' => false,
                'message' => 'Lỗi khi tạo phiếu trả hàng: ' . $e->getMessage()
            ], 500);
        }
    }

    public function show($id): JsonResponse
    {
        try {
            $receipt = PurchaseReturnReceipt::with([
                'purchaseReturnOrder:id,code,supplier_id,total',
                'purchaseReturnOrder.supplier:id,name,phone,email',
                'supplier:id,name,phone,email',
                'warehouse:id,code,name,address',
                'returnedBy:id,name',
                'creator:id,name',
                'approver:id,name',
                'items.product:id,sku,name',
                'items.purchaseReturnOrderItem:id,quantity,price'
            ])->findOrFail($id);

            return response()->json([
                'success' => true,
                'data' => $receipt
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Lỗi khi tải chi tiết phiếu trả hàng: ' . $e->getMessage()
            ], 500);
        }
    }

    public function updateStatus(Request $request, $id): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'status' => 'required|in:pending,approved,returned,completed,cancelled',
                'reason' => 'nullable|string'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Dữ liệu không hợp lệ',
                    'errors' => $validator->errors()
                ], 422);
            }

            $receipt = PurchaseReturnReceipt::findOrFail($id);

            // Update approval info if status is approved
            if ($request->status === PurchaseReturnReceipt::STATUS_APPROVED && 
                $receipt->status !== PurchaseReturnReceipt::STATUS_APPROVED) {
                $receipt->approved_by = auth()->id();
                $receipt->approved_at = now();
            }

            $receipt->update([
                'status' => $request->status,
                'note' => $request->reason
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Cập nhật trạng thái thành công',
                'data' => $receipt
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Lỗi khi cập nhật trạng thái: ' . $e->getMessage()
            ], 500);
        }
    }

    private function generateReceiptCode(): string
    {
        $prefix = 'RTR' . date('ymd'); // Return Receipt
        $lastReceipt = PurchaseReturnReceipt::where('code', 'LIKE', $prefix . '%')
            ->orderBy('id', 'desc')
            ->first();

        if ($lastReceipt) {
            $lastNumber = intval(substr($lastReceipt->code, -5));
            $newNumber = $lastNumber + 1;
        } else {
            $newNumber = 1;
        }

        return $prefix . str_pad($newNumber, 5, '0', STR_PAD_LEFT);
    }
    /**
 * Approve return receipt and auto reduce supplier debt
 */
public function approve(Request $request, $id): JsonResponse
{
    try {
        $returnReceipt = PurchaseReturnReceipt::findOrFail($id);

        if ($returnReceipt->status !== 'pending') {
            return response()->json([
                'success' => false,
                'message' => 'Chỉ có thể duyệt phiếu trả hàng ở trạng thái chờ duyệt'
            ], 422);
        }

        DB::beginTransaction();

        // Update return receipt status to approved
        $returnReceipt->update([
            'status' => PurchaseReturnReceipt::STATUS_APPROVED,
            'approved_by' => auth()->id(),
            'approved_at' => now()
        ]);

        // Adjust inventory & mark returned quantities atomically
        $returnReceipt->loadMissing(['items.purchaseReturnOrderItem', 'items.purchaseReturnOrderItem.purchaseReceiptItem', 'purchaseReturnOrder']);
        foreach ($returnReceipt->items as $item) {
            // 1. Validate inventory
            $warehouseProduct = WarehouseProduct::where('warehouse_id', $returnReceipt->warehouse_id)
                ->where('product_id', $item->product_id)
                ->lockForUpdate()
                ->first();

            if (!$warehouseProduct || $warehouseProduct->quantity < $item->quantity_returned) {
                throw new \Exception("Không đủ tồn kho cho sản phẩm ID {$item->product_id}. Tồn kho: " . ($warehouseProduct->quantity ?? 0));
            }

            // 2. Validate original receipt item remaining returnable
            $orderItem = $item->purchaseReturnOrderItem; // may be null if independent
            if ($orderItem && $orderItem->purchase_receipt_item_id) {
                $receiptItem = \App\Models\PurchaseReceiptItem::lockForUpdate()->find($orderItem->purchase_receipt_item_id);
                if (!$receiptItem) {
                    throw new \Exception('Không tìm thấy dòng phiếu nhập gốc');
                }
                $available = $receiptItem->quantity_received - $receiptItem->returned_quantity;
                if ($item->quantity_returned > $available) {
                    throw new \Exception("Số lượng trả vượt quá còn có thể trả cho sản phẩm ID {$item->product_id}");
                }
                // 3. Mark returned quantity on receipt item
                $receiptItem->increment('returned_quantity', $item->quantity_returned);
            }

            // 4. Reduce stock
            $warehouseProduct->quantity -= $item->quantity_returned;
            $warehouseProduct->last_export_date = now();
            $warehouseProduct->save();
        }

        // AUTO REDUCE SUPPLIER DEBT when return receipt is approved
        $this->reduceSupplierDebtForReturnReceipt($returnReceipt);

        // Update return order status if needed
        if ($returnReceipt->purchase_return_order_id) {
            $returnOrder = PurchaseReturnOrder::find($returnReceipt->purchase_return_order_id);
            if ($returnOrder && $returnOrder->status === PurchaseReturnOrder::STATUS_APPROVED) {
                $returnOrder->status = PurchaseReturnOrder::STATUS_RETURNED;
                $returnOrder->save();
            }
        }

        // Mark receipt as completed after stock and debt adjustments
        $returnReceipt->status = PurchaseReturnReceipt::STATUS_COMPLETED;
        $returnReceipt->save();

        DB::commit();

        $returnReceipt->load(['supplier', 'warehouse', 'items.product']);

        return response()->json([
            'success' => true,
            'message' => 'Duyệt phiếu trả hàng thành công và đã giảm công nợ nhà cung cấp',
            'data' => $returnReceipt
        ]);

    } catch (\Exception $e) {
        DB::rollback();
        return response()->json([
            'success' => false,
            'message' => 'Lỗi khi duyệt phiếu trả hàng: ' . $e->getMessage()
        ], 500);
    }
}

/**
 * Auto reduce supplier debt when return receipt is approved
 */
private function reduceSupplierDebtForReturnReceipt($returnReceipt): void
{
    try {
        // Use the fixed SupplierDebt method  
        SupplierDebt::createReturnCredit(
            supplierId: $returnReceipt->supplier_id,
            amount: $returnReceipt->total_amount,
            refCode: $returnReceipt->code,
            note: "Giảm công nợ do phiếu trả hàng {$returnReceipt->code}"
        );

        Log::info("Auto reduced supplier debt for Return Receipt: {$returnReceipt->code}", [
            'return_receipt_id' => $returnReceipt->id,
            'supplier_id' => $returnReceipt->supplier_id,
            'reduced_amount' => $returnReceipt->total_amount
        ]);

    } catch (\Exception $e) {
        Log::error("Failed to reduce supplier debt for Return Receipt: {$returnReceipt->code}", [
            'error' => $e->getMessage(),
            'return_receipt_id' => $returnReceipt->id
        ]);
        throw $e;
    }
}

}