<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PurchaseReturnOrder;
use App\Models\PurchaseReturnOrderItem;
use App\Models\PurchaseReturnOrderStatusHistory;
use App\Models\PurchaseReceipt;
use App\Models\PurchaseReceiptItem;
use App\Models\SupplierDebt;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class PurchaseReturnOrderController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        try {
            $query = PurchaseReturnOrder::with(['supplier', 'warehouse', 'creator']);

            // Filters
            if ($request->has('search') && !empty($request->search)) {
                $search = $request->search;
                $query->where(function($q) use ($search) {
                    $q->where('code', 'like', "%{$search}%")
                      ->orWhereHas('supplier', function($sq) use ($search) {
                          $sq->where('name', 'like', "%{$search}%");
                      });
                });
            }

            if ($request->has('supplier_id') && !empty($request->supplier_id)) {
                $query->where('supplier_id', $request->supplier_id);
            }

            if ($request->has('warehouse_id') && !empty($request->warehouse_id)) {
                $query->where('warehouse_id', $request->warehouse_id);
            }

            if ($request->has('status') && !empty($request->status)) {
                $query->where('status', $request->status);
            }

            if ($request->has('date_from') && !empty($request->date_from)) {
                $query->whereDate('returned_at', '>=', $request->date_from);
            }

            if ($request->has('date_to') && !empty($request->date_to)) {
                $query->whereDate('returned_at', '<=', $request->date_to);
            }

            // Sort
            $query->orderBy('created_at', 'desc');

            // Pagination
            $perPage = $request->get('per_page', 20);
            $returnOrders = $query->paginate($perPage);

            return response()->json([
                'success' => true,
                'data' => $returnOrders
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Lỗi khi lấy danh sách đơn trả hàng: ' . $e->getMessage()
            ], 500);
        }
    }

    public function show($id): JsonResponse
    {
        try {
            $returnOrder = PurchaseReturnOrder::with([
                'supplier', 
                'warehouse', 
                'creator', 
                'approver',
                'items.product',
                'items.purchaseReceipt',
                'items.purchaseReceiptItem'
            ])->findOrFail($id);

            return response()->json([
                'success' => true,
                'data' => $returnOrder
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Lỗi khi lấy thông tin đơn trả hàng: ' . $e->getMessage()
            ], 500);
        }
    }

    public function getReceiptsBySupplier($supplierId): JsonResponse
    {
        try {
            $receipts = PurchaseReceipt::with(['purchaseOrder', 'warehouse', 'items.product'])
                ->whereHas('purchaseOrder', function($query) use ($supplierId) {
                    $query->where('supplier_id', $supplierId);
                })
                ->where('status', 'completed')
                ->whereHas('items', function($query) {
                    $query->whereRaw('(quantity_received - returned_quantity) > 0');
                })
                ->orderBy('received_at', 'desc')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $receipts
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Lỗi khi lấy danh sách phiếu nhập: ' . $e->getMessage()
            ], 500);
        }
    }

    public function getReturnableItems($receiptId): JsonResponse
    {
        try {
            $items = PurchaseReceiptItem::with(['product', 'purchaseOrderItem'])
                ->where('purchase_receipt_id', $receiptId)
                ->returnable()
                ->get()
                ->map(function($item) {
                    return [
                        'id' => $item->id,
                        'product_id' => $item->product_id,
                        'product' => $item->product,
                        'quantity_received' => $item->quantity_received,
                        'returned_quantity' => $item->returned_quantity,
                        'returnable_quantity' => $item->returnable_quantity,
                        'unit_cost' => $item->unit_cost,
                        'lot_number' => $item->lot_number,
                        'expiry_date' => $item->expiry_date,
                        'condition_status' => $item->condition_status,
                    ];
                });

            return response()->json([
                'success' => true,
                'data' => $items
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Lỗi khi lấy danh sách sản phẩm có thể trả: ' . $e->getMessage()
            ], 500);
        }
    }

    public function store(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'supplier_id' => 'required|exists:suppliers,id',
                'warehouse_id' => 'required|exists:warehouses,id',
                'returned_at' => 'nullable|date',
                'items' => 'required|array|min:1',
                'items.*.purchase_receipt_item_id' => 'required|exists:purchase_receipt_items,id',
                'items.*.product_id' => 'required|exists:products,id',
                'items.*.quantity' => 'required|integer|min:1',
                'items.*.price' => 'required|numeric|min:0',
                'note' => 'nullable|string',
                'return_reason' => 'nullable|string',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Dữ liệu không hợp lệ',
                    'errors' => $validator->errors()
                ], 422);
            }

            DB::beginTransaction();

            // Validate returnable quantities
            foreach ($request->items as $item) {
                $receiptItem = PurchaseReceiptItem::find($item['purchase_receipt_item_id']);
                if ($item['quantity'] > $receiptItem->returnable_quantity) {
                    return response()->json([
                        'success' => false,
                        'message' => "Số lượng trả vượt quá số lượng có thể trả cho sản phẩm {$receiptItem->product->name}"
                    ], 422);
                }
            }

            // Generate code
            $code = $this->generateReturnCode();

            // Calculate total
            $total = collect($request->items)->sum(function($item) {
                return $item['quantity'] * $item['price'];
            });

            // Create return order
            $returnOrder = PurchaseReturnOrder::create([
                'code' => $code,
                'supplier_id' => $request->supplier_id,
                'warehouse_id' => $request->warehouse_id,
                'created_by' => auth()->id(),
                'returned_at' => $request->returned_at,
                'status' => PurchaseReturnOrder::STATUS_PENDING,
                'total' => $total,
                'note' => $request->note,
                'return_reason' => $request->return_reason,
            ]);

            // Create items (không cập nhật returned_quantity ở giai đoạn order)
            foreach ($request->items as $item) {
                $receiptItem = PurchaseReceiptItem::find($item['purchase_receipt_item_id']);
                PurchaseReturnOrderItem::create([
                    'purchase_return_order_id' => $returnOrder->id,
                    'purchase_receipt_id' => $receiptItem->purchase_receipt_id,
                    'purchase_receipt_item_id' => $item['purchase_receipt_item_id'],
                    'product_id' => $item['product_id'],
                    'quantity' => $item['quantity'],
                    'max_returnable_quantity' => $receiptItem->returnable_quantity,
                    'returned_quantity' => 0, // sẽ tăng khi approve return receipt
                    'price' => $item['price'],
                    'note' => $item['note'] ?? null,
                    'return_reason' => $item['return_reason'] ?? null,
                    'condition_status' => $item['condition_status'] ?? 'good',
                ]);
            }

            DB::commit();

            $returnOrder->load(['supplier', 'warehouse', 'items.product']);

            return response()->json([
                'success' => true,
                'message' => 'Tạo đơn trả hàng thành công',
                'data' => $returnOrder
            ], 201);

        } catch (\Exception $e) {
            DB::rollback();
            return response()->json([
                'success' => false,
                'message' => 'Lỗi khi tạo đơn trả hàng: ' . $e->getMessage()
            ], 500);
        }
    }

    public function update(Request $request, $id): JsonResponse
    {
        try {
            $returnOrder = PurchaseReturnOrder::findOrFail($id);

            if (!$returnOrder->canUpdate()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Không thể cập nhật đơn trả hàng ở trạng thái hiện tại'
                ], 422);
            }

            $validator = Validator::make($request->all(), [
                'supplier_id' => 'required|exists:suppliers,id',
                'warehouse_id' => 'required|exists:warehouses,id',
                'returned_at' => 'nullable|date',
                'items' => 'required|array|min:1',
                'items.*.purchase_receipt_item_id' => 'required|exists:purchase_receipt_items,id',
                'items.*.product_id' => 'required|exists:products,id',
                'items.*.quantity' => 'required|integer|min:1',
                'items.*.price' => 'required|numeric|min:0',
                'note' => 'nullable|string',
                'return_reason' => 'nullable|string',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Dữ liệu không hợp lệ',
                    'errors' => $validator->errors()
                ], 422);
            }

            DB::beginTransaction();

            // Không cần revert returned_quantity vì chưa thay đổi ở giai đoạn order

            // Validate new returnable quantities
            foreach ($request->items as $item) {
                $receiptItem = PurchaseReceiptItem::find($item['purchase_receipt_item_id']);
                if ($item['quantity'] > $receiptItem->returnable_quantity) {
                    return response()->json([
                        'success' => false,
                        'message' => "Số lượng trả vượt quá số lượng có thể trả cho sản phẩm {$receiptItem->product->name}"
                    ], 422);
                }
            }

            // Calculate new total
            $total = collect($request->items)->sum(function($item) {
                return $item['quantity'] * $item['price'];
            });

            // Update return order
            $returnOrder->update([
                'supplier_id' => $request->supplier_id,
                'warehouse_id' => $request->warehouse_id,
                'returned_at' => $request->returned_at,
                'total' => $total,
                'note' => $request->note,
                'return_reason' => $request->return_reason,
            ]);

            // Delete old items and create new ones
            $returnOrder->items()->delete();

            foreach ($request->items as $item) {
                $receiptItem = PurchaseReceiptItem::find($item['purchase_receipt_item_id']);
                PurchaseReturnOrderItem::create([
                    'purchase_return_order_id' => $returnOrder->id,
                    'purchase_receipt_id' => $receiptItem->purchase_receipt_id,
                    'purchase_receipt_item_id' => $item['purchase_receipt_item_id'],
                    'product_id' => $item['product_id'],
                    'quantity' => $item['quantity'],
                    'max_returnable_quantity' => $receiptItem->returnable_quantity,
                    'returned_quantity' => 0,
                    'price' => $item['price'],
                    'note' => $item['note'] ?? null,
                    'return_reason' => $item['return_reason'] ?? null,
                    'condition_status' => $item['condition_status'] ?? 'good',
                ]);
            }

            DB::commit();

            $returnOrder->load(['supplier', 'warehouse', 'items.product']);

            return response()->json([
                'success' => true,
                'message' => 'Cập nhật đơn trả hàng thành công',
                'data' => $returnOrder
            ]);

        } catch (\Exception $e) {
            DB::rollback();
            return response()->json([
                'success' => false,
                'message' => 'Lỗi khi cập nhật đơn trả hàng: ' . $e->getMessage()
            ], 500);
        }
    }

    public function updateStatus(Request $request, $id): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'status' => 'required|in:pending,approved,returned,completed,cancelled',
                'note' => 'nullable|string|max:500'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Dữ liệu không hợp lệ',
                    'errors' => $validator->errors()
                ], 422);
            }

            $returnOrder = PurchaseReturnOrder::findOrFail($id);
            $oldStatus = $returnOrder->status;

            if (!$returnOrder->canUpdateStatus($request->status)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Không thể chuyển trạng thái từ ' . $oldStatus . ' sang ' . $request->status
                ], 422);
            }

            DB::beginTransaction();

            $returnOrder->update([
                'status' => $request->status
            ]);

            // Log status history
            PurchaseReturnOrderStatusHistory::create([
                'purchase_return_order_id' => $returnOrder->id,
                'from_status' => $oldStatus,
                'to_status' => $request->status,
                'reason' => $request->note,
                'changed_by' => auth()->id(),
                'changed_at' => now()
            ]);

            DB::commit();

            $returnOrder->load(['supplier', 'warehouse', 'creator', 'items.product']);

            return response()->json([
                'success' => true,
                'message' => 'Cập nhật trạng thái thành công',
                'data' => $returnOrder
            ]);

        } catch (\Exception $e) {
            DB::rollback();
            return response()->json([
                'success' => false,
                'message' => 'Lỗi khi cập nhật trạng thái: ' . $e->getMessage()
            ], 500);
        }
    }

    public function destroy($id): JsonResponse
    {
        try {
            $returnOrder = PurchaseReturnOrder::findOrFail($id);

            if (!$returnOrder->canDelete()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Không thể xóa đơn trả hàng ở trạng thái hiện tại'
                ], 422);
            }

            DB::beginTransaction();

            // Không cần revert returned_quantity khi xóa order vì chưa thay đổi

            $returnOrder->delete();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Xóa đơn trả hàng thành công'
            ]);

        } catch (\Exception $e) {
            DB::rollback();
            return response()->json([
                'success' => false,
                'message' => 'Lỗi khi xóa đơn trả hàng: ' . $e->getMessage()
            ], 500);
        }
    }

    private function generateReturnCode(): string
    {
        $prefix = 'RTN' . date('ymd');
        $lastReturn = PurchaseReturnOrder::where('code', 'LIKE', $prefix . '%')
            ->orderBy('id', 'desc')
            ->first();

        if ($lastReturn) {
            $lastNumber = intval(substr($lastReturn->code, -5));
            $newNumber = $lastNumber + 1;
        } else {
            $newNumber = 1;
        }

        return $prefix . str_pad($newNumber, 5, '0', STR_PAD_LEFT);
    }
    /**
 * Approve return order and auto reduce supplier debt
 */
