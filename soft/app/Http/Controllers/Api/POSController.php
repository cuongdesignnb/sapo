<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Customer;
use App\Models\Product;
use App\Models\WarehouseProduct;
use App\Models\CustomerDebt;
use App\Models\Warehouse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class POSController extends Controller
{
    /**
     * Lấy dữ liệu cần thiết cho POS
     */
    public function index()
    {
        try {
            $data = [
                'warehouses' => Warehouse::where('status', 'active')->get(['id', 'code', 'name']),
                'default_warehouse' => Warehouse::where('status', 'active')->first(),
                'payment_methods' => [
                    ['value' => 'cash', 'label' => 'Tiền mặt'],
                    ['value' => 'transfer', 'label' => 'Chuyển khoản'],
                    ['value' => 'card', 'label' => 'Thẻ tín dụng'],
                    ['value' => 'wallet', 'label' => 'Ví điện tử'],
                    ['value' => 'debt', 'label' => 'Công nợ']
                ]
            ];

            return response()->json([
                'success' => true,
                'data' => $data
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Lỗi khi tải dữ liệu POS: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Tìm kiếm sản phẩm cho POS
     */
    public function searchProducts(Request $request)
    {
        $request->validate([
            'search' => 'required|string|min:1',
            'warehouse_id' => 'required|exists:warehouses,id'
        ]);

        try {
            $search = $request->search;
            $warehouseId = $request->warehouse_id;

            $products = Product::select([
                'products.id',
                'products.sku',
                'products.name',
                'products.barcode',
                'products.retail_price',
                'products.category_name',
                'products.brand_name',
                'products.weight',
                'warehouse_products.quantity as stock',
                'warehouse_products.cost'
            ])
            ->join('warehouse_products', 'products.id', '=', 'warehouse_products.product_id')
            ->where('warehouse_products.warehouse_id', $warehouseId)
            ->where('products.status', 'active')
            ->where('warehouse_products.quantity', '>', 0)
            ->where(function($query) use ($search) {
                $query->where('products.name', 'LIKE', "%{$search}%")
                      ->orWhere('products.sku', 'LIKE', "%{$search}%")
                      ->orWhere('products.barcode', 'LIKE', "%{$search}%");
            })
            ->orderBy('products.name')
            ->limit(20)
            ->get();

            return response()->json([
                'success' => true,
                'data' => $products
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Lỗi khi tìm sản phẩm: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Tìm kiếm khách hàng cho POS
     */
    public function searchCustomers(Request $request)
    {
        $request->validate([
            'search' => 'required|string|min:1'
        ]);

        try {
            $search = $request->search;

            $customers = Customer::select([
                'id',
                'code',
                'name',
                'phone',
                'email',
                'total_debt',
                'customer_type',
                'group_id'
            ])
            ->with(['group:id,name,discount_percent'])
            ->where('status', 'active')
            ->where(function($query) use ($search) {
                $query->where('name', 'LIKE', "%{$search}%")
                      ->orWhere('phone', 'LIKE', "%{$search}%")
                      ->orWhere('code', 'LIKE', "%{$search}%")
                      ->orWhere('email', 'LIKE', "%{$search}%");
            })
            ->orderBy('name')
            ->limit(10)
            ->get();

            return response()->json([
                'success' => true,
                'data' => $customers
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Lỗi khi tìm khách hàng: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Tạo đơn hàng từ POS
     */
    public function createOrder(Request $request)
    {
        $request->validate([
            'customer_id' => 'nullable|exists:customers,id',
            'warehouse_id' => 'required|exists:warehouses,id',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.price' => 'required|numeric|min:0',
            'items.*.discount_percent' => 'nullable|numeric|min:0|max:100',
            'items.*.discount_amount' => 'nullable|numeric|min:0',
            'subtotal' => 'required|numeric|min:0',
            'discount_percent' => 'nullable|numeric|min:0|max:100',
            'discount_amount' => 'nullable|numeric|min:0',
            'vat_percent' => 'nullable|numeric|min:0|max:100',
            'vat_amount' => 'nullable|numeric|min:0',
            'total' => 'required|numeric|min:0',
            'paid' => 'nullable|numeric|min:0',
            'payment_method' => 'nullable|in:cash,transfer,card,wallet,debt',
            'note' => 'nullable|string|max:1000',
            'print_invoice' => 'nullable|boolean'
        ]);

        DB::beginTransaction();
        try {
            // Kiểm tra tồn kho
            foreach ($request->items as $item) {
                $warehouseProduct = WarehouseProduct::where('warehouse_id', $request->warehouse_id)
                    ->where('product_id', $item['product_id'])
                    ->first();

                if (!$warehouseProduct || $warehouseProduct->quantity < $item['quantity']) {
                    $product = Product::find($item['product_id']);
                    throw ValidationException::withMessages([
                        'items' => "Sản phẩm {$product->name} không đủ tồn kho. Còn lại: " . ($warehouseProduct->quantity ?? 0)
                    ]);
                }
            }

            // Tạo hoặc chọn khách hàng
            if (!$request->customer_id) {
                $customer = Customer::firstOrCreate(
                    ['code' => 'KHBLE'],
                    [
                        'name' => 'Khách hàng bán lẻ',
                        'phone' => '',
                        'email' => '',
                        'status' => 'active',
                        'customer_type' => 'Bán lẻ',
                        'person_in_charge' => auth()->user()->name ?? 'System',
                        'group_id' => 1
                    ]
                );
                $customerId = $customer->id;
            } else {
                $customerId = $request->customer_id;
            }

            // Tạo mã đơn hàng
            $orderCode = $this->generateOrderCode();

            // Tính toán
            $paid = $request->paid ?? 0;
            $debt = max(0, $request->total - $paid);

            // Tạo đơn hàng
            $order = Order::create([
                'code' => $orderCode,
                'customer_id' => $customerId,
                'warehouse_id' => $request->warehouse_id,
                'cashier_id' => auth()->id(),
                'total' => $request->total,
                'subtotal' => $request->subtotal,
                'discount_percent' => $request->discount_percent ?? 0,
                'discount_amount' => $request->discount_amount ?? 0,
                'vat_percent' => $request->vat_percent ?? 0,
                'vat_amount' => $request->vat_amount ?? 0,
                'paid' => $paid,
                'debt' => $debt,
                'status' => 'completed',
                'source' => 'POS',
                'priority' => 'normal',
                'note' => $request->note,
                'ordered_at' => now(),
                'created_by' => auth()->id(),
            ]);

            // Tạo order items và cập nhật tồn kho
            foreach ($request->items as $item) {
                $product = Product::find($item['product_id']);
                
                OrderItem::create([
                    'order_id' => $order->id,
                    'product_id' => $item['product_id'],
                    'quantity' => $item['quantity'],
                    'price' => $item['price'],
                    'discount_percent' => $item['discount_percent'] ?? 0,
                    'discount_amount' => $item['discount_amount'] ?? 0,
                    'total' => $item['quantity'] * $item['price'] - ($item['discount_amount'] ?? 0),
                    'sku' => $product->sku,
                    'product_name' => $product->name,
                    'unit_name' => 'Chai',
                    'cost_price' => $product->cost_price,
                    'profit' => ($item['price'] - $product->cost_price) * $item['quantity'],
                ]);

                // Cập nhật tồn kho
                $warehouseProduct = WarehouseProduct::where('warehouse_id', $request->warehouse_id)
                    ->where('product_id', $item['product_id'])
                    ->first();
                
                $warehouseProduct->quantity -= $item['quantity'];
                $warehouseProduct->last_export_date = now();
                $warehouseProduct->save();
            }

            // Tạo công nợ nếu có
            if ($debt > 0) {
                CustomerDebt::create([
                    'customer_id' => $customerId,
                    'order_id' => $order->id,
                    'amount' => $debt,
                    'note' => "Công nợ từ đơn hàng POS {$orderCode}",
                    'created_by' => auth()->id(),
                    'recorded_at' => now()
                ]);
            }

            // Cập nhật tổng chi tiêu của khách hàng
            $customer = Customer::find($customerId);
            $customer->total_spend += $request->total;
            $customer->total_orders += 1;
            $customer->save();

            DB::commit();

            $order->load([
                'customer:id,code,name,phone',
                'warehouse:id,code,name',
                'cashier:id,name',
                'items.product:id,sku,name'
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Tạo đơn hàng thành công',
                'data' => [
                    'order' => $order,
                    'print_data' => $request->print_invoice ? $this->generatePrintData($order) : null
                ]
            ], 201);

        } catch (\Exception $e) {
            DB::rollback();
            
            if ($e instanceof ValidationException) {
                throw $e;
            }
            
            return response()->json([
                'success' => false,
                'message' => 'Có lỗi xảy ra: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Tạo mã đơn hàng POS
     */
    private function generateOrderCode()
    {
        $prefix = 'POS' . date('ymd');
        $lastOrder = Order::where('code', 'LIKE', $prefix . '%')
            ->where('source', 'POS')
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

    /**
     * Tạo dữ liệu in hóa đơn
     */
    private function generatePrintData($order)
    {
        return [
            'order_code' => $order->code,
            'date' => $order->created_at->format('d/m/Y H:i:s'),
            'cashier' => $order->cashier->name ?? 'N/A',
            'warehouse' => $order->warehouse->name ?? 'N/A',
            'customer' => [
                'name' => $order->customer->name ?? 'Khách hàng bán lẻ',
                'phone' => $order->customer->phone ?? '',
                'code' => $order->customer->code ?? ''
            ],
            'items' => $order->items->map(function ($item) {
                return [
                    'name' => $item->product_name,
                    'sku' => $item->sku,
                    'quantity' => $item->quantity,
                    'price' => $item->price,
                    'discount' => $item->discount_amount ?? 0,
                    'total' => $item->total
                ];
            }),
            'summary' => [
                'subtotal' => $order->subtotal ?? $order->total,
                'discount_percent' => $order->discount_percent ?? 0,
                'discount_amount' => $order->discount_amount ?? 0,
                'vat_percent' => $order->vat_percent ?? 0,
                'vat_amount' => $order->vat_amount ?? 0,
                'total' => $order->total,
                'paid' => $order->paid,
                'change' => max(0, $order->paid - $order->total),
                'debt' => $order->debt
            ]
        ];
    }
}