<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderShipping;
use App\Models\ShippingProvider;
use App\Models\ShippingLog;
use App\Models\CustomerDebt;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ShippingController extends Controller
{
    /**
     * Danh sách tất cả đơn vận chuyển
     */
    public function index(Request $request)
    {
        $query = OrderShipping::with(['order.customer', 'provider'])
            ->orderBy('created_at', 'desc');

        // Filter by status
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        // Filter by provider
        if ($request->filled('provider_id')) {
            $query->where('provider_id', $request->provider_id);
        }

        // Filter by payment_by
        if ($request->filled('payment_by')) {
            $query->where('payment_by', $request->payment_by);
        }

        // Search
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('tracking_number', 'LIKE', "%{$search}%")
                  ->orWhereHas('order', function($q) use ($search) {
                      $q->where('code', 'LIKE', "%{$search}%")
                        ->orWhereHas('customer', function($q) use ($search) {
                            $q->where('name', 'LIKE', "%{$search}%")
                              ->orWhere('phone', 'LIKE', "%{$search}%");
                        });
                  });
            });
        }

        // Date range filter
        if ($request->filled('date_range')) {
            $days = (int)$request->date_range;
            if ($days > 0) {
                $query->where('created_at', '>=', now()->subDays($days));
            }
        }

        $shippings = $query->paginate($request->per_page ?? 20);

        return response()->json([
            'success' => true,
            'data' => $shippings->items(),
            'pagination' => [
                'current_page' => $shippings->currentPage(),
                'last_page' => $shippings->lastPage(),
                'per_page' => $shippings->perPage(),
                'total' => $shippings->total(),
                'from' => $shippings->firstItem(),
                'to' => $shippings->lastItem(),
            ]
        ]);
    }

    /**
     * Chi tiết đơn vận chuyển
     */
    public function show($id)
    {
        $shipping = OrderShipping::with(['order.customer', 'provider', 'logs'])
            ->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $shipping
        ]);
    }

    /**
     * Tạo đơn giao hàng cho order
     */
    /**
 * Tạo đơn giao hàng cho order
 */
public function createShipping(Request $request, $orderId)
{
    $request->validate([
        'provider_id' => 'required|exists:shipping_providers,id',
        'shipping_method' => 'required|string',
        'shipping_fee' => 'required|numeric|min:0',
        'payment_by' => 'required|in:sender,receiver',
        'delivery_address' => 'required|string',
        'delivery_phone' => 'required|string',
        'delivery_contact' => 'required|string',
        'pickup_address' => 'nullable|string',
        'pickup_phone' => 'nullable|string',
        'weight' => 'nullable|numeric|min:0',
        'dimensions' => 'nullable|string',
        'cod_amount' => 'nullable|numeric|min:0',
        'note' => 'nullable|string'
    ]);

    $order = Order::findOrFail($orderId);
    
    // Kiểm tra order đã có shipping chưa
    if ($order->shipping) {
        return response()->json([
            'success' => false,
            'message' => 'Đơn hàng đã có thông tin giao hàng'
        ], 422);
    }

    DB::beginTransaction();
    try {
        $shipping = OrderShipping::create([
            'order_id' => $orderId,
            'provider_id' => $request->provider_id,
            'shipping_method' => $request->shipping_method,
            'tracking_number' => $this->generateTrackingNumber(),
            'shipping_fee' => $request->shipping_fee,
            'payment_by' => $request->payment_by,
            'cost' => $request->cost ?? 0,
            'delivery_address' => $request->delivery_address,
            'delivery_phone' => $request->delivery_phone,
            'delivery_contact' => $request->delivery_contact,
            'pickup_address' => $request->pickup_address,
            'pickup_phone' => $request->pickup_phone,
            'weight' => $request->weight ?? 0,
            'dimensions' => $request->dimensions,
            'cod_amount' => $request->cod_amount ?? 0,
            'status' => 'pending',
            'note' => $request->note
        ]);

        // Tạo log đầu tiên
        ShippingLog::create([
            'order_shipping_id' => $shipping->id,
            'status' => 'pending',
            'description' => 'Đơn giao hàng được tạo',
            'logged_at' => now()
        ]);

        // Chỉ cập nhật status - không động đến total hay debt
        $order->update(['status' => 'shipping']);

        DB::commit();

        $shipping->load(['provider', 'logs']);

        return response()->json([
            'success' => true,
            'message' => 'Tạo đơn giao hàng thành công',
            'data' => $shipping
        ]);

    } catch (\Exception $e) {
        DB::rollback();
        return response()->json([
            'success' => false,
            'message' => 'Lỗi tạo đơn giao hàng: ' . $e->getMessage()
        ], 500);
    }
}

    /**
 * Confirm delivery - FIXED VERSION
 */