public function approve(Request $request, $id): JsonResponse
{
    try {
        $returnOrder = PurchaseReturnOrder::findOrFail($id);

        if ($returnOrder->status !== 'pending') {
            return response()->json([
                'success' => false,
                'message' => 'Chỉ có thể duyệt đơn trả hàng ở trạng thái chờ duyệt'
            ], 422);
        }

        DB::beginTransaction();

        // Update return order status
        $returnOrder->update([
            'status' => 'approved',
            'approved_by' => auth()->id(),
            'approved_at' => now()
        ]);

    // Note: Do not change stock or supplier debt at order approval stage.
    // Those adjustments will occur when the corresponding return receipt is approved.

        DB::commit();

        $returnOrder->load(['supplier', 'warehouse', 'items.product']);

        return response()->json([
            'success' => true,
            'message' => 'Duyệt đơn trả hàng thành công',
            'data' => $returnOrder
        ]);

    } catch (\Exception $e) {
        DB::rollback();
        return response()->json([
            'success' => false,
            'message' => 'Lỗi khi duyệt đơn trả hàng: ' . $e->getMessage()
        ], 500);
    }
}

/**
 * Auto reduce supplier debt when return order is approved
 */
private function reduceSupplierDebtForReturn($returnOrder): void
{
    try {
        // Use the fixed SupplierDebt method
        SupplierDebt::createReturnCredit(
            supplierId: $returnOrder->supplier_id,
            amount: $returnOrder->total,
            refCode: $returnOrder->code,
            note: "Giảm công nợ do đơn trả hàng {$returnOrder->code}"
        );

        Log::info("Auto reduced supplier debt for Return Order: {$returnOrder->code}", [
            'return_order_id' => $returnOrder->id,
            'supplier_id' => $returnOrder->supplier_id,
            'reduced_amount' => $returnOrder->total
        ]);

    } catch (\Exception $e) {
        Log::error("Failed to reduce supplier debt for Return Order: {$returnOrder->code}", [
            'error' => $e->getMessage(),
            'return_order_id' => $returnOrder->id
        ]);
        throw $e;
    }
}
}