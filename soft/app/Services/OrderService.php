<?php

namespace App\Services;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderStatusHistory;
use App\Models\OrderPayment;
use App\Models\OrderShipping;
use App\Models\Customer;
use App\Models\Product;
use App\Models\WarehouseProduct;
use App\Models\CustomerDebt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Exception;

class OrderService
{
    const STATUS_PENDING = 'pending';
    const STATUS_CONFIRMED = 'confirmed';
    const STATUS_PROCESSING = 'processing';
    const STATUS_SHIPPING = 'shipping';
    const STATUS_DELIVERED = 'delivered';
    const STATUS_COMPLETED = 'completed';
    const STATUS_CANCELLED = 'cancelled';
    const STATUS_REFUNDED = 'refunded';

    const ALLOWED_TRANSITIONS = [
        self::STATUS_PENDING => [self::STATUS_CONFIRMED, self::STATUS_CANCELLED],
        self::STATUS_CONFIRMED => [self::STATUS_PROCESSING, self::STATUS_CANCELLED],
        self::STATUS_PROCESSING => [self::STATUS_SHIPPING, self::STATUS_CANCELLED],
        self::STATUS_SHIPPING => [self::STATUS_DELIVERED, self::STATUS_CANCELLED],
        self::STATUS_DELIVERED => [self::STATUS_COMPLETED],
        self::STATUS_COMPLETED => [self::STATUS_REFUNDED],
        self::STATUS_CANCELLED => [],
        self::STATUS_REFUNDED => []
    ];

    public function createOrder(array $data, ?int $userId = null): Order
    {
        DB::beginTransaction();
        try {
            $this->validateOrderData($data);
            $this->validateStockAvailability($data['warehouse_id'], $data['items']);
            
            $totals = $this->calculateOrderTotals($data['items']);
            $status = $data['status'] ?? self::STATUS_PENDING;
            $this->validateStatusTransition(null, $status);
            
            $orderCode = $this->generateOrderCode();

            $order = Order::create([
                'code' => $orderCode,
                'customer_id' => $data['customer_id'],
                'warehouse_id' => $data['warehouse_id'],
                'cashier_id' => $userId,
                'total' => $totals['total'],
                'subtotal' => $totals['subtotal'],
                'discount_amount' => $totals['discount'] ?? 0,
                'vat_amount' => $totals['vat'] ?? 0,
                'paid' => 0,
                'debt' => $totals['total'],
                'status' => $status,
                'source' => $data['source'] ?? 'Web',
                'priority' => $data['priority'] ?? 'normal',
                'delivery_address' => $data['delivery_address'] ?? null,
                'delivery_phone' => $data['delivery_phone'] ?? null,
                'delivery_contact' => $data['delivery_contact'] ?? null,
                'tags' => $data['tags'] ?? null,
                'note' => $data['note'] ?? null,
                'ordered_at' => now(),
                'created_by' => $userId,
            ]);

            $this->createOrderItems($order->id, $data['items']);
            
            $this->createStatusHistory($order->id, null, $status, 
                $status === self::STATUS_CONFIRMED ? 'Đơn hàng được tạo và duyệt tự động' : 'Đơn hàng được tạo',
                $userId
            );

            if ($totals['total'] > 0) {
                $this->createCustomerDebt($order, $userId);
            }

            DB::commit();
            return $order->load(['customer', 'warehouse', 'items.product']);

        } catch (Exception $e) {
            DB::rollback();
            Log::error('Error creating order: ' . $e->getMessage());
            throw $e;
        }
    }

    public function updateOrderStatus(Order $order, string $newStatus, ?string $note = null, ?int $userId = null): Order
    {
        DB::beginTransaction();
        try {
            $oldStatus = $order->status;
            $this->validateStatusTransition($oldStatus, $newStatus);

            $order->update(['status' => $newStatus]);
            $this->createStatusHistory($order->id, $oldStatus, $newStatus, $note, $userId);

            DB::commit();
            return $order;

        } catch (Exception $e) {
            DB::rollback();
            Log::error('Error updating order status: ' . $e->getMessage());
            throw $e;
        }
    }

    private function validateOrderData(array $data): void
    {
        if (empty($data['customer_id'])) {
            throw new Exception('Thiếu thông tin khách hàng');
        }
        if (empty($data['warehouse_id'])) {
            throw new Exception('Thiếu thông tin cửa hàng');
        }
        if (empty($data['items']) || !is_array($data['items'])) {
            throw new Exception('Thiếu thông tin sản phẩm');
        }
    }

