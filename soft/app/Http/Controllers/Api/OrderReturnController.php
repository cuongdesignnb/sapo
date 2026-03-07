<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderReturn;
use App\Models\OrderReturnItem;
use App\Models\CustomerDebt;
use App\Models\WarehouseProduct;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class OrderReturnController extends Controller
{
    public function index(Request $request)
    {
        $query = OrderReturn::with(['customer', 'order.customerDebt', 'warehouse', 'creator', 'items.product'])
            ->orderBy('created_at', 'desc');

        // Filters
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('customer_id')) {
            $query->where('customer_id', $request->customer_id);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('code', 'like', "%{$search}%")
                  ->orWhereHas('customer', function ($cq) use ($search) {
                      $cq->where('name', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%");
                  })
                  ->orWhereHas('order', function ($oq) use ($search) {
                      $oq->where('code', 'like', "%{$search}%");
                  });
            });
        }

        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        $returns = $query->paginate($request->per_page ?? 20);

        return response()->json([
            'success' => true,
            'data' => $returns->items(),
            'pagination' => [
                'current_page' => $returns->currentPage(),
                'last_page' => $returns->lastPage(),
                'per_page' => $returns->perPage(),
                'total' => $returns->total(),
                'from' => $returns->firstItem(),
                'to' => $returns->lastItem(),
            ]
        ]);
    }

    public function show(OrderReturn $orderReturn)
    {
        $orderReturn->load([
            'customer',
            'order.items.product',
            'warehouse',
            'creator',
            'approver',
            'items.product',
            'items.orderItem'
        ]);

        return response()->json([
            'success' => true,
            'data' => $orderReturn
        ]);
    }

    public function store(Request $request, Order $order)
    {
        \Log::info('OrderReturn store called', [
            'order_id' => $order->id,
            'request_data' => $request->all()
        ]);

        $request->validate([
            'items' => 'required|array|min:1',
            'items.*.order_item_id' => 'required|exists:order_items,id',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.return_reason' => 'nullable|string',
            'return_reason' => 'nullable|string',
            'note' => 'nullable|string',
        ]);

        // Validate order can create return
        if (!$this->canCreateReturn($order)) {
            return response()->json([
                'success' => false,
                'message' => 'Đơn hàng này không thể tạo đơn trả hàng'
            ], 400);
        }

        // Load order with items to ensure we have the data
        $order->load('items.product');
        
        \Log::info('Order items loaded', [
            'items_count' => $order->items->count(),
            'items' => $order->items->toArray()
        ]);

        // Validate items belong to this order and quantities
        $orderItemIds = $order->items->pluck('id')->toArray();
        foreach ($request->items as $item) {
            if (!in_array($item['order_item_id'], $orderItemIds)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Sản phẩm không thuộc đơn hàng này'
                ], 400);
            }

            $orderItem = $order->items->find($item['order_item_id']);
            $returnedQuantity = $this->getReturnedQuantity($orderItem->id);
            
            if ($returnedQuantity + $item['quantity'] > $orderItem->quantity) {
                return response()->json([
                    'success' => false,
                    'message' => "Số lượng trả vượt quá số lượng đã mua cho sản phẩm {$orderItem->product->name}"
                ], 400);
            }
        }

        DB::beginTransaction();
        try {
            // Create order return
            $orderReturn = OrderReturn::create([
                'code' => $this->generateReturnCode(),
                'order_id' => $order->id,
                'customer_id' => $order->customer_id,
                'warehouse_id' => $order->warehouse_id,
                'created_by' => Auth::id(),
                'returned_at' => now(),
                'status' => OrderReturn::STATUS_PENDING,
                'return_reason' => $request->return_reason,
                'note' => $request->note,
            ]);

            $total = 0;

            // Create return items
            foreach ($request->items as $item) {
                $orderItem = $order->items->find($item['order_item_id']);
                
                if (!$orderItem) {
                    \Log::error('Order item not found', ['order_item_id' => $item['order_item_id']]);
                    throw new \Exception("Order item {$item['order_item_id']} not found");
                }
                
                \Log::info('Creating return item', [
                    'order_item' => $orderItem->toArray(),
                    'return_quantity' => $item['quantity']
                ]);
                
                $unitPrice = $orderItem->price ?? $orderItem->unit_price ?? 0;
                
                $returnItem = OrderReturnItem::create([
                    'order_return_id' => $orderReturn->id,
                    'order_item_id' => $orderItem->id,
                    'product_id' => $orderItem->product_id,
                    'quantity' => $item['quantity'],
                    'unit_price' => $unitPrice,
                    'return_reason' => $item['return_reason'] ?? null,
                ]);

                $total += $returnItem->quantity * $returnItem->unit_price;

                // Update warehouse stock - KHÔNG cập nhật ở đây, chỉ khi nhập kho
                // $this->updateWarehouseStock($order->warehouse_id, $orderItem->product_id, $item['quantity']);
            }

            // Update total
            $orderReturn->update(['total' => $total]);

            // Update order status to show it has returns
            $order->updateReturnStatus();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Tạo đơn trả hàng thành công',
                'data' => $orderReturn->load(['items.product'])
            ]);

        } catch (\Exception $e) {
            DB::rollback();
            return response()->json([
                'success' => false,
                'message' => 'Lỗi khi tạo đơn trả hàng: ' . $e->getMessage()
            ], 500);
        }
    }

    public function receive(OrderReturn $orderReturn)
    {
        if (!$orderReturn->canReceive()) {
            return response()->json([
                'success' => false,
                'message' => 'Không thể nhận hàng cho đơn trả hàng này'
            ], 400);
        }

        $orderReturn->updateStatus(OrderReturn::STATUS_RECEIVED, 'Đã nhận hàng trả về');

        return response()->json([
            'success' => true,
            'message' => 'Nhận hàng thành công',
            'data' => $orderReturn->fresh()
        ]);
    }

    public function warehouse(OrderReturn $orderReturn)
    {
        if (!$orderReturn->canWarehouse()) {
            return response()->json([
                'success' => false,
                'message' => 'Không thể nhập kho cho đơn trả hàng này'
            ], 400);
        }

        DB::beginTransaction();
        try {
            // Update warehouse stock when warehousing
            foreach ($orderReturn->items as $item) {
                $this->updateWarehouseStock(
                    $orderReturn->warehouse_id, 
                    $item->product_id, 
                    $item->quantity
                );
            }

            $orderReturn->updateStatus(OrderReturn::STATUS_WAREHOUSED, 'Đã nhập kho hàng trả về');

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Nhập kho thành công',
                'data' => $orderReturn->fresh()
            ]);

        } catch (\Exception $e) {
            DB::rollback();
            return response()->json([
                'success' => false,
                'message' => 'Lỗi khi nhập kho: ' . $e->getMessage()
            ], 500);
        }
    }

    public function refund(Request $request, OrderReturn $orderReturn)
    {
        $request->validate([
            'amount' => 'required|numeric|min:0',
            'note' => 'nullable|string',
        ]);

        // Validate can refund
        if (!$orderReturn->canRefund()) {
            return response()->json([
                'success' => false,
                'message' => 'Không thể hoàn tiền cho đơn trả hàng này'
            ], 400);
        }

        $refundAmount = $request->amount;
        $remainingRefund = $orderReturn->remaining_refund;

        if ($refundAmount > $remainingRefund) {
            return response()->json([
                'success' => false,
                'message' => 'Số tiền hoàn vượt quá số tiền cần hoàn'
            ], 400);
        }

        DB::beginTransaction();
        try {
            // Update order return
            $orderReturn->update([
                'refunded' => $orderReturn->refunded + $refundAmount,
                'status' => $orderReturn->refunded + $refundAmount >= $orderReturn->total 
                    ? OrderReturn::STATUS_REFUNDED 
                    : OrderReturn::STATUS_WAREHOUSED,
            ]);

            // CHỈ cập nhật dư nợ KHI hoàn tiền
            $this->handleCustomerDebtAdjustment($orderReturn->order, $refundAmount, $request->note);

            // Update order status after refund
            $orderReturn->order->updateReturnStatus();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Hoàn tiền thành công',
                'data' => $orderReturn->fresh()
            ]);

        } catch (\Exception $e) {
            DB::rollback();
            return response()->json([
                'success' => false,
                'message' => 'Lỗi khi hoàn tiền: ' . $e->getMessage()
            ], 500);
        }
    }

    public function approve(OrderReturn $orderReturn)
    {
        if (!$orderReturn->canApprove()) {
            return response()->json([
                'success' => false,
                'message' => 'Không thể duyệt đơn trả hàng này'
            ], 400);
        }

        $orderReturn->update([
            'status' => OrderReturn::STATUS_APPROVED,
            'approved_by' => Auth::id(),
            'approved_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Duyệt đơn trả hàng thành công',
            'data' => $orderReturn->fresh()
        ]);
    }

    public function cancel(OrderReturn $orderReturn)
    {
        if (!$orderReturn->canCancel()) {
            return response()->json([
                'success' => false,
                'message' => 'Không thể hủy đơn trả hàng này'
            ], 400);
        }

        DB::beginTransaction();
        try {
            // Revert warehouse stock changes
            foreach ($orderReturn->items as $item) {
                $this->updateWarehouseStock(
                    $orderReturn->warehouse_id, 
                    $item->product_id, 
                    -$item->quantity // Subtract back the returned quantity
                );
            }

            $orderReturn->update(['status' => OrderReturn::STATUS_CANCELLED]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Hủy đơn trả hàng thành công',
                'data' => $orderReturn->fresh()
            ]);

        } catch (\Exception $e) {
            DB::rollback();
            return response()->json([
                'success' => false,
                'message' => 'Lỗi khi hủy đơn trả hàng: ' . $e->getMessage()
            ], 500);
        }
    }

    // Private helper methods
    private function canCreateReturn(Order $order): bool
    {
        // Can only return from completed/delivered orders
        return in_array($order->status, ['completed', 'delivered']);
    }

    private function getReturnedQuantity($orderItemId): int
    {
        return OrderReturnItem::whereHas('orderReturn', function ($q) {
            $q->where('status', '!=', OrderReturn::STATUS_CANCELLED);
        })->where('order_item_id', $orderItemId)->sum('quantity');
    }

    private function updateWarehouseStock($warehouseId, $productId, $quantity)
    {
        $warehouseProduct = WarehouseProduct::firstOrCreate([
            'warehouse_id' => $warehouseId,
            'product_id' => $productId
        ], [
            'quantity' => 0
        ]);

        $warehouseProduct->increment('quantity', $quantity);

        // Sync product total quantity
        $product = \App\Models\Product::find($productId);
        if ($product) {
            $totalQuantity = WarehouseProduct::where('product_id', $productId)->sum('quantity');
            $product->update(['quantity' => $totalQuantity]);
        }
    }

    private function handleCustomerDebtAdjustment(Order $order, $refundAmount, $note = null)
    {
        // Check if customer has existing debt
        $existingDebt = CustomerDebt::where('customer_id', $order->customer_id)
            ->where('order_id', '!=', $order->id)
            ->sum('amount');

        if ($existingDebt > 0) {
            // Customer has debt - reduce debt first
            $debtReduction = min($existingDebt, $refundAmount);
            
            if ($debtReduction > 0) {
                CustomerDebt::create([
                    'customer_id' => $order->customer_id,
                    'order_id' => $order->id,
                    'amount' => -$debtReduction, // Negative amount reduces debt
                    'description' => "Giảm nợ từ hoàn trả hàng {$order->code}" . ($note ? " - {$note}" : ""),
                    'created_by' => Auth::id(),
                ]);
            }

            $remainingRefund = $refundAmount - $debtReduction;
            
            // If there's still money left after debt reduction, it can be refunded
            if ($remainingRefund > 0) {
                // Here you might want to create a cash receipt or other refund record
                // For now, we'll just note it in the description
                CustomerDebt::create([
                    'customer_id' => $order->customer_id,
                    'order_id' => $order->id,
                    'amount' => 0,
                    'description' => "Hoàn tiền {$remainingRefund} từ trả hàng {$order->code}" . ($note ? " - {$note}" : ""),
                    'created_by' => Auth::id(),
                ]);
            }
        } else {
            // No existing debt - full refund
            CustomerDebt::create([
                'customer_id' => $order->customer_id,
                'order_id' => $order->id,
                'amount' => 0,
                'description' => "Hoàn tiền {$refundAmount} từ trả hàng {$order->code}" . ($note ? " - {$note}" : ""),
                'created_by' => Auth::id(),
            ]);
        }
    }

    /**
     * Generate unique return code
     */
    private function generateReturnCode()
    {
        $prefix = 'TR' . date('Ymd');
        $lastReturn = OrderReturn::where('code', 'like', $prefix . '%')
            ->orderBy('code', 'desc')
            ->first();

        if ($lastReturn) {
            $lastNumber = (int) substr($lastReturn->code, -4);
            $newNumber = $lastNumber + 1;
        } else {
            $newNumber = 1;
        }

        return $prefix . str_pad($newNumber, 4, '0', STR_PAD_LEFT);
    }
}