public function confirmDelivery(Request $request, $orderId, $shippingId)
{
    $request->validate([
        'location' => 'nullable|string',
        'description' => 'required|string'
    ]);

    $shipping = OrderShipping::findOrFail($shippingId);
    $order = $shipping->order;
    
    DB::beginTransaction();
    try {
        // Cập nhật shipping status
        $shipping->update([
            'status' => 'delivered',
            'actual_delivery' => now()
        ]);

        // Tạo log
        ShippingLog::create([
            'order_shipping_id' => $shipping->id,
            'status' => 'delivered',
            'location' => $request->location,
            'description' => $request->description,
            'logged_at' => now()
        ]);

        // Chỉ cập nhật order status - không tạo debt cho shipping fee
        $order->update(['status' => 'completed']);

        DB::commit();

        return response()->json([
            'success' => true,
            'message' => 'Xác nhận giao hàng thành công'
        ]);

    } catch (\Exception $e) {
        DB::rollback();
        return response()->json([
            'success' => false,
            'message' => 'Lỗi xác nhận giao hàng: ' . $e->getMessage()
        ], 500);
    }
}

    /**
     * Cập nhật trạng thái shipping
     */
    public function updateStatus(Request $request, $orderId, $shippingId)
    {
        $request->validate([
            'status' => 'required|in:pending,picked_up,in_transit,delivered,failed',
            'location' => 'nullable|string',
            'description' => 'required|string'
        ]);

        $shipping = OrderShipping::findOrFail($shippingId);
        
        DB::beginTransaction();
        try {
            // Cập nhật status
            $shipping->update(['status' => $request->status]);

            // Tạo log
            ShippingLog::create([
                'order_shipping_id' => $shipping->id,
                'status' => $request->status,
                'location' => $request->location,
                'description' => $request->description,
                'logged_at' => now()
            ]);

            // Cập nhật order status nếu delivered
            if ($request->status === 'delivered') {
                $shipping->order->update(['status' => 'delivered']);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Cập nhật trạng thái thành công'
            ]);

        } catch (\Exception $e) {
            DB::rollback();
            return response()->json([
                'success' => false,
                'message' => 'Lỗi cập nhật: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Lấy danh sách providers
     */
    public function getProviders()
    {
        $providers = ShippingProvider::active()->get();
        
        return response()->json([
            'success' => true,
            'data' => $providers
        ]);
    }

    /**
     * Generate tracking number
     */
    private function generateTrackingNumber()
    {
        return 'TK' . date('YmdHis') . rand(1000, 9999);
    }

    /**
 * Get shipping statistics
 */
public function getStats(Request $request)
{
    $startDate = $request->get('start_date', now()->startOfMonth());
    $endDate = $request->get('end_date', now()->endOfMonth());

    // Tổng thống kê
    $totalStats = OrderShipping::whereBetween('created_at', [$startDate, $endDate])
        ->selectRaw('
            COUNT(*) as total_orders,
            SUM(shipping_fee) as total_cost,
            COUNT(CASE WHEN status = "delivered" THEN 1 END) as delivered_orders,
            COUNT(CASE WHEN status IN ("pending", "picked_up", "in_transit") THEN 1 END) as in_progress_orders
        ')
        ->first();

    // Thống kê theo provider
    $providerStats = OrderShipping::with('provider')
        ->whereBetween('created_at', [$startDate, $endDate])
        ->selectRaw('
            provider_id,
            COUNT(*) as order_count,
            SUM(shipping_fee) as total_cost,
            COUNT(CASE WHEN status = "delivered" THEN 1 END) as delivered_count
        ')
        ->groupBy('provider_id')
        ->get()
        ->map(function ($item) {
            return [
                'provider_name' => $item->provider->name ?? 'N/A',
                'order_count' => $item->order_count,
                'total_cost' => $item->total_cost,
                'delivered_count' => $item->delivered_count,
                'delivery_rate' => $item->order_count > 0 ? round(($item->delivered_count / $item->order_count) * 100, 1) : 0
            ];
        });

    // Thống kê theo payment_by
    $paymentStats = OrderShipping::whereBetween('created_at', [$startDate, $endDate])
        ->selectRaw('
            payment_by,
            COUNT(*) as order_count,
            SUM(shipping_fee) as total_cost
        ')
        ->groupBy('payment_by')
        ->get()
        ->keyBy('payment_by');

    return response()->json([
        'success' => true,
        'data' => [
            'period' => [
                'start_date' => $startDate,
                'end_date' => $endDate
            ],
            'total' => $totalStats,
            'by_provider' => $providerStats,
            'by_payment' => [
                'sender' => $paymentStats->get('sender'),
                'receiver' => $paymentStats->get('receiver')
            ]
        ]
    ]);
}
/**
 * Get tracking information for order
 */
public function getTracking($orderId)
{
    $order = Order::findOrFail($orderId);
    
    $shipping = OrderShipping::with(['order.customer', 'provider', 'logs'])
        ->where('order_id', $orderId)
        ->first();
    
    if (!$shipping) {
        return response()->json([
            'success' => false,
            'message' => 'Không tìm thấy thông tin vận chuyển'
        ], 404);
    }
    
    return response()->json([
        'success' => true,
        'data' => [
            'shipping' => $shipping,
            'logs' => $shipping->logs
        ]
    ]);
}

/**
     * In đơn vận chuyển
     */
    public function printLabel($id)
    {
        $shipping = OrderShipping::with(['order.customer', 'provider'])
            ->findOrFail($id);

        return view('shipping.print-label', compact('shipping'));
    }

    /**
     * In nhiều đơn vận chuyển
     */
    public function printBulkLabels(Request $request)
    {
        $request->validate([
            'shipping_ids' => 'required|array',
            'shipping_ids.*' => 'exists:order_shipping,id'
        ]);

        $shippings = OrderShipping::with(['order.customer', 'provider'])
            ->whereIn('id', $request->shipping_ids)
            ->get();

        return view('shipping.print-bulk-labels', compact('shippings'));
    }
}