    private function validateStockAvailability(int $warehouseId, array $items): void
    {
        foreach ($items as $item) {
            $warehouseProduct = WarehouseProduct::where('warehouse_id', $warehouseId)
                ->where('product_id', $item['product_id'])
                ->first();

            if (!$warehouseProduct || $warehouseProduct->quantity < $item['quantity']) {
                $product = Product::find($item['product_id']);
                throw new Exception("Sản phẩm {$product->name} không đủ tồn kho");
            }
        }
    }

    private function calculateOrderTotals(array $items): array
    {
        $subtotal = 0;
        foreach ($items as $item) {
            $subtotal += $item['quantity'] * $item['price'];
        }
        return [
            'subtotal' => $subtotal,
            'discount' => 0,
            'vat' => 0,
            'total' => $subtotal
        ];
    }

    private function validateStatusTransition(?string $fromStatus, string $toStatus): void
    {
        if ($fromStatus === null) {
            if (!in_array($toStatus, [self::STATUS_PENDING, self::STATUS_CONFIRMED])) {
                throw new Exception('Đơn hàng mới chỉ có thể ở trạng thái chờ duyệt hoặc đã duyệt');
            }
            return;
        }

        $allowedStatuses = self::ALLOWED_TRANSITIONS[$fromStatus] ?? [];
        if (!in_array($toStatus, $allowedStatuses)) {
            throw new Exception("Không thể chuyển từ trạng thái '{$fromStatus}' sang '{$toStatus}'");
        }
    }

    private function createOrderItems(int $orderId, array $items): void
    {
        foreach ($items as $item) {
            $product = Product::find($item['product_id']);
            
            OrderItem::create([
                'order_id' => $orderId,
                'product_id' => $item['product_id'],
                'quantity' => $item['quantity'],
                'price' => $item['price'],
                'total' => $item['quantity'] * $item['price'],
                'sku' => $product->sku,
                'product_name' => $product->name,
                'unit_name' => $product->unit->name ?? 'Chai',
                'cost_price' => $product->cost_price,
                'profit' => ($item['price'] - $product->cost_price) * $item['quantity'],
            ]);
        }
    }

    private function createStatusHistory(int $orderId, ?string $fromStatus, string $toStatus, ?string $note, ?int $userId): void
    {
        OrderStatusHistory::create([
            'order_id' => $orderId,
            'from_status' => $fromStatus,
            'to_status' => $toStatus,
            'note' => $note,
            'changed_by' => $userId,
            'changed_at' => now(),
        ]);
    }

    private function generateOrderCode(): string
    {
        $prefix = 'SON' . date('ymd');
        $lastOrder = Order::where('code', 'LIKE', $prefix . '%')
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

    private function createCustomerDebt(Order $order, ?int $userId): void
    {
        CustomerDebt::create([
            'order_id' => $order->id,
            'customer_id' => $order->customer_id,
            'ref_code' => 'CD' . date('YmdHis') . rand(100, 999),
            'amount' => $order->total,
            'debt_total' => 0,
            'note' => 'Công nợ từ đơn hàng ' . $order->code,
            'created_by' => $userId,
            'recorded_at' => now(),
        ]);
    }

    public static function getStatusText(string $status): string
    {
        $statusMap = [
            self::STATUS_PENDING => 'Chờ duyệt',
            self::STATUS_CONFIRMED => 'Đã xác nhận',
            self::STATUS_PROCESSING => 'Đang xử lý',
            self::STATUS_SHIPPING => 'Đang giao hàng',
            self::STATUS_DELIVERED => 'Đã giao hàng',
            self::STATUS_COMPLETED => 'Hoàn thành',
            self::STATUS_CANCELLED => 'Đã hủy',
            self::STATUS_REFUNDED => 'Đã hoàn tiền'
        ];

        return $statusMap[$status] ?? $status;
    }

    public static function canEdit(Order $order): bool
    {
        return $order->status === self::STATUS_PENDING;
    }

    public static function canDelete(Order $order): bool
    {
        return in_array($order->status, [self::STATUS_PENDING, self::STATUS_CANCELLED]);
    }

    public static function canCancel(Order $order): bool
    {
        return in_array($order->status, [
            self::STATUS_PENDING, 
            self::STATUS_CONFIRMED, 
            self::STATUS_PROCESSING,
            self::STATUS_SHIPPING
        ]);
    }
}