<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Models\ActivityLog;
use App\Models\Order;
use App\Models\Invoice;
use App\Models\Branch;
use App\Models\Product;
use App\Models\Customer;
use App\Models\Employee;
use App\Models\PriceBook;
use App\Models\Setting;
use App\Enums\OrderStatus;
use App\Models\InvoiceItemSerial;
use App\Models\SerialImei;
use App\Support\Filters\FilterableIndex;
use App\Services\CustomerDebtService;
use App\Services\LockPeriodService;
use App\Services\MovingAvgCostingService;
use App\Services\OrderPaymentSummaryService;
use App\Services\PartnerTransactionGuard;
use App\Services\PrintableOrderService;
use App\Services\SerialAvailabilityService;
use App\Services\StockMovementService;
use App\Support\Status\BusinessStatus;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class OrderController extends Controller
{
    use FilterableIndex;

    protected function configureOrderFilters(): void
    {
        $this->searchable = ['code', 'note', 'tracking_code', 'receiver_name', 'receiver_phone', 'created_by_name', 'assigned_to_name'];
        $this->searchableRelations = [
            'customer'      => ['name', 'code', 'phone'],
            'items.product' => ['name', 'code', 'barcode'],
        ];
        $this->sortable = ['code', 'created_at', 'total_payment', 'order_paid_total', 'order_remaining_debt', 'order_credit_total', 'status'];
        $this->dateColumn = 'created_at';
        $this->creatorColumn = 'created_by';
        $this->scalarFilters = [
            'branch_id', 'customer_id',
            'is_delivery', 'delivery_partner',
            'sales_channel', 'price_table_id', 'promotion_id',
        ];
    }

    public function index(Request $request)
    {
        $this->configureOrderFilters();
        $paymentSummary = app(OrderPaymentSummaryService::class);

        $query = Order::query()
            ->select('orders.*')
            ->with(['customer', 'branch', 'items.product'])
            ->when($request->filled('has_debt'), function ($q) use ($request) {
                app(OrderPaymentSummaryService::class)->applyHasDebtFilter(
                    $q,
                    (string) $request->input('has_debt') === '1'
                );
            });
        $paymentSummary->addSummarySelects($query);
        $paymentSummary->applyPaymentStatusFilter($query, $request->input('payment_status'));

        $this->applyFilters($query, $request);

        $orders = $query->paginate(15)->withQueryString();

        // Step 22.1C (read-only): enrich items[].selected_serials cho UI hiển thị.
        $allSerialIds = [];
        foreach ($orders->items() as $o) {
            foreach ($o->items as $it) {
                if (is_array($it->serial_ids)) {
                    foreach ($it->serial_ids as $sid) $allSerialIds[] = $sid;
                }
            }
        }
        $serialMap = [];
        if (!empty($allSerialIds)) {
            $serialMap = SerialImei::whereIn('id', array_unique($allSerialIds))
                ->get(['id', 'serial_number'])
                ->keyBy('id');
        }
        foreach ($orders->items() as $o) {
            foreach ($o->items as $it) {
                $list = [];
                if (is_array($it->serial_ids)) {
                    foreach ($it->serial_ids as $sid) {
                        $s = $serialMap[$sid] ?? null;
                        $list[] = [
                            'id'            => (int) $sid,
                            'serial_number' => $s?->serial_number,
                        ];
                    }
                }
                $it->setAttribute('selected_serials', $list);
            }
        }

        $filters = $this->currentFilters($request);
        $filters['has_debt'] = $request->input('has_debt', '');
        $filters['payment_status'] = $request->input('payment_status', '');

        return Inertia::render('Orders/Index', [
            'orders' => $orders,
            'branches' => Branch::all(),
            'employees' => Employee::where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'filters' => $filters,
            'filterOptions' => [
                'branches' => Branch::select('id', 'name')->get(),
                'statuses' => OrderStatus::options(),
                'employees' => Employee::select('id', 'name')->where('is_active', true)->orderBy('name')->get(),
                'creators' => \App\Models\User::select('id', 'name')->orderBy('name')->get(),
                'salesChannels' => Order::query()
                    ->whereNotNull('sales_channel')->where('sales_channel', '!=', '')
                    ->distinct()->orderBy('sales_channel')->pluck('sales_channel')
                    ->map(fn($c) => ['value' => $c, 'label' => $c])->values(),
                'deliveryOptions' => [
                    ['value' => '0', 'label' => 'Không giao hàng'],
                    ['value' => '1', 'label' => 'Giao hàng'],
                ],
                'debtOptions' => [
                    ['value' => '1', 'label' => 'Còn nợ'],
                    ['value' => '0', 'label' => 'Đã trả đủ'],
                ],
                'paymentStatusOptions' => [
                    ['value' => 'unpaid', 'label' => 'Chưa trả'],
                    ['value' => 'partial', 'label' => 'Còn nợ'],
                    ['value' => 'paid', 'label' => 'Đã trả đủ'],
                    ['value' => 'overpaid', 'label' => 'Trả dư'],
                ],
            ],
        ]);
    }

    public function create(Request $request)
    {
        if (!Setting::get('order_enabled', true)) {
            return redirect()->route('orders.index')->with('error', 'Chức năng đặt hàng đã bị tắt trong thiết lập.');
        }

        $invoice = null;
        if ($request->filled('invoice_id')) {
            $invoice = \App\Models\Invoice::with(['items.product', 'customer'])->find($request->invoice_id);
        }

        return Inertia::render('Orders/Create', [
            'branches' => Branch::all(),
            // Step 22.2E: không load toàn bộ KH — frontend dùng AJAX `api.customers.search`.
            'customers' => [],
            'priceBooks' => PriceBook::query()
                ->where(function ($q) {
                    $q->where('is_active', true)
                        ->orWhere('status', 'active');
                })
                ->orderBy('name')
                ->get(['id', 'name']),
            'invoice' => $invoice,
            'action' => $request->input('action', 'edit'),
            'confirmBeforeComplete' => Setting::get('order_confirm_before_complete', false),
            'allowOutOfStock' => Setting::get('order_allow_when_out_of_stock', true),
        ]);
    }

    public function store(Request $request)
    {
        if (!Setting::get('order_enabled', true)) {
            return back()->with('error', 'Chức năng đặt hàng đã bị tắt trong thiết lập.');
        }

        $validated = $request->validate([
            'customer_id' => 'nullable|exists:customers,id',
            'branch_id' => 'nullable|exists:branches,id',
            'status' => 'required|string',
            'total_price' => 'numeric',
            'discount' => 'numeric',
            'other_fees' => 'numeric',
            'total_payment' => 'numeric',
            'amount_paid' => 'numeric',
            'note' => 'nullable|string',
            'created_by_name' => 'nullable|string',
            'assigned_to_name' => 'nullable|string',
            'price_book_id' => 'nullable|exists:price_books,id',
            'price_book_name' => 'nullable|string|max:255',
            'payment_method' => 'nullable|string',
            'bank_account_info' => 'nullable|string',

            // Items
            'items' => 'required|array',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.qty' => 'required|numeric|min:1',
            'items.*.price' => 'required|numeric',
            'items.*.discount' => 'numeric',
            'items.*.serial_ids' => 'nullable|array',
            'items.*.serial_ids.*' => 'integer|exists:serial_imeis,id',

            // Delivery
            'is_delivery' => 'boolean',
            'delivery_partner' => 'nullable|string',
            'receiver_name' => 'nullable|string',
            'receiver_phone' => 'nullable|string',
            'receiver_address' => 'nullable|string',
            'receiver_ward' => 'nullable|string',
            'receiver_district' => 'nullable|string',
            'receiver_city' => 'nullable|string',
            'weight' => 'numeric|nullable',
            'delivery_fee' => 'numeric|nullable',
            'cod_amount' => 'numeric|nullable',
            'expected_delivery_date' => 'nullable|date',
        ]);

        app(PartnerTransactionGuard::class)->assertCanTransact(
            isset($validated['customer_id']) ? (int) $validated['customer_id'] : null,
            'customer_id'
        );

        // Lock period check
        $txDate = $request->order_date ? Carbon::parse($request->order_date) : now();
        app(LockPeriodService::class)->assertNotLocked($txDate, 'order_create');

        // Step 22.2G: pre-flight serial validation TRƯỚC khi tạo Order row.
        if ($preFlight = $this->validateItemsSerials($validated['items'] ?? [])) {
            return $preFlight;
        }

        $priceBookName = 'Bảng giá chung';
        if (!empty($validated['price_book_id'])) {
            $priceBook = PriceBook::find($validated['price_book_id']);
            if ($priceBook) {
                $priceBookName = $priceBook->name;
            }
        } elseif (!empty($validated['price_book_name'])) {
            $priceBookName = $validated['price_book_name'];
        }

        try {
            \Illuminate\Support\Facades\DB::beginTransaction();
            app(PartnerTransactionGuard::class)->assertCanTransact(
                isset($validated['customer_id']) ? (int) $validated['customer_id'] : null,
                'customer_id'
            );

            $order = Order::create([
                'code' => 'DH' . time(), // Simple unique code for now
                'customer_id' => $validated['customer_id'] ?? null,
                'branch_id' => $validated['branch_id'] ?? null,
                'status' => $validated['status'] ?? 'draft',
                'total_price' => $validated['total_price'] ?? 0,
                'discount' => $validated['discount'] ?? 0,
                'other_fees' => $validated['other_fees'] ?? 0,
                'total_payment' => $validated['total_payment'] ?? 0,
                'amount_paid' => $validated['amount_paid'] ?? 0,
                'note' => $validated['note'] ?? null,
                'created_by_name' => $validated['created_by_name'] ?? auth()->user()?->name,
                'assigned_to_name' => $validated['assigned_to_name'] ?? auth()->user()?->name,
                'price_book_name' => $priceBookName,

                'is_delivery' => $validated['is_delivery'] ?? false,
                'delivery_partner' => $validated['delivery_partner'] ?? null,
                'receiver_name' => $validated['receiver_name'] ?? null,
                'receiver_phone' => $validated['receiver_phone'] ?? null,
                'receiver_address' => $validated['receiver_address'] ?? null,
                'receiver_ward' => $validated['receiver_ward'] ?? null,
                'receiver_district' => $validated['receiver_district'] ?? null,
                'receiver_city' => $validated['receiver_city'] ?? null,
                'weight' => $validated['weight'] ?? 0,
                'delivery_fee' => $validated['delivery_fee'] ?? 0,
                'cod_amount' => $validated['cod_amount'] ?? 0,
                'expected_delivery_date' => $validated['expected_delivery_date'] ?? null,
            ]);

            // Cho phép chọn ngày (kế toán nhập sau)
            if ($request->filled('order_date')) {
                $orderDate = Carbon::parse($request->order_date);

                // Validate: không được đặt hàng trước ngày nhập hàng đầu tiên
                foreach ($validated['items'] as $item) {
                    $product = Product::find($item['product_id']);
                    if ($product) {
                        $earliestImport = $product->getEarliestImportDate();
                        if ($earliestImport && $orderDate->lt($earliestImport)) {
                            throw new \Exception("Không thể đặt hàng sản phẩm '{$product->name}' trước ngày nhập hàng đầu tiên (" . $earliestImport->format('d/m/Y H:i') . ").");
                        }
                    }
                }

                $order->update(['created_at' => $orderDate]);
            }

            foreach ($validated['items'] as $item) {
                $subtotal = ($item['qty'] * $item['price']) - ($item['discount'] ?? 0);

                $serialIds = array_values(array_filter($item['serial_ids'] ?? [], fn($v) => $v !== null && $v !== ''));
                $product = Product::find($item['product_id']);
                if ($product && $product->has_serial) {
                    if (count($serialIds) !== (int) $item['qty']) {
                        throw new \Exception("Sản phẩm '{$product->name}' là hàng Serial/IMEI. Vui lòng chọn đủ {$item['qty']} Serial/IMEI trước khi lưu đơn.");
                    }
                    $availability = app(SerialAvailabilityService::class);
                    $blocked = $availability->findBlockedIds($serialIds, $product->id);
                    if (!empty($blocked)) {
                        throw new \Exception("Sản phẩm '{$product->name}': Serial/IMEI không khả dụng (id: " . implode(', ', $blocked) . ").");
                    }
                } else {
                    $serialIds = [];
                }

                $order->items()->create([
                    'product_id' => $item['product_id'],
                    'qty' => $item['qty'],
                    'price' => $item['price'],
                    'discount' => $item['discount'] ?? 0,
                    'subtotal' => $subtotal,
                    'serial_ids' => !empty($serialIds) ? $serialIds : null,
                ]);
            }

            // Ghi nhận cashflow cọc
            $this->recordOrderDepositCashFlow($order, $validated['payment_method'] ?? 'cash', $validated['bank_account_info'] ?? null);

            ActivityLog::log('order_create', "Tạo đơn hàng {$order->code}, tổng: " . number_format($order->total_payment), $order);

            \Illuminate\Support\Facades\DB::commit();

            if ($request->boolean('_print') || $request->wantsJson()) {
                return response()->json(['id' => $order->id, 'code' => $order->code]);
            }

            return redirect()->route('orders.index')->with('success', 'Tạo đơn đặt hàng thành công');
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\DB::rollBack();
            if ($request->wantsJson()) {
                return response()->json(['success' => false, 'message' => 'Lỗi: ' . $e->getMessage()], 422);
            }
            return back()->withErrors(['items' => $e->getMessage()])->withInput();
        }
    }

    public function update(Request $request, Order $order)
    {
        if (Setting::get('block_change_transaction_time', false) && $request->has('created_at')) {
            if ($request->wantsJson()) {
                return response()->json(['error' => 'Không được phép thay đổi thời gian giao dịch.'], 422);
            }
            return back()->with('error', 'Không được phép thay đổi thời gian giao dịch.');
        }

        if (in_array($order->status, ['completed', 'cancelled', 'ended'])) {
            if ($request->wantsJson()) {
                return response()->json(['error' => 'Không thể sửa đơn hàng đã hoàn thành, đã hủy hoặc đã kết thúc.'], 422);
            }
            return back()->with('error', 'Không thể sửa đơn hàng đã hoàn thành, đã hủy hoặc đã kết thúc.');
        }

        if ($request->has('status') && in_array($request->input('status'), ['completed', 'cancelled', 'ended'], true)) {
            $msg = match ($request->input('status')) {
                'completed' => 'Vui lòng dùng chức năng Xử lý đơn hàng để tạo hóa đơn.',
                'cancelled' => 'Vui lòng dùng chức năng Hủy đơn để ghi nhận lý do hủy.',
                'ended' => 'Vui lòng dùng chức năng Kết thúc đơn để ghi nhận lý do kết thúc.',
            };
            if ($request->wantsJson()) {
                return response()->json(['error' => $msg], 422);
            }
            return back()->with('error', $msg);
        }

        // Check if order is partially or fully fulfilled
        $hasFulfilled = $order->items()->where('fulfilled_quantity', '>', 0)->exists();
        $hasInvoice = $order->invoices()->exists();

        if ($hasFulfilled || $hasInvoice) {
            if ($request->has('items')) {
                if ($request->wantsJson()) {
                    return response()->json(['error' => 'Không thể thay đổi danh sách hàng hóa của đơn đặt hàng đã xử lý một phần hoặc đã xuất hóa đơn.'], 422);
                }
                return back()->with('error', 'Không thể thay đổi danh sách hàng hóa của đơn đặt hàng đã xử lý một phần hoặc đã xuất hóa đơn.');
            }
            if ($request->has('amount_paid') && (float)$request->input('amount_paid') !== (float)$order->amount_paid) {
                if ($request->wantsJson()) {
                    return response()->json(['error' => 'Không thể sửa đổi số tiền đặt cọc của đơn đặt hàng đã xử lý một phần hoặc đã xuất hóa đơn.'], 422);
                }
                return back()->with('error', 'Không thể sửa đổi số tiền đặt cọc của đơn đặt hàng đã xử lý một phần hoặc đã xuất hóa đơn.');
            }
        }

        // Chặn sửa amount_paid nếu đơn hàng đã có cashflow cọc
        if ($request->has('amount_paid') && (float)$request->input('amount_paid') !== (float)$order->amount_paid) {
            $hasCashflow = \App\Models\CashFlow::where('reference_type', 'Order')
                ->where('reference_code', $order->code)
                ->exists();
            if ($hasCashflow) {
                if ($request->wantsJson()) {
                    return response()->json(['error' => 'Không thể sửa đổi trực tiếp tiền cọc của đơn hàng đã ghi nhận sổ quỹ.'], 422);
                }
                return back()->with('error', 'Không thể sửa đổi trực tiếp tiền cọc của đơn hàng đã ghi nhận sổ quỹ.');
            }
        }

        $validated = $request->validate([
            'assigned_to_name' => 'nullable|string',
            'sales_channel' => 'nullable|string',
            'status' => 'nullable|string',
            'items' => 'nullable|array',
            'items.*.product_id' => 'required_with:items|exists:products,id',
            'items.*.qty' => 'required_with:items|numeric|min:1',
            'items.*.price' => 'required_with:items|numeric',
            'items.*.discount' => 'numeric',
            'items.*.serial_ids' => 'nullable|array',
            'items.*.serial_ids.*' => 'integer|exists:serial_imeis,id',
            'total_price' => 'nullable|numeric',
            'discount' => 'nullable|numeric',
            'other_fees' => 'nullable|numeric',
            'total_payment' => 'nullable|numeric',
            'amount_paid' => 'nullable|numeric',
            'note' => 'nullable|string',
        ]);

        // Update items if provided
        if ($request->has('items')) {
            // Step 22.2G: pre-flight serial validation TRƯỚC khi xoá items cũ.
            // Tránh trường hợp validate fail giữa chừng → order mất hết items cũ.
            if ($preFlight = $this->validateItemsSerials($validated['items'] ?? [])) {
                return $preFlight;
            }
            $order->items()->delete();
            foreach ($validated['items'] as $item) {
                $subtotal = ($item['qty'] * $item['price']) - ($item['discount'] ?? 0);

                // Step 22.2G: enforce Serial/IMEI required cho hàng has_serial khi update.
                $serialIds = array_values(array_filter($item['serial_ids'] ?? [], fn($v) => $v !== null && $v !== ''));
                $product = Product::find($item['product_id']);
                if ($product && $product->has_serial) {
                    if (count($serialIds) !== (int) $item['qty']) {
                        return back()->withErrors([
                            'items' => "Sản phẩm '{$product->name}' là hàng Serial/IMEI. Vui lòng chọn đủ {$item['qty']} Serial/IMEI trước khi lưu đơn (đã chọn " . count($serialIds) . ")."
                        ])->withInput();
                    }
                    $availability = app(SerialAvailabilityService::class);
                    $blocked = $availability->findBlockedIds($serialIds, $product->id);
                    if (!empty($blocked)) {
                        return back()->withErrors([
                            'items' => "Sản phẩm '{$product->name}': Serial/IMEI không khả dụng (id: " . implode(', ', $blocked) . ")."
                        ])->withInput();
                    }
                } else {
                    $serialIds = [];
                }

                $order->items()->create([
                    'product_id' => $item['product_id'],
                    'qty' => $item['qty'],
                    'price' => $item['price'],
                    'discount' => $item['discount'] ?? 0,
                    'subtotal' => $subtotal,
                    'serial_ids' => !empty($serialIds) ? $serialIds : null,
                ]);
            }
        }

        $order->update(array_filter($validated, fn($v) => $v !== null));

        ActivityLog::log('order_update', "Cập nhật đơn hàng {$order->code}", $order);

        return back()->with('success', 'Cập nhật đơn hàng thành công');
    }

    /**
     * Step 22.2G: pre-flight kiểm tra serial_ids cho tất cả items has_serial.
     * Trả về RedirectResponse khi fail (để caller `return $preFlight`), null khi ok.
     * Phải chạy TRƯỚC mọi DB write để giữ DB nhất quán.
     */
    private function validateItemsSerials(array $items)
    {
        $availability = app(SerialAvailabilityService::class);
        foreach ($items as $item) {
            $serialIds = array_values(array_filter($item['serial_ids'] ?? [], fn($v) => $v !== null && $v !== ''));
            $product = Product::find($item['product_id'] ?? null);
            if (!$product || !$product->has_serial) continue;

            $qty = (int) ($item['qty'] ?? 0);
            if (count($serialIds) !== $qty) {
                if (request()->wantsJson()) {
                    return response()->json(['error' => "Sản phẩm '{$product->name}' là hàng Serial/IMEI. Vui lòng chọn đủ {$qty} Serial/IMEI trước khi lưu đơn (đã chọn " . count($serialIds) . ")."], 422);
                }
                return back()->withErrors([
                    'items' => "Sản phẩm '{$product->name}' là hàng Serial/IMEI. Vui lòng chọn đủ {$qty} Serial/IMEI trước khi lưu đơn (đã chọn " . count($serialIds) . ")."
                ])->withInput();
            }
            $blocked = $availability->findBlockedIds($serialIds, $product->id);
            if (!empty($blocked)) {
                if (request()->wantsJson()) {
                    return response()->json(['error' => "Sản phẩm '{$product->name}': Serial/IMEI không khả dụng (id: " . implode(', ', $blocked) . ")."], 422);
                }
                return back()->withErrors([
                    'items' => "Sản phẩm '{$product->name}': Serial/IMEI không khả dụng (id: " . implode(', ', $blocked) . ")."
                ])->withInput();
            }
        }
        return null;
    }

    public function print(Order $order, PrintableOrderService $printableOrder)
    {
        return view('prints.simple_order_a4', [
            'printable' => $printableOrder->forOrder($order),
        ]);
    }

    public function export(Request $request)
    {
        $this->configureOrderFilters();
        $paymentSummary = app(OrderPaymentSummaryService::class);

        $query = Order::query()->select('orders.*')->with(['customer', 'branch']);
        $paymentSummary->addSummarySelects($query);
        $paymentSummary->applyPaymentStatusFilter($query, $request->input('payment_status'));
        if ($request->filled('has_debt')) {
            $paymentSummary->applyHasDebtFilter($query, (string) $request->input('has_debt') === '1');
        }
        $this->applyFilters($query, $request);
        $orders = $query->get();

        return \App\Services\CsvService::export(
            ['Mã đơn hàng', 'Thời gian', 'Khách hàng', 'Chi nhánh', 'Khách cần trả', 'Khách đã trả', 'Còn nợ', 'Tiền trả dư', 'Trạng thái thanh toán', 'Trạng thái', 'Ghi chú'],
            $orders->map(fn($o) => [
                $o->code,
                $o->created_at?->format('d/m/Y H:i'),
                $o->customer?->name,
                $o->branch?->name,
                $o->order_total,
                $o->order_paid_total,
                $o->order_remaining_debt,
                $o->order_credit_total,
                $o->payment_status,
                $o->status,
                $o->note,
            ]),
            'don_hang.csv'
        );
    }

    /**
     * Xử lý đơn hàng — Chuyển Order (Phiếu tạm) → Invoice (Hóa đơn).
     * Trừ kho, tính công nợ, tạo CashFlow.
     * Prior deposit (order.amount_paid) is factored in.
     */
    public function processOrder(Request $request, Order $order)
    {
        app(PartnerTransactionGuard::class)->assertCanTransact($order->customer_id, 'customer_id');

        if ($order->status === 'completed') {
            if ($request->wantsJson()) {
                return response()->json(['success' => false, 'message' => 'Đơn hàng đã được xử lý trước đó.'], 422);
            }
            return back()->with('error', 'Đơn hàng đã được xử lý trước đó.');
        }

        if ($order->status === 'cancelled' || $order->status === 'ended') {
            if ($request->wantsJson()) {
                return response()->json(['success' => false, 'message' => 'Đơn hàng đã bị hủy hoặc kết thúc.'], 422);
            }
            return back()->with('error', 'Đơn hàng đã bị hủy hoặc kết thúc.');
        }

        // If items is not passed, default to all remaining items in the order
        if (!$request->has('items')) {
            $defaultItems = [];
            foreach ($order->items as $item) {
                $rem = (int) $item->qty - (int) ($item->fulfilled_quantity ?? 0);
                if ($rem > 0) {
                    $defaultItems[] = [
                        'order_item_id' => $item->id,
                        'product_id' => $item->product_id,
                        'quantity' => $rem,
                        'serial_ids' => $item->serial_ids ?? [],
                    ];
                }
            }
            $request->merge(['items' => $defaultItems]);
        }

        $validated = $request->validate([
            'amount_paid' => 'required|numeric|min:0',
            'payment_method' => 'nullable|string',
            'from_pos' => 'nullable|boolean',
            'items' => 'required|array|min:1',
            'items.*.order_item_id' => 'nullable|exists:order_items,id',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.serial_ids' => 'nullable|array',
            'keep_remaining' => 'nullable|boolean',
            'end_remaining' => 'nullable|boolean',
            
            'delivery' => 'nullable|array',
            'delivery.is_delivery' => 'nullable|boolean',
            'delivery.delivery_mode' => 'nullable|string',
            'delivery.delivery_partner' => 'nullable|string',
            'delivery.receiver_name' => 'nullable|string',
            'delivery.receiver_phone' => 'nullable|string',
            'delivery.receiver_address' => 'nullable|string',
            'delivery.receiver_ward' => 'nullable|string',
            'delivery.receiver_district' => 'nullable|string',
            'delivery.receiver_city' => 'nullable|string',
            'delivery.weight' => 'nullable|numeric|min:0',
            'delivery.delivery_fee' => 'nullable|numeric|min:0',
            'delivery.cod_amount' => 'nullable|numeric|min:0',
            'delivery.tracking_code' => 'nullable|string',
            'delivery.delivery_note' => 'nullable|string',
        ]);

        try {
            \Illuminate\Support\Facades\DB::beginTransaction();
            app(PartnerTransactionGuard::class)->assertCanTransact($order->customer_id, 'customer_id');

            $order->load('items.product', 'customer');
            $customer = $order->customer;
            $paymentMethod = $validated['payment_method'] ?? 'cash';
            $requestItems = $validated['items'];

            // 1) Calculate subtotal, discount, total, cọc for this specific partial processing
            $invoiceSubtotal = 0;
            $invoiceDiscount = 0;
            $itemsToProcess = [];

            foreach ($requestItems as $reqItem) {
                $orderItem = null;
                if (!empty($reqItem['order_item_id'])) {
                    $orderItem = $order->items()->find($reqItem['order_item_id']);
                } else {
                    $orderItem = $order->items()->where('product_id', $reqItem['product_id'])->first();
                }

                if (!$orderItem) {
                    throw new \Exception("Dòng sản phẩm đặt hàng (ID: " . ($reqItem['order_item_id'] ?? 'N/A') . ", SP ID: {$reqItem['product_id']}) không tồn tại trong đơn hàng.");
                }

                if ((int)$orderItem->product_id !== (int)$reqItem['product_id']) {
                    throw new \Exception("Sản phẩm không khớp với dòng đặt hàng.");
                }

                $qty = (int) $reqItem['quantity'];
                $remainingQty = $orderItem->qty - ($orderItem->fulfilled_quantity ?? 0);
                if ($qty > $remainingQty) {
                    throw new \Exception("Số lượng xử lý ({$qty}) cho sản phẩm ID '{$reqItem['product_id']}' vượt quá số lượng còn lại của đơn hàng ({$remainingQty}).");
                }

                $product = $orderItem->product ? Product::lockForUpdate()->find($orderItem->product->id) : null;
                if (!$product) {
                    throw new \Exception("Không tìm thấy sản phẩm ID '{$reqItem['product_id']}'.");
                }

                // Calculate item discount partial allocation
                $lineSubtotalBeforeDiscount = $qty * $orderItem->price;
                $unitDiscount = $orderItem->qty > 0 ? ($orderItem->discount / $orderItem->qty) : 0;
                $lineDiscount = $unitDiscount * $qty;

                $invoiceSubtotal += $lineSubtotalBeforeDiscount;
                $invoiceDiscount += $lineDiscount;

                $itemsToProcess[] = [
                    'order_item' => $orderItem,
                    'product' => $product,
                    'qty' => $qty,
                    'line_discount' => $lineDiscount,
                    'req_serial_ids' => $reqItem['serial_ids'] ?? [],
                ];
            }

            $invoiceTotal = $invoiceSubtotal - $invoiceDiscount;

            // Prior deposit & progressive deposit application
            $initialDeposit = (float) ($order->amount_paid ?? 0);
            $alreadyAppliedDeposit = Invoice::where('order_id', $order->id)
                ->tap(fn (Builder $query) => BusinessStatus::scopeNotCancelled($query, 'status'))
                ->sum('order_deposit_applied_amount');

            $depositRemaining = max(0.0, $initialDeposit - $alreadyAppliedDeposit);
            $depositAppliedThisInvoice = min($depositRemaining, $invoiceTotal);

            $newPayment = $validated['amount_paid']; // Additional money customer pays right now
            $totalPaidForInvoice = $depositAppliedThisInvoice + $newPayment;
            $debtAmount = $invoiceTotal - $totalPaidForInvoice;

            // 2) Create Invoice
            $invoiceData = [
                'code' => 'HD' . time() . rand(10, 99),
                'order_id' => $order->id,
                'branch_id' => $order->branch_id,
                'subtotal' => $invoiceSubtotal,
                'discount' => $invoiceDiscount,
                'total' => $invoiceTotal,
                'customer_paid' => $totalPaidForInvoice,
                'order_deposit_applied_amount' => $depositAppliedThisInvoice,
                'customer_id' => $customer?->id,
                'created_by_name' => auth()->user()?->name ?? $order->created_by_name,
                'seller_name' => $order->assigned_to_name,
                'sales_channel' => $order->sales_channel ?? 'Bán trực tiếp',
                'price_book_name' => $order->price_book_name,
                'payment_method' => $paymentMethod,
                'note' => 'Từ đơn đặt hàng ' . $order->code,
                'status' => 'Hoàn thành',
            ];

            if ($request->has('delivery')) {
                $deliveryData = $validated['delivery'] ?? [];
                $order->fill([
                    'is_delivery' => (bool) ($deliveryData['is_delivery'] ?? false),
                    'delivery_partner' => $deliveryData['delivery_partner'] ?? null,
                    'tracking_code' => $deliveryData['tracking_code'] ?? null,
                    'delivery_fee' => $deliveryData['delivery_fee'] ?? 0,
                    'cod_amount' => $deliveryData['cod_amount'] ?? 0,
                    'receiver_name' => $deliveryData['receiver_name'] ?? null,
                    'receiver_phone' => $deliveryData['receiver_phone'] ?? null,
                    'receiver_address' => $deliveryData['receiver_address'] ?? null,
                    'receiver_ward' => $deliveryData['receiver_ward'] ?? null,
                    'receiver_district' => $deliveryData['receiver_district'] ?? null,
                    'receiver_city' => $deliveryData['receiver_city'] ?? null,
                    'weight' => $deliveryData['weight'] ?? 0,
                    'delivery_note' => $deliveryData['delivery_note'] ?? null,
                ]);

                $invoiceData = array_merge($invoiceData, [
                    'is_delivery' => (bool) ($deliveryData['is_delivery'] ?? false),
                    'delivery_partner' => $deliveryData['delivery_partner'] ?? null,
                    'tracking_code' => $deliveryData['tracking_code'] ?? null,
                    'delivery_fee' => $deliveryData['delivery_fee'] ?? 0,
                    'cod_amount' => $deliveryData['cod_amount'] ?? 0,
                    'receiver_name' => $deliveryData['receiver_name'] ?? null,
                    'receiver_phone' => $deliveryData['receiver_phone'] ?? null,
                    'receiver_address' => $deliveryData['receiver_address'] ?? null,
                    'receiver_ward' => $deliveryData['receiver_ward'] ?? null,
                    'receiver_district' => $deliveryData['receiver_district'] ?? null,
                    'receiver_city' => $deliveryData['receiver_city'] ?? null,
                    'weight' => $deliveryData['weight'] ?? 0,
                    'delivery_note' => $deliveryData['delivery_note'] ?? null,
                ]);
            }

            $invoice = \App\Models\Invoice::create($invoiceData);

            // 3) Create Invoice Items, process serials & deduct stock
            foreach ($itemsToProcess as $processItem) {
                /** @var OrderItem $orderItem */
                $orderItem = $processItem['order_item'];
                /** @var Product $product */
                $product = $processItem['product'];
                $qty = $processItem['qty'];
                $lineDiscount = $processItem['line_discount'];
                $reqSerialIds = $processItem['req_serial_ids'];

                $allowOversell = Setting::get('inventory_allow_oversell', true);
                $serialIds = [];

                if ($product->has_serial) {
                    $serialIds = array_values(array_filter($reqSerialIds, fn($v) => $v !== null && $v !== ''));
                    if (count($serialIds) !== $qty) {
                        throw new \Exception("Sản phẩm '{$product->name}' là hàng Serial/IMEI. Vui lòng chọn đủ {$qty} Serial/IMEI (đang chọn " . count($serialIds) . ").");
                    }



                    $availability = app(SerialAvailabilityService::class);
                    $blocked = $availability->findBlockedIds($serialIds, $product->id);
                    if (!empty($blocked)) {
                        throw new \Exception("Sản phẩm '{$product->name}': Serial/IMEI không khả dụng (id: " . implode(', ', $blocked) . ").");
                    }
                } elseif (!$allowOversell && $product->stock_quantity < $qty) {
                    throw new \Exception("Sản phẩm [{$product->sku}] {$product->name} không đủ tồn kho (Còn: {$product->stock_quantity})");
                }

                $costSnapshot = (float) ($product->cost_price ?? 0);

                // Create Invoice Item
                $invoiceItem = $invoice->items()->create([
                    'product_id' => $product->id,
                    'order_item_id' => $orderItem->id,
                    'quantity'   => $qty,
                    'price'      => $orderItem->price,
                    'cost_price' => $costSnapshot,
                ]);

                // Serials
                if ($product->has_serial && !empty($serialIds)) {
                    $soldSerials = SerialImei::whereIn('id', $serialIds)
                        ->where('product_id', $product->id)
                        ->get();
                    foreach ($soldSerials as $serial) {
                        InvoiceItemSerial::create([
                            'invoice_item_id' => $invoiceItem->id,
                            'serial_imei_id'  => $serial->id,
                            'serial_number'   => $serial->serial_number,
                            'cost_price'      => $costSnapshot,
                        ]);
                        $serial->status          = 'sold';
                        $serial->sold_at         = now();
                        $serial->invoice_id      = $invoice->id;
                        $serial->sold_cost_price = $costSnapshot;
                        $serial->save();
                    }
                }

                // Deduct stock
                MovingAvgCostingService::applySale($product, $qty);
                $product->refresh();
                if ($product->has_serial) {
                    $product->recomputeFromSerials();
                }

                // Record StockMovement
                StockMovementService::record(
                    $product->fresh(),
                    StockMovementService::TYPE_OUT_INVOICE,
                    $qty,
                    $costSnapshot,
                    $invoice,
                    [
                        'branch_id' => $invoice->branch_id ?? null,
                        'ref_code'  => $invoice->code,
                        'moved_at'  => $invoice->created_at ?? now(),
                        'note'      => "Xuất bán một phần từ đơn hàng {$order->code} sang hóa đơn {$invoice->code}",
                    ]
                );

                // Increment fulfilled quantity
                $orderItem->increment('fulfilled_quantity', $qty);
            }

            // 4) Customer debt tracking
            if ($customer) {
                if (abs($debtAmount) >= 0.01) {
                    app(CustomerDebtService::class)->recordInvoiceBalanceEffect(
                        $customer->id,
                        (float) $debtAmount,
                        $invoice,
                        "Ghi nợ khi xử lý một phần đơn hàng {$order->code} sang hóa đơn {$invoice->code}",
                        [
                            'order_id' => $order->id,
                            'ref_code' => $invoice->code,
                            'type' => 'sale',
                        ]
                    );
                }
                $customer->increment('total_spent', $invoiceTotal);
            }

            // 5) CashFlow for the additional payment (not deposit)
            if ($newPayment > 0) {
                \App\Models\CashFlow::create([
                    'code' => 'PT' . time() . rand(10, 99),
                    'type' => 'receipt',
                    'amount' => $newPayment,
                    'time' => now(),
                    'category' => 'Thu tiền khách trả',
                    'target_type' => 'Khách hàng',
                    'target_id' => $customer?->id,
                    'target_name' => $customer?->name ?? 'Khách lẻ',
                    'reference_type' => 'Invoice',
                    'reference_code' => $invoice->code,
                    'payment_method' => $paymentMethod,
                    'description' => 'Xử lý đơn ' . $order->code . ' → HD ' . $invoice->code,
                ]);
            }


            // 6) Check and update Order Status
            $hasRemaining = $order->items()->get()->contains(function ($it) {
                return ($it->qty - $it->fulfilled_quantity) > 0;
            });

            if (!$hasRemaining) {
                $order->status = 'completed';
            } else {
                if ($request->boolean('end_remaining')) {
                    $order->status = 'ended';
                } elseif ($request->boolean('keep_remaining')) {
                    // Keep current status (confirmed or delivering)
                }
            }
            $order->save();

            // Auto-generate warranty records
            app(\App\Services\WarrantyGenerationService::class)->generateForInvoice($invoice);

            ActivityLog::log('order_convert', "Chuyển đơn một phần {$order->code} → hóa đơn {$invoice->code}, status: {$order->status}", $order);

            \Illuminate\Support\Facades\DB::commit();

            if ($request->wantsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => "Xử lý thành công! Hóa đơn {$invoice->code} đã được tạo.",
                    'invoice_id' => $invoice->id,
                    'invoice_code' => $invoice->code,
                ]);
            }

            return back()->with('success', "Xử lý thành công! Hóa đơn {$invoice->code} đã được tạo.");
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\DB::rollBack();

            if ($request->wantsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Lỗi: ' . $e->getMessage(),
                ], 422);
            }

            return back()->with('error', 'Lỗi: ' . $e->getMessage());
        }
    }

    /**
     * Hủy đơn hàng.
     */
    public function cancel(Request $request, Order $order)
    {
        if ($order->status === 'completed') {
            if ($request->wantsJson()) {
                return response()->json(['success' => false, 'message' => 'Không thể hủy đơn hàng đã hoàn thành.'], 422);
            }
            return back()->with('error', 'Không thể hủy đơn hàng đã hoàn thành.');
        }
        if ($order->status === 'cancelled') {
            if ($request->wantsJson()) {
                return response()->json(['success' => false, 'message' => 'Đơn hàng đã bị hủy trước đó.'], 422);
            }
            return back()->with('error', 'Đơn hàng đã bị hủy trước đó.');
        }
        if ($order->status === 'ended') {
            if ($request->wantsJson()) {
                return response()->json(['success' => false, 'message' => 'Đơn hàng đã kết thúc.'], 422);
            }
            return back()->with('error', 'Đơn hàng đã kết thúc.');
        }

        $order->update([
            'status' => 'cancelled',
            'note' => ($order->note ? $order->note . ' | ' : '') . 'Hủy: ' . ($request->reason ?? ''),
        ]);

        ActivityLog::log('order_cancel', "Hủy đơn hàng {$order->code}" . ($request->reason ? " - Lý do: {$request->reason}" : ""), $order);

        if ($request->wantsJson()) {
            return response()->json(['success' => true, 'message' => 'Đã hủy đơn hàng thành công.']);
        }

        return back()->with('success', 'Đã hủy đơn hàng.');
    }

    /**
     * Kết thúc đơn hàng (đóng mà không chuyển hóa đơn).
     */
    public function endOrder(Request $request, Order $order)
    {
        if (in_array($order->status, ['completed', 'cancelled', 'ended'])) {
            if ($request->wantsJson()) {
                return response()->json(['success' => false, 'message' => 'Đơn hàng không ở trạng thái có thể kết thúc.'], 422);
            }
            return back()->with('error', 'Đơn hàng không ở trạng thái có thể kết thúc.');
        }

        $order->update([
            'status' => 'ended',
            'note' => ($order->note ? $order->note . ' | ' : '') . 'Kết thúc: ' . ($request->reason ?? ''),
        ]);

        ActivityLog::log('order_end', "Kết thúc đơn hàng {$order->code}" . ($request->reason ? " - Lý do: {$request->reason}" : ""), $order);

        if ($request->wantsJson()) {
            return response()->json(['success' => true, 'message' => 'Đã kết thúc đơn hàng thành công.']);
        }

        return back()->with('success', 'Đã kết thúc đơn hàng.');
    }

    /**
     * Merge compatible orders — same customer, same branch, draft/confirmed states.
     */
    public function merge(Request $request)
    {
        $request->validate([
            'order_ids' => 'required|array|min:2',
            'order_ids.*' => 'required|exists:orders,id',
        ]);

        $orders = Order::with('items')->whereIn('id', $request->order_ids)->get();

        // Validate compatibility
        $customers = $orders->pluck('customer_id')->unique();
        if ($customers->count() > 1) {
            return back()->with('error', 'Không thể gộp: các đơn hàng phải cùng khách hàng.');
        }

        $branches = $orders->pluck('branch_id')->unique();
        if ($branches->count() > 1) {
            return back()->with('error', 'Không thể gộp: các đơn hàng phải cùng chi nhánh.');
        }

        $invalidStates = $orders->filter(fn($o) => !in_array($o->status, ['draft', 'confirmed']));
        if ($invalidStates->isNotEmpty()) {
            return back()->with('error', 'Không thể gộp: tất cả đơn hàng phải ở trạng thái nháp hoặc đã xác nhận.');
        }

        // Chặn gộp nếu có đơn hàng đã được xử lý một phần
        $hasFulfilledItems = $orders->contains(function ($o) {
            return $o->items->contains(function ($item) {
                return $item->fulfilled_quantity > 0;
            });
        });
        if ($hasFulfilledItems) {
            return back()->with('error', 'Không thể gộp: có đơn hàng đã được xử lý một phần.');
        }

        try {
            \Illuminate\Support\Facades\DB::beginTransaction();

            // Create merged order
            $totalPrice = $orders->sum('total_price');
            $totalDiscount = $orders->sum('discount');
            $totalOther = $orders->sum('other_fees');
            $totalPayment = $totalPrice - $totalDiscount + $totalOther;
            $totalDeposit = $orders->sum('amount_paid');

            $merged = Order::create([
                'code' => 'DH' . time() . 'M',
                'customer_id' => $customers->first(),
                'branch_id' => $branches->first(),
                'status' => $orders->contains('status', 'confirmed') ? 'confirmed' : 'draft',
                'total_price' => $totalPrice,
                'discount' => $totalDiscount,
                'other_fees' => $totalOther,
                'total_payment' => $totalPayment,
                'amount_paid' => $totalDeposit,
                'note' => 'Gộp từ: ' . $orders->pluck('code')->join(', ') . ', cọc nguồn giữ ở phiếu nguồn',
                'created_by_name' => auth()->user()?->name,
                'assigned_to_name' => $orders->first()->assigned_to_name,
                'price_book_name' => $orders->first()->price_book_name,
            ]);

            // Copy and group all items by product_id
            $mergedItems = [];
            foreach ($orders as $order) {
                foreach ($order->items as $item) {
                    $pid = $item->product_id;
                    if (!isset($mergedItems[$pid])) {
                        $mergedItems[$pid] = [
                            'product_id' => $pid,
                            'qty'        => 0.0,
                            'price'      => $item->price,
                            'discount'   => 0.0,
                            'subtotal'   => 0.0,
                            'serial_ids' => [],
                        ];
                    }
                    $mergedItems[$pid]['qty'] += (float)$item->qty;
                    $mergedItems[$pid]['discount'] += (float)$item->discount;
                    $mergedItems[$pid]['subtotal'] += (float)$item->subtotal;

                    $sIds = $item->serial_ids;
                    if (is_array($sIds)) {
                        $mergedItems[$pid]['serial_ids'] = array_merge($mergedItems[$pid]['serial_ids'], $sIds);
                    }
                }
                // Cancel source orders
                $order->update([
                    'status' => 'cancelled',
                    'note' => ($order->note ? $order->note . ' | ' : '') . 'Đã gộp vào ' . $merged->code,
                ]);
            }

            foreach ($mergedItems as $itemData) {
                $serialIds = array_values(array_unique($itemData['serial_ids']));
                $merged->items()->create([
                    'product_id' => $itemData['product_id'],
                    'qty'        => $itemData['qty'],
                    'price'      => $itemData['price'],
                    'discount'   => $itemData['discount'],
                    'subtotal'   => $itemData['subtotal'],
                    'serial_ids' => !empty($serialIds) ? $serialIds : null,
                ]);
            }

            ActivityLog::log('order_merge', "Gộp đơn hàng: " . $orders->pluck('code')->join(', ') . " → {$merged->code}", $merged);

            \Illuminate\Support\Facades\DB::commit();

            if ($request->wantsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => "Đã gộp thành đơn hàng {$merged->code}",
                    'order_id' => $merged->id,
                    'order_code' => $merged->code,
                    'order' => [
                        'id' => $merged->id,
                        'code' => $merged->code,
                    ],
                ]);
            }

            return back()->with('success', "Đã gộp thành đơn hàng {$merged->code}");
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\DB::rollBack();
            if ($request->wantsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Lỗi: ' . $e->getMessage(),
                ], 422);
            }
            return back()->with('error', 'Lỗi: ' . $e->getMessage());
        }
    }

    public function posPayload(Request $request, string $orderKey)
    {
        $orderKey = trim((string) $orderKey);

        $query = Order::query()
            ->with(['customer', 'branch', 'items.product']);

        $order = $query
            ->where(function ($q) use ($orderKey) {
                if (ctype_digit($orderKey)) {
                    $q->where('id', (int) $orderKey);
                }
                $q->orWhere('code', $orderKey);
            })
            ->first();

        if (!$order) {
            return response()->json([
                'success' => false,
                'message' => "Không tìm thấy đơn đặt hàng '{$orderKey}'. Vui lòng mở đơn từ danh sách Đơn hàng.",
                'error_code' => 'ORDER_NOT_FOUND',
                'order_key' => $orderKey,
            ], 404);
        }

        if ($order->status === 'completed') {
            return response()->json([
                'success' => false,
                'message' => 'Đơn hàng đã được xử lý.',
                'error_code' => 'ORDER_ALREADY_COMPLETED',
                'order_key' => $orderKey,
                'order_code' => $order->code,
            ], 422);
        }

        if ($order->status === 'cancelled') {
            return response()->json([
                'success' => false,
                'message' => 'Đơn hàng đã bị hủy.',
                'error_code' => 'ORDER_CANCELLED',
                'order_key' => $orderKey,
                'order_code' => $order->code,
            ], 422);
        }

        if ($order->status === 'ended') {
            return response()->json([
                'success' => false,
                'message' => 'Đơn hàng đã kết thúc.',
                'error_code' => 'ORDER_ENDED',
                'order_key' => $orderKey,
                'order_code' => $order->code,
            ], 422);
        }

        if ($order->items->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'Đơn hàng không có sản phẩm.',
                'error_code' => 'ORDER_EMPTY',
                'order_key' => $orderKey,
                'order_code' => $order->code,
            ], 422);
        }

        $items = $order->items->map(function ($item) {
            $selectedSerials = [];
            if ($item->product?->has_serial && is_array($item->serial_ids) && !empty($item->serial_ids)) {
                $selectedSerials = \App\Models\SerialImei::whereIn('id', $item->serial_ids)
                    ->get(['id', 'serial_number'])
                    ->toArray();
            }
            $fulfilledQty = (int) ($item->fulfilled_quantity ?? 0);
            $remainingQty = max(0, (int) $item->qty - $fulfilledQty);
            $lineTotal = (float) ($item->qty * $item->price - ($item->discount ?? 0));
            return [
                'order_item_id' => $item->id,
                'product_id' => $item->product_id,
                'product' => $item->product ? [
                    'id' => $item->product->id,
                    'sku' => $item->product->sku,
                    'name' => $item->product->name,
                ] : null,
                'sku' => $item->product?->sku,
                'code' => $item->product?->sku,
                'barcode' => $item->product?->barcode,
                'name' => $item->product?->name,
                'qty' => (int) $item->qty,
                'quantity' => (int) $item->qty,
                'fulfilled_quantity' => $fulfilledQty,
                'remaining_quantity' => $remainingQty,
                'price' => (float) $item->price,
                'discount' => (float) ($item->discount ?? 0),
                'subtotal' => $lineTotal,
                'total' => $lineTotal,
                'has_serial' => (bool) $item->product?->has_serial,
                'stock_quantity' => (float) ($item->product?->stock_quantity ?? 0),
                'serial_ids' => $item->serial_ids ?? [],
                'selected_serials' => $selectedSerials,
            ];
        });

        $paymentSummary = app(OrderPaymentSummaryService::class)->summary($order);
        $customerReceivableBefore = (float) ($order->customer?->debt_amount ?? 0);
        $suggestedPayment = $paymentSummary['order_remaining_debt'];

        return response()->json([
            'success' => true,
            'resolved_by' => [
                'input' => $orderKey,
                'id' => $order->id,
                'code' => $order->code,
            ],
            'order' => [
                'id' => $order->id,
                'code' => $order->code,
                'status' => $order->status,
                'created_at' => $order->created_at?->toIso8601String(),
                'customer' => $order->customer ? [
                    'id' => $order->customer->id,
                    'code' => $order->customer->code,
                    'name' => $order->customer->name,
                    'phone' => $order->customer->phone,
                    'address' => $order->customer->address,
                    'debt_amount' => (float) ($order->customer->debt_amount ?? 0),
                    'supplier_debt_amount' => (float) ($order->customer->supplier_debt_amount ?? 0),
                ] : null,
                'branch' => $order->branch ? [
                    'id' => $order->branch->id,
                    'name' => $order->branch->name,
                    'address' => $order->branch->address,
                    'phone' => $order->branch->phone,
                ] : null,
                'items' => $items,
                'totals' => [
                    'total_price' => (float) $order->total_price,
                    'discount' => (float) $order->discount,
                    'other_fees' => (float) $order->other_fees,
                    'total_payment' => (float) $order->total_payment,
                    'amount_paid' => (float) ($order->amount_paid ?? 0),
                    'remaining' => $paymentSummary['order_remaining_debt'],
                    'order_deposit_original' => $paymentSummary['original_deposit'],
                    'total_paid_for_order' => $paymentSummary['order_paid_total'],
                    'deposit_total' => $paymentSummary['original_deposit'],
                    ...$paymentSummary,
                    'amount_to_collect_before_this_time' => $paymentSummary['order_remaining_debt'],
                    'customer_receivable_before' => $customerReceivableBefore,
                    'suggested_customer_pay_now' => $suggestedPayment,
                    'remaining_after_suggested_payment' => max(0.0, $paymentSummary['order_remaining_debt'] - $suggestedPayment),
                    'customer_receivable_after_preview' => $customerReceivableBefore,
                ],
                'delivery' => [
                    'is_delivery' => (bool) $order->is_delivery,
                    'delivery_mode' => $order->delivery_partner ? 'partner' : ($order->is_delivery ? 'self' : 'none'),
                    'delivery_partner' => $order->delivery_partner,
                    'receiver_name' => $order->receiver_name,
                    'receiver_phone' => $order->receiver_phone,
                    'receiver_address' => $order->receiver_address,
                    'receiver_ward' => $order->receiver_ward,
                    'receiver_district' => $order->receiver_district,
                    'receiver_city' => $order->receiver_city,
                    'weight' => (float) ($order->weight ?? 0),
                    'delivery_fee' => (float) ($order->delivery_fee ?? 0),
                    'cod_amount' => (float) ($order->cod_amount ?? 0),
                    'tracking_code' => $order->tracking_code,
                    'delivery_note' => $order->delivery_note,
                ],
                'note' => $order->note,
            ]
        ]);
    }

    private function recordOrderDepositCashFlow(Order $order, $paymentMethod = 'cash', $bankInfo = null): void
    {
        if ($order->amount_paid <= 0) {
            return;
        }

        // Kiểm tra không tạo trùng
        $exists = \App\Models\CashFlow::where('reference_type', 'Order')
            ->where('reference_code', $order->code)
            ->where('amount', $order->amount_paid)
            ->exists();
        if ($exists) {
            return;
        }

        $customer = $order->customer;
        \App\Models\CashFlow::create([
            'code' => 'PT' . time() . rand(10, 99),
            'type' => 'receipt',
            'amount' => $order->amount_paid,
            'time' => now(),
            'category' => 'Thu đặt cọc đơn đặt hàng',
            'target_type' => 'Khách hàng',
            'target_id' => $customer?->id,
            'target_name' => $customer?->name ?? 'Khách lẻ',
            'reference_type' => 'Order',
            'reference_code' => $order->code,
            'payment_method' => $paymentMethod ?? 'cash',
            'description' => 'Thu đặt cọc đơn đặt hàng ' . $order->code . ($bankInfo ? ' - CK: ' . $bankInfo : ''),
        ]);
    }
}
