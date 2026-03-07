<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderPayment;
use App\Models\OrderShipping;
use App\Models\OrderStatusHistory;
use App\Models\Product;
use App\Models\ProductSerial;
use App\Models\ShippingProvider;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WarehouseProduct;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class OrderController extends Controller
{
    // Quy trình mới đơn giản hóa 5 bước
    const STATUS_ORDERED = 'ordered';           // Bước 1: Tạo đơn hàng

    const STATUS_APPROVED = 'approved';         // Bước 2: Duyệt đơn hàng

    const STATUS_SHIPPING_CREATED = 'shipping_created'; // Bước 3: Tạo đơn vận chuyển

    const STATUS_DELIVERED = 'delivered';       // Bước 4: Xuất kho

    const STATUS_COMPLETED = 'completed';       // Bước 5: Thanh toán hoàn tất

    const STATUS_CANCELLED = 'cancelled';       // Trạng thái hủy

    // Shipping methods
    const SHIPPING_THIRD_PARTY = 'third_party';    // Gửi cho bên giao hàng

    const SHIPPING_SELF_DELIVERY = 'self_delivery'; // Tự giao hàng

    const SHIPPING_PICKUP = 'pickup';               // Nhận tại cửa hàng

    /**
     * Danh sách đơn hàng
     */
    public function index(Request $request)
    {
        $query = Order::with([
            'customer:id,code,name,phone',
            'warehouse:id,name',
            'cashier:id,name',
            'createdBy:id,name',
        ])
            ->orderBy('created_at', 'desc');

        // Filter theo warehouse
        if ($request->warehouse_id) {
            $query->where('warehouse_id', $request->warehouse_id);
        }

        // Filter theo chi nhánh (nếu có)
        if ($request->branch_id) {
            $query->where('branch_id', $request->branch_id);
        }

        // Filter theo trạng thái
        if ($request->status) {
            $query->where('status', $request->status);
        }

        // Filter theo nhân viên tạo đơn
        if ($request->created_by) {
            $query->where('created_by', $request->created_by);
        }

        // Filter theo nhân viên phụ trách (cashier)
        if ($request->cashier_id) {
            $query->where('cashier_id', $request->cashier_id);
        }

        // Filter theo khách hàng
        if ($request->customer_id) {
            $query->where('customer_id', $request->customer_id);
        }

        // Filter theo sản phẩm (lọc theo items)
        if ($request->product_id) {
            $productId = $request->product_id;
            $query->whereHas('items', function ($q) use ($productId) {
                $q->where('product_id', $productId);
            });
        }

        // Filter theo đối tác vận chuyển
        if ($request->shipping_provider_id) {
            $providerId = $request->shipping_provider_id;
            $query->whereHas('shipping', function ($q) use ($providerId) {
                $q->where('provider_id', $providerId);
            });
        }

        // Filter theo trạng thái giao hàng (status trong order_shipping)
        if ($request->shipping_status) {
            $shippingStatus = $request->shipping_status;
            $query->whereHas('shipping', function ($q) use ($shippingStatus) {
                $q->where('status', $shippingStatus);
            });
        }

        // Filter theo trạng thái thanh toán (derive từ paid/debt)
        if ($request->payment_status) {
            switch ($request->payment_status) {
                case 'unpaid':
                    $query->where(function ($q) {
                        $q->whereNull('paid')->orWhere('paid', '<=', 0);
                    });
                    break;
                case 'partial':
                    $query->where('paid', '>', 0)->where('debt', '>', 0);
                    break;
                case 'paid':
                    $query->where(function ($q) {
                        $q->whereNull('debt')->orWhere('debt', '<=', 0);
                    });
                    break;
            }
        }

        // Tìm kiếm
        if ($request->search) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('code', 'LIKE', "%{$search}%")
                    ->orWhereHas('customer', function ($q) use ($search) {
                        $q->where('name', 'LIKE', "%{$search}%")
                            ->orWhere('phone', 'LIKE', "%{$search}%");
                    });
            });
        }

        // Filter theo ngày
        if ($request->date_from) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }
        if ($request->date_to) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        // Filter theo date range
        if ($request->date_range) {
            $days = (int) $request->date_range;
            if ($days > 0) {
                $query->where('created_at', '>=', now()->subDays($days));
            }
        }

        $orders = $query->paginate($request->per_page ?? 20);

        return response()->json([
            'success' => true,
            'data' => $orders->items(),
            'pagination' => [
                'current_page' => $orders->currentPage(),
                'last_page' => $orders->lastPage(),
                'per_page' => $orders->perPage(),
                'total' => $orders->total(),
                'from' => $orders->firstItem(),
                'to' => $orders->lastItem(),
            ],
        ]);
    }

    /**
     * Dữ liệu cho bộ lọc (dropdown options)
     */
    public function filterData(Request $request)
    {
        $limit = (int) ($request->get('limit') ?? 200);
        if ($limit <= 0 || $limit > 500) {
            $limit = 200;
        }

        $warehouses = Warehouse::query()
            ->select(['id', 'name'])
            ->orderBy('name')
            ->limit($limit)
            ->get();

        $users = User::query()
            ->select(['id', 'name'])
            ->orderBy('name')
            ->limit($limit)
            ->get();

        $customers = Customer::query()
            ->select(['id', 'name', 'phone'])
            ->orderBy('name')
            ->limit($limit)
            ->get();

        $products = Product::query()
            ->select(['id', 'name', 'sku'])
            ->orderBy('name')
            ->limit($limit)
            ->get();

        $shippingProviders = ShippingProvider::query()
            ->select(['id', 'name'])
            ->when($request->filled('only_active'), function ($q) {
                $q->where('status', 'active');
            })
            ->orderBy('name')
            ->limit($limit)
            ->get();

        return response()->json([
            'success' => true,
            'data' => [
                'warehouses' => $warehouses,
                'users' => $users,
                'customers' => $customers,
                'products' => $products,
                'shipping_providers' => $shippingProviders,
            ],
        ]);
    }

    /**
     * Chi tiết đơn hàng
     */
    public function show($id)
    {
        $order = Order::with([
            'customer:id,code,name,phone,email',
            'warehouse:id,code,name',
            'cashier:id,name,email',
            'items.product:id,sku,name,retail_price',
            'statusHistory.user:id,name',
            'payments',
            'shipping',
            'returns' => function ($query) {
                $query->where('status', '!=', 'cancelled')
                    ->with('items.product:id,sku,name');
            },
        ])->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $order,
        ]);
    }

    /**
     * Tạo đơn hàng mới
     */
    /**
     * BƯỚC 1: Tạo đơn hàng mới (đơn giản hóa)
     */
    public function store(Request $request)
    {
        $request->validate([
            'customer_id' => 'required|exists:customers,id',
            'warehouse_id' => 'required|exists:warehouses,id',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.price' => 'required|numeric|min:0',
            'items.*.serial_ids' => 'nullable|array',
            'items.*.serial_ids.*' => 'nullable|integer|exists:product_serials,id',
            'note' => 'nullable|string|max:1000',
            'delivery_address' => 'nullable|string',
            'delivery_phone' => 'nullable|string',
            'delivery_contact' => 'nullable|string',
            'tags' => 'nullable|string',
            'priority' => 'nullable|in:low,normal,high,urgent',
        ]);

        DB::beginTransaction();
        try {
            // Kiểm tra tồn kho + Serial validation
            foreach ($request->items as $item) {
                $warehouseProduct = WarehouseProduct::where('warehouse_id', $request->warehouse_id)
                    ->where('product_id', $item['product_id'])
                    ->first();

                if (! $warehouseProduct || $warehouseProduct->quantity < $item['quantity']) {
                    $product = Product::find($item['product_id']);
                    throw ValidationException::withMessages([
                        'items' => "Sản phẩm {$product->name} không đủ tồn kho. Còn lại: ".($warehouseProduct->quantity ?? 0),
                    ]);
                }

                // Validate serial selection for serial-tracked products
                $product = Product::find($item['product_id']);
                if ($product && $product->track_serial) {
                    $serialIds = $item['serial_ids'] ?? [];
                    if (empty($serialIds)) {
                        throw ValidationException::withMessages([
                            'items' => "Sản phẩm '{$product->name}' yêu cầu chọn Serial/IMEI.",
                        ]);
                    }
                    if (count($serialIds) !== (int) $item['quantity']) {
                        throw ValidationException::withMessages([
                            'items' => "Sản phẩm '{$product->name}': Số serial đã chọn (" . count($serialIds) . ") phải bằng số lượng ({$item['quantity']})",
                        ]);
                    }
                    // Verify serials are available in the selected warehouse
                    $availableCount = ProductSerial::whereIn('id', $serialIds)
                        ->where('product_id', $item['product_id'])
                        ->where('warehouse_id', $request->warehouse_id)
                        ->where('status', ProductSerial::STATUS_IN_STOCK)
                        ->count();
                    if ($availableCount !== count($serialIds)) {
                        throw ValidationException::withMessages([
                            'items' => "Sản phẩm '{$product->name}': Một số serial không khả dụng trong kho đã chọn.",
                        ]);
                    }
                }
            }

            // Tạo mã đơn hàng
            $orderCode = $this->generateOrderCode();

            // Tính tổng tiền (CHỈ ITEMS - KHÔNG BAO GỒM SHIPPING)
            $total = collect($request->items)->sum(function ($item) {
                return $item['quantity'] * $item['price'];
            });

            $paid = $request->paid ?? 0;
            $debt = max(0, $total - $paid);

            // Tạo đơn hàng với trạng thái "ORDERED" (Đặt hàng)
            $order = Order::create([
                'code' => $orderCode,
                'customer_id' => $request->customer_id,
                'warehouse_id' => $request->warehouse_id,
                'cashier_id' => auth()->id(),
                'total' => $total,
                'paid' => 0, // Không thanh toán ngay khi tạo đơn
                'debt' => $total, // Toàn bộ số tiền là nợ
                'status' => self::STATUS_ORDERED, // Trạng thái: Đặt hàng
                'source' => 'Web',
                'priority' => $request->priority ?? 'normal',
                'delivery_address' => $request->delivery_address,
                'delivery_phone' => $request->delivery_phone,
                'delivery_contact' => $request->delivery_contact,
                'tags' => $request->tags,
                'note' => $request->note,
                'ordered_at' => now(),
                'created_by' => auth()->id(),
            ]);

            // Tạo order items
            foreach ($request->items as $item) {
                $product = Product::find($item['product_id']);

                $orderItem = OrderItem::create([
                    'order_id' => $order->id,
                    'product_id' => $item['product_id'],
                    'quantity' => $item['quantity'],
                    'price' => $item['price'],
                    'total' => $item['quantity'] * $item['price'],
                    'sku' => $product->sku,
                    'product_name' => $product->name,
                    'unit_name' => 'Chai',
                    'cost_price' => $product->cost_price,
                    'profit' => ($item['price'] - $product->cost_price) * $item['quantity'],
                    'note' => $item['note'] ?? null,
                ]);

                // Mark serials as sold for serial-tracked products
                if ($product->track_serial && !empty($item['serial_ids'])) {
                    foreach ($item['serial_ids'] as $serialId) {
                        $serial = ProductSerial::find($serialId);
                        if ($serial) {
                            $serial->markAsSold($orderItem->id, auth()->id());
                        }
                    }
                }
            }

            // Tạo status history
            OrderStatusHistory::create([
                'order_id' => $order->id,
                'from_status' => null,
                'to_status' => self::STATUS_ORDERED,
                'note' => 'Đơn hàng được tạo - Chờ duyệt',
                'changed_by' => auth()->id(),
                'changed_at' => now(),
            ]);

            // Tạo customer debt (toàn bộ số tiền đơn hàng)
            \App\Models\CustomerDebt::create([
                'order_id' => $order->id,
                'customer_id' => $request->customer_id,
                'ref_code' => 'CD'.date('YmdHis').rand(100, 999),
                'amount' => $total,
                'debt_total' => 0, // Sẽ được tính tự động trong model
                'note' => 'Công nợ từ đơn hàng '.$order->code,
                'created_by' => auth()->id(),
                'recorded_at' => now(),
            ]);

            DB::commit();

            $order->load(['customer', 'warehouse', 'items.product']);

            return response()->json([
                'success' => true,
                'message' => 'Tạo đơn hàng thành công - Chờ duyệt',
                'data' => $order,
                'next_action' => 'approve', // Hành động tiếp theo
            ], 201);

        } catch (\Exception $e) {
            DB::rollback();

            if ($e instanceof ValidationException) {
                throw $e;
            }

            return response()->json([
                'success' => false,
                'message' => 'Có lỗi xảy ra: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Cập nhật đơn hàng
     */
    public function update(Request $request, $id)
    {
        $order = Order::findOrFail($id);

        // Chỉ cho phép sửa đơn vừa tạo (ordered hoặc pending cũ)
        if (! in_array($order->status, ['pending', self::STATUS_ORDERED])) {
            return response()->json([
                'success' => false,
                'message' => 'Chỉ có thể sửa đơn hàng ở trạng thái vừa tạo',
            ], 422);
        }

        $request->validate([
            'customer_id' => 'required|exists:customers,id',
            'warehouse_id' => 'required|exists:warehouses,id',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.price' => 'required|numeric|min:0',
            'note' => 'nullable|string|max:1000',
            'paid' => 'nullable|numeric|min:0',
            'delivery_address' => 'nullable|string',
            'delivery_phone' => 'nullable|string',
            'delivery_contact' => 'nullable|string',
            'tags' => 'nullable|string',
            'priority' => 'nullable|in:low,normal,high,urgent',
        ]);

        DB::beginTransaction();
        try {
            // Lưu thông tin cũ để xử lý debt
            $oldDebt = $order->debt;
            $oldCustomerId = $order->customer_id;

            // Tính tổng tiền mới
            $total = collect($request->items)->sum(function ($item) {
                return $item['quantity'] * $item['price'];
            });

            $paid = $request->paid ?? 0;
            $newDebt = max(0, $total - $paid);

            // Cập nhật đơn hàng
            $order->update([
                'customer_id' => $request->customer_id,
                'warehouse_id' => $request->warehouse_id,
                'total' => $total,
                'paid' => $paid,
                'debt' => $newDebt,
                'delivery_address' => $request->delivery_address,
                'delivery_phone' => $request->delivery_phone,
                'delivery_contact' => $request->delivery_contact,
                'tags' => $request->tags,
                'note' => $request->note,
                'priority' => $request->priority ?? 'normal',
            ]);

            // Xóa items cũ và tạo lại
            $order->items()->delete();

            foreach ($request->items as $item) {
                $product = Product::find($item['product_id']);

                OrderItem::create([
                    'order_id' => $order->id,
                    'product_id' => $item['product_id'],
                    'quantity' => $item['quantity'],
                    'price' => $item['price'],
                    'total' => $item['quantity'] * $item['price'],
                    'sku' => $product->sku,
                    'product_name' => $product->name,
                    'unit_name' => 'Chai',
                    'cost_price' => $product->cost_price,
                    'profit' => ($item['price'] - $product->cost_price) * $item['quantity'],
                    'note' => $item['note'] ?? null,
                ]);
            }

            // XỬ LÝ CÔNG NỢ KHI SỬA ĐỠN HÀNG
            if ($oldCustomerId == $request->customer_id) {
                // Cùng khách hàng - cập nhật debt hiện có
                $debtDifference = $newDebt - $oldDebt;

                if ($debtDifference != 0) {
                    // Tìm record debt của đơn hàng này
                    $existingDebt = \App\Models\CustomerDebt::where('order_id', $order->id)->first();

                    if ($existingDebt) {
                        // Cập nhật record debt cũ
                        $customer = Customer::find($request->customer_id);

                        // Cập nhật tổng nợ của khách hàng
                        $customer->total_debt = $customer->total_debt - $oldDebt + $newDebt;
                        $customer->save();

                        if ($newDebt > 0) {
                            // Cập nhật record debt hiện có
                            $existingDebt->update([
                                'amount' => $newDebt,
                                'debt_total' => $customer->total_debt,
                                'note' => "Công nợ từ đơn hàng {$order->code} (đã cập nhật lúc ".now()->format('d/m/Y H:i').')',
                                'recorded_at' => now(),
                            ]);
                        } else {
                            // Nếu không còn nợ thì xóa record debt
                            $existingDebt->delete();
                        }
                    } elseif ($newDebt > 0) {
                        // Tạo record debt mới nếu chưa có và có nợ
                        $customer = Customer::find($request->customer_id);
                        $customer->total_debt += $newDebt;
                        $customer->save();

                        \App\Models\CustomerDebt::create([
                            'order_id' => $order->id,
                            'customer_id' => $request->customer_id,
                            'ref_code' => 'CD'.date('YmdHis').rand(100, 999),
                            'amount' => $newDebt,
                            'debt_total' => $customer->total_debt,
                            'note' => "Công nợ từ đơn hàng {$order->code}",
                            'created_by' => auth()->id(),
                            'recorded_at' => now(),
                        ]);
                    }
                }
            } else {
                // Khác khách hàng - xử lý phức tạp hơn

                // Xóa debt cũ của khách hàng cũ
                $existingDebt = \App\Models\CustomerDebt::where('order_id', $order->id)->first();
                if ($existingDebt) {
                    $oldCustomer = Customer::find($oldCustomerId);
                    $oldCustomer->total_debt -= $oldDebt;
                    $oldCustomer->save();
                    $existingDebt->delete();
                }

                // Tạo debt mới cho khách hàng mới
                if ($newDebt > 0) {
                    $newCustomer = Customer::find($request->customer_id);
                    $newCustomer->total_debt += $newDebt;
                    $newCustomer->save();

                    \App\Models\CustomerDebt::create([
                        'order_id' => $order->id,
                        'customer_id' => $request->customer_id,
                        'ref_code' => 'CD'.date('YmdHis').rand(100, 999),
                        'amount' => $newDebt,
                        'debt_total' => $newCustomer->total_debt,
                        'note' => "Công nợ từ đơn hàng {$order->code}",
                        'created_by' => auth()->id(),
                        'recorded_at' => now(),
                    ]);
                }
            }

            DB::commit();

            $order->load(['customer', 'warehouse', 'items.product']);

            return response()->json([
                'success' => true,
                'message' => 'Cập nhật đơn hàng thành công',
                'data' => $order,
            ]);

        } catch (\Exception $e) {
            DB::rollback();

            return response()->json([
                'success' => false,
                'message' => 'Có lỗi xảy ra: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Cập nhật trạng thái đơn hàng
     */
    public function updateStatus(Request $request, $id)
    {
        $request->validate([
            'status' => 'required|in:pending,confirmed,processing,shipping,delivered,completed,cancelled,refunded,ordered,approved,shipping_created',
            'note' => 'nullable|string|max:500',
        ]);

        $order = Order::findOrFail($id);
        $oldStatus = $order->status;

        DB::beginTransaction();
        try {
            // Cập nhật trạng thái
            $order->update(['status' => $request->status]);

            // Tạo history record
            OrderStatusHistory::create([
                'order_id' => $order->id,
                'from_status' => $oldStatus,
                'to_status' => $request->status,
                'note' => $request->note,
                'changed_by' => auth()->id(),
                'changed_at' => now(),
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Cập nhật trạng thái thành công',
                'data' => $order,
            ]);

        } catch (\Exception $e) {
            DB::rollback();

            return response()->json([
                'success' => false,
                'message' => 'Có lỗi xảy ra: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Thanh toán đơn hàng
     */
    public function addPayment(Request $request, $id)
    {
        $request->validate([
            'amount' => 'required|numeric|min:0',
            'payment_method' => 'required|in:cash,transfer,card,wallet,other',
            'transaction_id' => 'nullable|string',
            'note' => 'nullable|string|max:500',
        ]);

        $order = Order::findOrFail($id);

        if ($request->amount > $order->debt) {
            return response()->json([
                'success' => false,
                'message' => 'Số tiền thanh toán không được vượt quá số nợ',
            ], 422);
        }

        DB::beginTransaction();
        try {
            // Tạo payment record
            OrderPayment::create([
                'order_id' => $order->id,
                'payment_method' => $request->payment_method,
                'amount' => $request->amount,
                'transaction_id' => $request->transaction_id,
                'note' => $request->note,
                'paid_at' => now(),
                'created_by' => auth()->id(),
            ]);

            // Cập nhật số tiền đã thanh toán
            $order->paid += $request->amount;
            $order->debt -= $request->amount;
            $order->save();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Thanh toán thành công',
                'data' => $order,
            ]);

        } catch (\Exception $e) {
            DB::rollback();

            return response()->json([
                'success' => false,
                'message' => 'Có lỗi xảy ra: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Dọn dẹp toàn bộ side-effects khi xoá đơn hàng.
     * Phải gọi TRƯỚC $order->delete() và BÊN TRONG transaction.
     *
     * 1. Xoá CustomerDebt → trigger boot() deleted → cập nhật Customer.total_debt
     * 2. Trả serial về in_stock (nếu sản phẩm theo dõi serial)
     * 3. Hoàn tồn kho nếu đã xuất (status delivered/completed — edge case)
     * 4. Đồng bộ Product.quantity
     */
    private function cleanupOrderSideEffects(Order $order): void
    {
        // 1. Xoá nợ khách hàng → Customer.total_debt tự cập nhật qua boot()
        if ($order->customerDebt) {
            $order->customerDebt->delete();
        }

        // 2. Trả serial về in_stock
        foreach ($order->items as $item) {
            $serials = ProductSerial::where('order_item_id', $item->id)->get();
            foreach ($serials as $serial) {
                $serial->markAsReturned(
                    $order->warehouse_id,
                    auth()->id(),
                    'Tự động trả serial khi xoá đơn hàng ' . $order->code
                );
            }
        }

        // 3. Hoàn tồn kho nếu stock đã bị trừ (delivered, completed)
        $stockExportedStatuses = ['delivered', 'completed'];
        if (in_array($order->status, $stockExportedStatuses)) {
            foreach ($order->items as $item) {
                $warehouseProduct = WarehouseProduct::where('warehouse_id', $order->warehouse_id)
                    ->where('product_id', $item->product_id)
                    ->first();

                if ($warehouseProduct) {
                    $warehouseProduct->increment('quantity', $item->quantity);
                } else {
                    // Trường hợp sản phẩm đã bị xoá khỏi kho → tạo lại
                    WarehouseProduct::create([
                        'warehouse_id' => $order->warehouse_id,
                        'product_id' => $item->product_id,
                        'quantity' => $item->quantity,
                        'cost' => $item->cost_price ?? 0,
                    ]);
                }
            }

            // Đồng bộ Product.quantity = tổng warehouse_products
            $productIds = $order->items->pluck('product_id')->unique();
            foreach ($productIds as $productId) {
                $product = Product::find($productId);
                if ($product) {
                    $totalQty = WarehouseProduct::where('product_id', $productId)->sum('quantity');
                    $product->update(['quantity' => $totalQty]);
                }
            }
        }

        // 4. Xoá thanh toán, lịch sử trạng thái, vận chuyển
        $order->payments()->delete();
        $order->statusHistory()->delete();
        if ($order->shipping) {
            $order->shipping->delete();
        }
    }

    /**
     * Xóa đơn hàng — dọn dẹp toàn bộ side-effects: nợ, serial, tồn kho
     */
    public function destroy($id)
    {
        $order = Order::with(['items', 'customerDebt', 'payments', 'statusHistory', 'shipping'])->findOrFail($id);

        // Chặn xoá nếu đã có đơn trả hàng
        if ($order->returns()->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Không thể xóa đơn hàng đã có đơn trả hàng. Hãy xoá đơn trả hàng trước.',
            ], 422);
        }

        DB::beginTransaction();
        try {
            $this->cleanupOrderSideEffects($order);
            $order->delete();
            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Xóa đơn hàng thành công. Nợ và tồn kho đã được cập nhật.',
            ]);

        } catch (\Exception $e) {
            DB::rollback();

            return response()->json([
                'success' => false,
                'message' => 'Có lỗi xảy ra: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Bulk delete orders
     */
    public function bulkDelete(Request $request)
    {
        $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'integer|exists:orders,id',
        ]);

        try {
            $orders = Order::with(['items', 'customerDebt', 'payments', 'statusHistory', 'shipping'])
                ->whereIn('id', $request->ids)
                ->get();

            // Chặn nếu bất kỳ đơn nào có đơn trả hàng
            foreach ($orders as $order) {
                if ($order->returns()->exists()) {
                    return response()->json([
                        'success' => false,
                        'message' => "Đơn hàng {$order->code} đã có đơn trả hàng, không thể xóa.",
                    ], 422);
                }
            }

            DB::beginTransaction();

            foreach ($orders as $order) {
                $this->cleanupOrderSideEffects($order);
                $order->delete();
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => "Đã xóa {$orders->count()} đơn hàng. Nợ và tồn kho đã được cập nhật.",
            ]);

        } catch (\Exception $e) {
            DB::rollback();

            return response()->json([
                'success' => false,
                'message' => 'Có lỗi xảy ra: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Thống kê đơn hàng
     */
    public function stats(Request $request)
    {
        $query = Order::query();

        if ($request->warehouse_id) {
            $query->where('warehouse_id', $request->warehouse_id);
        }

        if ($request->date_from) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }
        if ($request->date_to) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        $stats = $query->selectRaw('
            COUNT(*) as total_orders,
            SUM(total) as total_revenue,
            SUM(paid) as total_paid,
            SUM(debt) as total_debt,
            AVG(total) as avg_order_value,
            COUNT(CASE WHEN status = "pending" THEN 1 END) as pending_orders,
            COUNT(CASE WHEN status = "confirmed" THEN 1 END) as confirmed_orders,
            COUNT(CASE WHEN status = "processing" THEN 1 END) as processing_orders,
            COUNT(CASE WHEN status = "shipping" THEN 1 END) as shipping_orders,
            COUNT(CASE WHEN status = "completed" THEN 1 END) as completed_orders,
            COUNT(CASE WHEN status = "cancelled" THEN 1 END) as cancelled_orders,
            COUNT(CASE WHEN paid < total THEN 1 END) as waiting_payment,
            COUNT(CASE WHEN status = "processing" THEN 1 END) as pending_pack,
            COUNT(CASE WHEN status = "shipping" THEN 1 END) as pickup,
            COUNT(CASE WHEN status = "shipping" THEN 1 END) as redelivery
        ')->first();

        return response()->json([
            'success' => true,
            'data' => $stats,
        ]);
    }

    /**
     * Export orders to Excel
     */
    public function export(Request $request)
    {
        try {
            $query = Order::with(['customer', 'warehouse', 'cashier', 'items.product']);

            // Apply filters
            if ($request->status) {
                $query->where('status', $request->status);
            }
            if ($request->warehouse_id) {
                $query->where('warehouse_id', $request->warehouse_id);
            }
            if ($request->customer_id) {
                $query->where('customer_id', $request->customer_id);
            }
            if ($request->date_from) {
                $query->whereDate('created_at', '>=', $request->date_from);
            }
            if ($request->date_to) {
                $query->whereDate('created_at', '<=', $request->date_to);
            }
            if ($request->search) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('code', 'LIKE', "%{$search}%")
                        ->orWhereHas('customer', function ($q) use ($search) {
                            $q->where('name', 'LIKE', "%{$search}%")
                                ->orWhere('phone', 'LIKE', "%{$search}%");
                        });
                });
            }

            // Selected IDs
            if ($request->selected_ids) {
                $ids = is_array($request->selected_ids) ? $request->selected_ids : explode(',', $request->selected_ids);
                $query->whereIn('id', $ids);
            }

            $orders = $query->get();

            // Create spreadsheet
            $spreadsheet = new Spreadsheet;
            $sheet = $spreadsheet->getActiveSheet();

            // Headers
            $headers = [
                'A1' => 'Mã đơn hàng',
                'B1' => 'Ngày tạo',
                'C1' => 'Khách hàng',
                'D1' => 'SĐT khách hàng',
                'E1' => 'Trạng thái',
                'F1' => 'Tổng tiền',
                'G1' => 'Đã thanh toán',
                'H1' => 'Còn nợ',
                'I1' => 'Cửa hàng',
                'J1' => 'Nhân viên',
                'K1' => 'Ghi chú',
                'L1' => 'Ngày ghi nhận',
            ];

            foreach ($headers as $cell => $value) {
                $sheet->setCellValue($cell, $value);
            }

            // Style headers
            $sheet->getStyle('A1:L1')->getFont()->setBold(true);
            $sheet->getStyle('A1:L1')->getFill()
                ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                ->getStartColor()->setRGB('E2E8F0');

            // Data rows
            $row = 2;
            foreach ($orders as $order) {
                $sheet->setCellValue("A{$row}", $order->code);
                $sheet->setCellValue("B{$row}", $order->created_at->format('d/m/Y H:i'));
                $sheet->setCellValue("C{$row}", $order->customer->name ?? '');
                $sheet->setCellValue("D{$row}", $order->customer->phone ?? '');
                $sheet->setCellValue("E{$row}", $this->getStatusText($order->status));
                $sheet->setCellValue("F{$row}", $order->total);
                $sheet->setCellValue("G{$row}", $order->paid);
                $sheet->setCellValue("H{$row}", $order->debt);
                $sheet->setCellValue("I{$row}", $order->warehouse->name ?? '');
                $sheet->setCellValue("J{$row}", $order->cashier->name ?? '');
                $sheet->setCellValue("K{$row}", $order->note ?? '');
                $sheet->setCellValue("L{$row}", $order->ordered_at ? $order->ordered_at->format('d/m/Y H:i') : '');

                $row++;
            }

            // Auto-size columns
            foreach (range('A', 'L') as $col) {
                $sheet->getColumnDimension($col)->setAutoSize(true);
            }

            // Format currency columns
            $sheet->getStyle("F2:H{$row}")->getNumberFormat()
                ->setFormatCode('#,##0');

            // Create writer and output
            $writer = new Xlsx($spreadsheet);
            $filename = 'danh-sach-don-hang-'.date('Y-m-d').'.xlsx';

            // Set headers for download
            return response()->stream(function () use ($writer) {
                $writer->save('php://output');
            }, 200, [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'Content-Disposition' => "attachment; filename=\"{$filename}\"",
                'Cache-Control' => 'max-age=0',
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Lỗi khi xuất file: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Download import template
     */
    public function downloadTemplate()
    {
        try {
            $spreadsheet = new Spreadsheet;
            $sheet = $spreadsheet->getActiveSheet();

            // Headers
            $headers = [
                'A1' => 'Mã khách hàng*',
                'B1' => 'Tên khách hàng*',
                'C1' => 'SĐT khách hàng',
                'D1' => 'Mã sản phẩm*',
                'E1' => 'Tên sản phẩm*',
                'F1' => 'Số lượng*',
                'G1' => 'Đơn giá*',
                'H1' => 'Địa chỉ giao hàng',
                'I1' => 'Người nhận',
                'J1' => 'SĐT nhận hàng',
                'K1' => 'Ghi chú',
                'L1' => 'Phương thức thanh toán',
            ];

            foreach ($headers as $cell => $value) {
                $sheet->setCellValue($cell, $value);
            }

            // Style headers
            $sheet->getStyle('A1:L1')->getFont()->setBold(true);
            $sheet->getStyle('A1:L1')->getFill()
                ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                ->getStartColor()->setRGB('E2E8F0');

            // Sample data
            $sampleData = [
                ['KH001', 'Nguyen Van A', '0123456789', 'SP001', 'Vodka Absolut', 2, 250000, '123 ABC Street', 'Nguyen Van A', '0123456789', 'Giao nhanh', 'cash'],
                ['KH002', 'Tran Thi B', '0987654321', 'SP002', 'Whisky Dalmore', 1, 2500000, '456 DEF Street', 'Tran Thi B', '0987654321', '', 'transfer'],
            ];

            $row = 2;
            foreach ($sampleData as $data) {
                $col = 'A';
                foreach ($data as $value) {
                    $sheet->setCellValue($col.$row, $value);
                    $col++;
                }
                $row++;
            }

            // Auto-size columns
            foreach (range('A', 'L') as $col) {
                $sheet->getColumnDimension($col)->setAutoSize(true);
            }

            // Create writer and output
            $writer = new Xlsx($spreadsheet);
            $filename = 'mau-import-don-hang.xlsx';

            return response()->stream(function () use ($writer) {
                $writer->save('php://output');
            }, 200, [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'Content-Disposition' => "attachment; filename=\"{$filename}\"",
                'Cache-Control' => 'max-age=0',
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Lỗi khi tải file mẫu: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Import orders from Excel
     */
    public function import(Request $request)
    {
        $request->validate([
            'file' => 'required|mimes:xlsx,xls|max:10240',
        ]);

        try {
            $file = $request->file('file');
            $spreadsheet = IOFactory::load($file->getPathname());
            $sheet = $spreadsheet->getActiveSheet();
            $rows = $sheet->toArray();

            // Remove header row
            array_shift($rows);

            $successCount = 0;
            $errorCount = 0;
            $errors = [];

            DB::beginTransaction();

            // Group rows by customer
            $orderGroups = [];
            foreach ($rows as $index => $row) {
                if (empty($row[0])) {
                    continue;
                } // Skip empty rows

                $customerCode = $row[0];
                if (! isset($orderGroups[$customerCode])) {
                    $orderGroups[$customerCode] = [
                        'customer_code' => $row[0],
                        'customer_name' => $row[1],
                        'customer_phone' => $row[2],
                        'delivery_address' => $row[7],
                        'delivery_contact' => $row[8],
                        'delivery_phone' => $row[9],
                        'note' => $row[10],
                        'payment_method' => $row[11] ?: 'cash',
                        'items' => [],
                    ];
                }

                $orderGroups[$customerCode]['items'][] = [
                    'product_sku' => $row[3],
                    'product_name' => $row[4],
                    'quantity' => (int) $row[5],
                    'price' => (float) $row[6],
                    'row_index' => $index + 2,
                ];
            }

            // Process each order group
            foreach ($orderGroups as $group) {
                try {
                    // Find or create customer
                    $customer = Customer::where('code', $group['customer_code'])->first();
                    if (! $customer) {
                        $customer = Customer::create([
                            'code' => $group['customer_code'],
                            'name' => $group['customer_name'],
                            'phone' => $group['customer_phone'],
                            'group_id' => 1,
                            'status' => 'active',
                            'customer_type' => 'Bán lẻ',
                            'person_in_charge' => auth()->user()->name,
                        ]);
                    }

                    // Validate products and calculate total
                    $validItems = [];
                    $total = 0;

                    foreach ($group['items'] as $item) {
                        $product = Product::where('sku', $item['product_sku'])->first();
                        if (! $product) {
                            $errors[] = "Dòng {$item['row_index']}: Không tìm thấy sản phẩm với SKU {$item['product_sku']}";

                            continue;
                        }

                        if ($item['quantity'] <= 0) {
                            $errors[] = "Dòng {$item['row_index']}: Số lượng phải lớn hơn 0";

                            continue;
                        }

                        if ($item['price'] <= 0) {
                            $errors[] = "Dòng {$item['row_index']}: Đơn giá phải lớn hơn 0";

                            continue;
                        }

                        $validItems[] = [
                            'product_id' => $product->id,
                            'product_name' => $product->name,
                            'sku' => $product->sku,
                            'quantity' => $item['quantity'],
                            'price' => $item['price'],
                            'total' => $item['quantity'] * $item['price'],
                        ];

                        $total += $item['quantity'] * $item['price'];
                    }

                    if (empty($validItems)) {
                        $errors[] = "Đơn hàng khách hàng {$group['customer_code']}: Không có sản phẩm hợp lệ";

                        continue;
                    }

                    // Create order
                    $orderCode = $this->generateOrderCode();
                    $order = Order::create([
                        'code' => $orderCode,
                        'customer_id' => $customer->id,
                        'warehouse_id' => 1, // Default warehouse
                        'cashier_id' => auth()->id(),
                        'total' => $total,
                        'paid' => 0,
                        'debt' => $total,
                        'status' => 'pending',
                        'source' => 'Import',
                        'priority' => 'normal',
                        'delivery_address' => $group['delivery_address'],
                        'delivery_contact' => $group['delivery_contact'],
                        'delivery_phone' => $group['delivery_phone'],
                        'note' => $group['note'],
                        'ordered_at' => now(),
                        'created_by' => auth()->id(),
                    ]);

                    // Create order items
                    foreach ($validItems as $item) {
                        OrderItem::create([
                            'order_id' => $order->id,
                            'product_id' => $item['product_id'],
                            'quantity' => $item['quantity'],
                            'price' => $item['price'],
                            'total' => $item['total'],
                            'sku' => $item['sku'],
                            'product_name' => $item['product_name'],
                            'unit_name' => 'Chai',
                        ]);
                    }

                    // Create status history
                    OrderStatusHistory::create([
                        'order_id' => $order->id,
                        'from_status' => null,
                        'to_status' => 'pending',
                        'note' => 'Đơn hàng được tạo từ import Excel',
                        'changed_by' => auth()->id(),
                        'changed_at' => now(),
                    ]);

                    $successCount++;

                } catch (\Exception $e) {
                    $errorCount++;
                    $errors[] = "Lỗi khi tạo đơn hàng khách hàng {$group['customer_code']}: ".$e->getMessage();
                }
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => "Import thành công {$successCount} đơn hàng".($errorCount > 0 ? ", {$errorCount} lỗi" : ''),
                'data' => [
                    'success_count' => $successCount,
                    'error_count' => $errorCount,
                    'errors' => $errors,
                ],
            ]);

        } catch (\Exception $e) {
            DB::rollback();

            return response()->json([
                'success' => false,
                'message' => 'Lỗi khi import file: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Helper method to get status text
     */
    private function getStatusText($status)
    {
        $statusMap = [
            'pending' => 'Chờ xử lý',
            'confirmed' => 'Đã xác nhận',
            'processing' => 'Đang xử lý',
            'shipping' => 'Đang giao hàng',
            'delivered' => 'Đã giao hàng',
            'completed' => 'Hoàn thành',
            'cancelled' => 'Đã hủy',
            'refunded' => 'Đã hoàn tiền',
        ];

        return $statusMap[$status] ?? $status;
    }

    /**
     * In đơn hàng
     */
    public function print($id)
    {
        try {
            $order = \App\Models\Order::with([
                'customer:id,code,name,phone,email', // BỎ address vì không tồn tại
                'warehouse:id,code,name,address,phone,email', // Có address, phone, email
                'cashier:id,name,email', // Users table
                'items.product:id,sku,name,retail_price',
                'payments',
            ])->findOrFail($id);

            return view('orders.print', compact('order'));

        } catch (\Exception $e) {
            return '<h1>Lỗi Exception: '.$e->getMessage().'</h1>';
        }
    }

    /**
     * Tạo mã đơn hàng tự động - Fixed version
     */
    private function generateOrderCode()
    {
        $prefix = 'SON'.date('ymd');
        $lastOrder = Order::where('code', 'LIKE', $prefix.'%')
            ->orderBy('id', 'desc')
            ->first();

        if ($lastOrder) {
            // Extract the last 5 digits instead of 2
            $lastNumber = intval(substr($lastOrder->code, -5));
            $newNumber = $lastNumber + 1;
        } else {
            $newNumber = 1;
        }

        return $prefix.str_pad($newNumber, 5, '0', STR_PAD_LEFT);
    }

    // =====================================================
    // QUY TRÌNH MỚI ĐƠN GIẢN HÓA 5 BƯỚC
    // =====================================================

    /**
     * BƯỚC 2: Duyệt đơn hàng
     */
    public function approveOrder(Request $request, $id)
    {
        $order = Order::findOrFail($id);

        if ($order->status !== self::STATUS_ORDERED) {
            return response()->json([
                'success' => false,
                'message' => 'Chỉ có thể duyệt đơn hàng ở trạng thái "Đặt hàng"',
            ], 422);
        }

        $request->validate([
            'note' => 'nullable|string|max:500',
        ]);

        DB::beginTransaction();
        try {
            // Kiểm tra tồn kho lần nữa
            foreach ($order->items as $item) {
                $warehouseProduct = WarehouseProduct::where('warehouse_id', $order->warehouse_id)
                    ->where('product_id', $item->product_id)
                    ->first();

                if (! $warehouseProduct || $warehouseProduct->quantity < $item->quantity) {
                    throw ValidationException::withMessages([
                        'stock' => "Sản phẩm {$item->product_name} không đủ tồn kho. Còn lại: ".($warehouseProduct->quantity ?? 0),
                    ]);
                }
            }

            // Cập nhật trạng thái
            $order->update(['status' => self::STATUS_APPROVED]);

            // Tạo status history
            OrderStatusHistory::create([
                'order_id' => $order->id,
                'from_status' => self::STATUS_ORDERED,
                'to_status' => self::STATUS_APPROVED,
                'note' => $request->note ?: 'Đơn hàng đã được duyệt',
                'changed_by' => auth()->id(),
                'changed_at' => now(),
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Duyệt đơn hàng thành công',
                'data' => $order->fresh(),
                'next_action' => 'create_shipping', // Hành động tiếp theo
            ]);

        } catch (\Exception $e) {
            DB::rollback();

            if ($e instanceof ValidationException) {
                throw $e;
            }

            return response()->json([
                'success' => false,
                'message' => 'Có lỗi xảy ra: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * BƯỚC 3: Tạo đơn vận chuyển (3 tùy chọn)
     */
    public function createShipping(Request $request, $id)
    {
        $order = Order::findOrFail($id);

        if ($order->status !== self::STATUS_APPROVED) {
            return response()->json([
                'success' => false,
                'message' => 'Chỉ có thể tạo vận chuyển cho đơn hàng đã duyệt',
            ], 422);
        }

        $request->validate([
            'shipping_method' => 'required|in:third_party,self_delivery,pickup',
            'provider_name' => 'required_if:shipping_method,third_party|string|max:255',
            'shipping_fee' => 'required_if:shipping_method,third_party|numeric|min:0',
            'receiver_name' => 'nullable|string|max:255',
            'receiver_phone' => 'nullable|string|max:20',
            'receiver_address' => 'nullable|string|max:500',
            'note' => 'nullable|string|max:500',
        ]);

        DB::beginTransaction();
        try {
            $shippingData = [
                'order_id' => $order->id,
                'shipping_method' => $request->shipping_method,
                'tracking_number' => $this->generateTrackingNumber(),
                'status' => 'pending',
                'note' => $request->note,
            ];

            // Xử lý theo từng loại vận chuyển
            switch ($request->shipping_method) {
                case self::SHIPPING_THIRD_PARTY:
                    $shippingData['carrier'] = $request->provider_name;
                    $shippingData['shipping_fee'] = $request->shipping_fee;
                    $shippingData['payment_by'] = 'sender'; // Mặc định người gửi trả
                    break;

                case self::SHIPPING_SELF_DELIVERY:
                    $shippingData['delivery_contact'] = $request->receiver_name ?: $order->customer->name;
                    $shippingData['delivery_phone'] = $request->receiver_phone ?: $order->customer->phone;
                    $shippingData['delivery_address'] = $request->receiver_address ?: $order->delivery_address;
                    $shippingData['shipping_fee'] = 0;
                    break;

                case self::SHIPPING_PICKUP:
                    // Nhận tại cửa hàng - chuyển thẳng sang bước tiếp theo
                    $order->update(['status' => self::STATUS_DELIVERED]);

                    OrderStatusHistory::create([
                        'order_id' => $order->id,
                        'from_status' => self::STATUS_APPROVED,
                        'to_status' => self::STATUS_DELIVERED,
                        'note' => 'Khách hàng nhận tại cửa hàng',
                        'changed_by' => auth()->id(),
                        'changed_at' => now(),
                    ]);

                    DB::commit();

                    return response()->json([
                        'success' => true,
                        'message' => 'Khách hàng sẽ nhận tại cửa hàng',
                        'data' => $order->fresh(),
                        'next_action' => 'payment', // Chuyển thẳng sang thanh toán
                    ]);
            }

            // Tạo shipping record (cho third_party và self_delivery)
            if ($request->shipping_method !== self::SHIPPING_PICKUP) {
                OrderShipping::create($shippingData);

                $order->update(['status' => self::STATUS_SHIPPING_CREATED]);

                OrderStatusHistory::create([
                    'order_id' => $order->id,
                    'from_status' => self::STATUS_APPROVED,
                    'to_status' => self::STATUS_SHIPPING_CREATED,
                    'note' => 'Đã tạo đơn vận chuyển - '.($request->shipping_method === 'third_party' ? $request->provider_name : 'Tự giao hàng'),
                    'changed_by' => auth()->id(),
                    'changed_at' => now(),
                ]);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Tạo đơn vận chuyển thành công',
                'data' => $order->fresh(['shipping']),
                'next_action' => 'export_stock', // Hành động tiếp theo
            ]);

        } catch (\Exception $e) {
            DB::rollback();

            return response()->json([
                'success' => false,
                'message' => 'Có lỗi xảy ra: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * BƯỚC 4: Xuất kho (đánh dấu đã giao hàng)
     */
    public function exportStock(Request $request, $id)
    {
        $order = Order::findOrFail($id);

        if (! in_array($order->status, [self::STATUS_SHIPPING_CREATED, self::STATUS_APPROVED])) {
            return response()->json([
                'success' => false,
                'message' => 'Không thể xuất kho cho đơn hàng này',
            ], 422);
        }

        $request->validate([
            'note' => 'nullable|string|max:500',
        ]);

        DB::beginTransaction();
        try {
            // Trừ tồn kho
            foreach ($order->items as $item) {
                $warehouseProduct = WarehouseProduct::where('warehouse_id', $order->warehouse_id)
                    ->where('product_id', $item->product_id)
                    ->first();

                if (! $warehouseProduct || $warehouseProduct->quantity < $item->quantity) {
                    throw ValidationException::withMessages([
                        'stock' => "Sản phẩm {$item->product_name} không đủ tồn kho để xuất",
                    ]);
                }

                $warehouseProduct->decrement('quantity', $item->quantity);
            }

            // Cập nhật trạng thái
            $order->update(['status' => self::STATUS_DELIVERED]);

            // Cập nhật shipping status nếu có
            if ($order->shipping) {
                $order->shipping->update(['status' => 'delivered']);
            }

            // Tạo status history
            $oldStatus = $order->status;
            OrderStatusHistory::create([
                'order_id' => $order->id,
                'from_status' => $oldStatus,
                'to_status' => self::STATUS_DELIVERED,
                'note' => $request->note ?: 'Đã xuất kho - Giao hàng thành công',
                'changed_by' => auth()->id(),
                'changed_at' => now(),
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Xuất kho thành công',
                'data' => $order->fresh(['shipping']),
                'next_action' => 'payment', // Hành động tiếp theo
                'can_return' => true, // Có thể đổi trả hàng
            ]);

        } catch (\Exception $e) {
            DB::rollback();

            if ($e instanceof ValidationException) {
                throw $e;
            }

            return response()->json([
                'success' => false,
                'message' => 'Có lỗi xảy ra: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * BƯỚC 5: Thanh toán hoàn tất
     */
    public function completePayment(Request $request, $id)
    {
        $order = Order::findOrFail($id);

        if ($order->status !== self::STATUS_DELIVERED) {
            return response()->json([
                'success' => false,
                'message' => 'Chỉ có thể thanh toán cho đơn hàng đã giao',
            ], 422);
        }

        $request->validate([
            'payment_method' => 'required|in:cash,transfer,card,wallet',
            'amount' => 'required|numeric|min:0|max:'.$order->debt,
            'note' => 'nullable|string|max:500',
        ]);

        DB::beginTransaction();
        try {
            // Tạo payment record
            OrderPayment::create([
                'order_id' => $order->id,
                'payment_method' => $request->payment_method,
                'amount' => $request->amount,
                'note' => $request->note ?: 'Thanh toán đơn hàng',
                'paid_at' => now(),
                'created_by' => auth()->id(),
            ]);

            // Cập nhật debt và paid
            $newPaid = $order->paid + $request->amount;
            $newDebt = $order->total - $newPaid;

            $order->update([
                'paid' => $newPaid,
                'debt' => $newDebt,
                'status' => $newDebt <= 0 ? self::STATUS_COMPLETED : self::STATUS_DELIVERED,
            ]);

            // Cập nhật customer debt
            if ($order->customerDebt) {
                $order->customerDebt->decrement('amount', $request->amount);
            }

            // Tạo status history nếu hoàn thành
            if ($newDebt <= 0) {
                OrderStatusHistory::create([
                    'order_id' => $order->id,
                    'from_status' => self::STATUS_DELIVERED,
                    'to_status' => self::STATUS_COMPLETED,
                    'note' => 'Đơn hàng đã thanh toán hoàn tất',
                    'changed_by' => auth()->id(),
                    'changed_at' => now(),
                ]);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => $newDebt <= 0 ? 'Thanh toán hoàn tất đơn hàng' : 'Thanh toán thành công',
                'data' => $order->fresh(['payments']),
                'remaining_debt' => $newDebt,
                'is_completed' => $newDebt <= 0,
            ]);

        } catch (\Exception $e) {
            DB::rollback();

            return response()->json([
                'success' => false,
                'message' => 'Có lỗi xảy ra: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Lấy hành động tiếp theo cho đơn hàng
     */
    public function getNextAction($id)
    {
        $order = Order::findOrFail($id);

        $nextActions = [
            self::STATUS_ORDERED => [
                'action' => 'approve',
                'label' => 'Duyệt đơn hàng',
                'color' => 'blue',
            ],
            self::STATUS_APPROVED => [
                'action' => 'create_shipping',
                'label' => 'Tạo đơn vận chuyển',
                'color' => 'purple',
                'options' => [
                    ['value' => 'third_party', 'label' => 'Gửi cho bên giao hàng'],
                    ['value' => 'self_delivery', 'label' => 'Tự giao hàng'],
                    ['value' => 'pickup', 'label' => 'Nhận tại cửa hàng'],
                ],
            ],
            self::STATUS_SHIPPING_CREATED => [
                'action' => 'export_stock',
                'label' => 'Xuất kho',
                'color' => 'orange',
            ],
            self::STATUS_DELIVERED => [
                'action' => 'payment',
                'label' => 'Thanh toán',
                'color' => 'green',
                'can_return' => true,
            ],
            self::STATUS_COMPLETED => [
                'action' => 'completed',
                'label' => 'Hoàn thành',
                'color' => 'green',
            ],
        ];

        return response()->json([
            'success' => true,
            'data' => [
                'current_status' => $order->status,
                'next_action' => $nextActions[$order->status] ?? null,
                'order' => $order->load(['customer', 'warehouse', 'items.product', 'shipping', 'payments']),
            ],
        ]);
    }

    /**
     * Hủy đơn hàng
     */
    public function cancelOrder(Request $request, $id)
    {
        $order = Order::findOrFail($id);

        // Chỉ có thể hủy đơn chưa xuất kho
        if (in_array($order->status, [self::STATUS_DELIVERED, self::STATUS_COMPLETED])) {
            return response()->json([
                'success' => false,
                'message' => 'Không thể hủy đơn hàng đã giao',
            ], 422);
        }

        $request->validate([
            'note' => 'required|string|max:500',
        ]);

        DB::beginTransaction();
        try {
            $oldStatus = $order->status;

            // Cập nhật trạng thái
            $order->update(['status' => self::STATUS_CANCELLED]);

            // Xóa debt nếu chưa thanh toán
            if ($order->customerDebt) {
                $order->customerDebt->delete();
            }

            // Tạo status history
            OrderStatusHistory::create([
                'order_id' => $order->id,
                'from_status' => $oldStatus,
                'to_status' => self::STATUS_CANCELLED,
                'note' => $request->note,
                'changed_by' => auth()->id(),
                'changed_at' => now(),
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Hủy đơn hàng thành công',
                'data' => $order->fresh(),
            ]);

        } catch (\Exception $e) {
            DB::rollback();

            return response()->json([
                'success' => false,
                'message' => 'Có lỗi xảy ra: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Generate tracking number
     */
    private function generateTrackingNumber()
    {
        return 'TK'.date('YmdHis').rand(1000, 9999);
    }
}
