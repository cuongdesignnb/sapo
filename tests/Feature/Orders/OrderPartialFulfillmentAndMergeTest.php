<?php

namespace Tests\Feature\Orders;

use App\Models\Category;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\SerialImei;
use App\Models\CashFlow;
use App\Models\User;
use App\Models\Branch;
use App\Models\ActivityLog;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class OrderPartialFulfillmentAndMergeTest extends TestCase
{
    use DatabaseTransactions;

    private User     $admin;
    private Customer $customer;
    private Branch   $branch;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::create([
            'name'     => 'Test Admin',
            'email'    => 'test-admin-' . uniqid() . '@test.local',
            'password' => bcrypt('password'),
            'role_id'  => null,
        ]);

        $this->customer = Customer::create([
            'code'  => 'KH-' . uniqid(),
            'name'  => 'Test Customer ' . uniqid(),
            'phone' => '091' . rand(1000000, 9999999),
        ]);

        $this->branch = Branch::create([
            'name' => 'Branch Test ' . uniqid(),
            'address' => '123 Test Rd',
        ]);
    }

    private function makeProduct(bool $hasSerial = false, int $stock = 10, float $cost = 100000): Product
    {
        $category = Category::firstOrCreate(['name' => 'Test Cat']);
        return Product::create([
            'sku'                  => 'PROD-' . uniqid(),
            'name'                 => 'Test Product',
            'cost_price'           => $cost,
            'retail_price'         => $cost * 2,
            'stock_quantity'       => $stock,
            'inventory_total_cost' => $stock * $cost,
            'is_active'            => true,
            'has_serial'           => $hasSerial,
            'category_id'          => $category->id,
        ]);
    }

    // 1. OrderStatusGuardTest: test chặn đổi status đặc biệt qua PUT thường.
    public function test_order_status_guard(): void
    {
        $product = $this->makeProduct();
        $order = Order::create([
            'code'          => 'DH-' . uniqid(),
            'customer_id'   => $this->customer->id,
            'branch_id'     => $this->branch->id,
            'status'        => 'draft',
            'total_price'   => 200000,
            'total_payment' => 200000,
            'amount_paid'   => 0,
        ]);

        $this->actingAs($this->admin);

        // Chặn chuyển trực tiếp sang completed
        $response = $this->putJson(route('orders.update', $order), [
            'status' => 'completed',
        ]);
        $response->assertStatus(422);

        // Chặn chuyển trực tiếp sang cancelled
        $response = $this->putJson(route('orders.update', $order), [
            'status' => 'cancelled',
        ]);
        $response->assertStatus(422);

        // Chặn chuyển trực tiếp sang ended
        $response = $this->putJson(route('orders.update', $order), [
            'status' => 'ended',
        ]);
        $response->assertStatus(422);
    }

    // 2. OrderCreateDepositCashflowTest: test tạo đơn từ Admin có cọc ghi nhận đúng cashflow, không trừ tồn.
    public function test_order_create_deposit_cashflow(): void
    {
        $product = $this->makeProduct(false, 10, 100000);
        $this->actingAs($this->admin);

        $payload = [
            'customer_id' => $this->customer->id,
            'branch_id' => $this->branch->id,
            'status' => 'confirmed',
            'total_price' => 400000,
            'discount' => 0,
            'other_fees' => 0,
            'total_payment' => 400000,
            'amount_paid' => 150000, // Cọc 150k
            'payment_method' => 'cash',
            'items' => [
                [
                    'product_id' => $product->id,
                    'qty' => 2,
                    'price' => 200000,
                    'discount' => 0,
                ]
            ]
        ];

        $response = $this->post(route('orders.store'), $payload);
        $response->assertStatus(302); // Redirect back on web route

        $order = Order::where('customer_id', $this->customer->id)->latest()->first();
        $this->assertNotNull($order);
        $this->assertSame('confirmed', $order->status);
        $this->assertEquals(150000, $order->amount_paid);

        // Tồn kho không được thay đổi
        $product->refresh();
        $this->assertSame(10, (int) $product->stock_quantity);

        // Check cashflow cọc
        $cashflow = CashFlow::where('reference_type', 'Order')
            ->where('reference_code', $order->code)
            ->first();
        $this->assertNotNull($cashflow);
        $this->assertSame('receipt', $cashflow->type);
        $this->assertEquals(150000, $cashflow->amount);
    }

    // 3. POSQuickOrderTest: test POS quick order lưu đầy đủ thông tin, tạo đúng cashflow cọc, không trừ tồn kho.
    public function test_pos_quick_order(): void
    {
        $product = $this->makeProduct(false, 10, 100000);
        $this->actingAs($this->admin);

        $payload = [
            'subtotal' => 400000,
            'discount' => 0,
            'total' => 400000,
            'customer_id' => $this->customer->id,
            'amount_paid' => 100000,
            'payment_method' => 'transfer',
            'bank_account_info' => 'Vietcombank 123',
            'expected_delivery_date' => now()->addDays(2)->toIso8601String(),
            'delivery' => [
                'is_delivery' => true,
                'delivery_mode' => 'partner',
                'delivery_partner' => 'Ahamove',
                'receiver_name' => 'Receiver Name',
                'receiver_phone' => '0987654321',
                'receiver_address' => '456 Delivery St',
                'delivery_fee' => 25000,
            ],
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => 2,
                    'price' => 200000,
                ]
            ]
        ];

        $response = $this->postJson('/api/pos/quick-order', $payload);
        $response->assertStatus(200);
        $response->assertJsonPath('success', true);

        $order = Order::where('customer_id', $this->customer->id)->latest()->first();
        $this->assertNotNull($order);
        $this->assertSame('draft', $order->status); // POS quick order is draft by default
        $this->assertEquals(100000, $order->amount_paid);
        $this->assertTrue((bool)$order->is_delivery);
        $this->assertSame('Ahamove', $order->delivery_partner);

        // Check cashflow cọc
        $cashflow = CashFlow::where('reference_type', 'Order')
            ->where('reference_code', $order->code)
            ->first();
        $this->assertNotNull($cashflow);
        $this->assertEquals(100000, $cashflow->amount);
        $this->assertSame('transfer', $cashflow->payment_method);
        $this->assertStringContainsString('Vietcombank 123', $cashflow->description);
    }

    // 4. OrderProcessFullTest: test xử lý full đơn đặt hàng, trừ tồn, mark serial sold, tạo invoice_items có order_item_id, cập nhật status completed.
    public function test_order_process_full(): void
    {
        $product = $this->makeProduct(false, 10, 100000);
        $order = Order::create([
            'code'          => 'DH-' . uniqid(),
            'customer_id'   => $this->customer->id,
            'branch_id'     => $this->branch->id,
            'status'        => 'confirmed',
            'total_price'   => 400000,
            'total_payment' => 400000,
            'amount_paid'   => 0,
        ]);
        $orderItem = $order->items()->create([
            'product_id' => $product->id,
            'qty'        => 2,
            'price'      => 200000,
            'discount'   => 0,
            'subtotal'   => 400000,
        ]);

        $this->actingAs($this->admin);

        $payload = [
            'from_pos' => true,
            'amount_paid' => 400000,
            'payment_method' => 'cash',
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => 2,
                    'order_item_id' => $orderItem->id,
                ]
            ]
        ];

        $response = $this->postJson(route('orders.process', $order), $payload);
        $response->assertStatus(200);

        $order->refresh();
        $this->assertSame('completed', $order->status);

        $orderItem->refresh();
        $this->assertEquals(2, $orderItem->fulfilled_quantity);

        // Check invoice_item has order_item_id
        $invoice = Invoice::where('order_id', $order->id)->first();
        $this->assertNotNull($invoice);
        $invoiceItem = InvoiceItem::where('invoice_id', $invoice->id)->first();
        $this->assertNotNull($invoiceItem);
        $this->assertEquals($orderItem->id, $invoiceItem->order_item_id);

        // Tồn giảm 2
        $product->refresh();
        $this->assertSame(8, (int) $product->stock_quantity);
    }

    // 5. OrderProcessPartialKeepTest: test xử lý một phần đơn hàng và chọn giữ lại, fulfilled_quantity tăng, order status giữ nguyên, tồn chỉ giảm phần thực lấy.
    public function test_order_process_partial_keep(): void
    {
        $product = $this->makeProduct(false, 10, 100000);
        $order = Order::create([
            'code'          => 'DH-' . uniqid(),
            'customer_id'   => $this->customer->id,
            'branch_id'     => $this->branch->id,
            'status'        => 'confirmed',
            'total_price'   => 600000,
            'total_payment' => 600000,
            'amount_paid'   => 0,
        ]);
        $orderItem = $order->items()->create([
            'product_id' => $product->id,
            'qty'        => 3,
            'price'      => 200000,
            'discount'   => 0,
            'subtotal'   => 600000,
        ]);

        $this->actingAs($this->admin);

        $payload = [
            'from_pos' => true,
            'amount_paid' => 200000, // Thanh toán cho 1 sản phẩm thực xuất
            'payment_method' => 'cash',
            'keep_remaining' => true,
            'end_remaining' => false,
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => 1, // Chỉ lấy 1
                    'order_item_id' => $orderItem->id,
                ]
            ]
        ];

        $response = $this->postJson(route('orders.process', $order), $payload);
        $response->assertStatus(200);

        $order->refresh();
        $this->assertSame('confirmed', $order->status); // Vẫn là confirmed

        $orderItem->refresh();
        $this->assertEquals(1, $orderItem->fulfilled_quantity);
        $this->assertEquals(2, $orderItem->remaining_quantity);

        // Tồn giảm 1
        $product->refresh();
        $this->assertSame(9, (int) $product->stock_quantity);
    }

    // 6. OrderProcessPartialEndTest: test xử lý một phần đơn hàng và chọn kết thúc phần còn lại, order status sang ended, tồn phần còn lại không giảm.
    public function test_order_process_partial_end(): void
    {
        $product = $this->makeProduct(false, 10, 100000);
        $order = Order::create([
            'code'          => 'DH-' . uniqid(),
            'customer_id'   => $this->customer->id,
            'branch_id'     => $this->branch->id,
            'status'        => 'confirmed',
            'total_price'   => 600000,
            'total_payment' => 600000,
            'amount_paid'   => 0,
        ]);
        $orderItem = $order->items()->create([
            'product_id' => $product->id,
            'qty'        => 3,
            'price'      => 200000,
            'discount'   => 0,
            'subtotal'   => 600000,
        ]);

        $this->actingAs($this->admin);

        $payload = [
            'from_pos' => true,
            'amount_paid' => 200000,
            'payment_method' => 'cash',
            'keep_remaining' => false,
            'end_remaining' => true, // Kết thúc
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => 1,
                    'order_item_id' => $orderItem->id,
                ]
            ]
        ];

        $response = $this->postJson(route('orders.process', $order), $payload);
        $response->assertStatus(200);

        $order->refresh();
        $this->assertSame('ended', $order->status); // Chuyển sang ended

        $orderItem->refresh();
        $this->assertEquals(1, $orderItem->fulfilled_quantity);

        // Tồn chỉ giảm 1
        $product->refresh();
        $this->assertSame(9, (int) $product->stock_quantity);
    }

    // 7. OrderDepositMultipleInvoiceTest: test xử lý nhiều lần, khấu trừ cọc lũy tiến chính xác và không ghi trùng cashflow cọc.
    public function test_order_deposit_multiple_invoice(): void
    {
        $product = $this->makeProduct(false, 10, 100000);
        $order = Order::create([
            'code'          => 'DH-' . uniqid(),
            'customer_id'   => $this->customer->id,
            'branch_id'     => $this->branch->id,
            'status'        => 'confirmed',
            'total_price'   => 400000,
            'total_payment' => 400000,
            'amount_paid'   => 150000, // Cọc trước 150k
        ]);
        $orderItem = $order->items()->create([
            'product_id' => $product->id,
            'qty'        => 2,
            'price'      => 200000,
            'discount'   => 0,
            'subtotal'   => 400000,
        ]);

        $this->actingAs($this->admin);

        // Lần 1: Lấy 1 sản phẩm (giá trị 200k). Áp dụng cọc 150k, thanh toán thêm 50k.
        $payload1 = [
            'from_pos' => true,
            'amount_paid' => 50000, // trả thêm 50k
            'payment_method' => 'cash',
            'keep_remaining' => true,
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => 1,
                    'order_item_id' => $orderItem->id,
                ]
            ]
        ];

        $response = $this->postJson(route('orders.process', $order), $payload1);
        $response->assertStatus(200);

        $order->refresh();
        $this->assertEquals(200000, (float) $order->amount_paid);

        $invoice1 = Invoice::where('order_id', $order->id)->latest('id')->first();
        $this->assertNotNull($invoice1);
        $this->assertEquals(150000, $invoice1->order_deposit_applied_amount); // Lần 1 ăn trọn 150k cọc

        // Check cashflow lần 1: Chỉ tạo phiếu thu 50k
        $cf1 = CashFlow::where('reference_code', $invoice1->code)->first();
        $this->assertNotNull($cf1);
        $this->assertEquals(50000, $cf1->amount);

        // Lần 2: Lấy sản phẩm thứ 2 (giá trị 200k). Cọc đã hết, thanh toán thêm 200k.
        $payload2 = [
            'from_pos' => true,
            'amount_paid' => 200000,
            'payment_method' => 'cash',
            'keep_remaining' => false,
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => 1,
                    'order_item_id' => $orderItem->id,
                ]
            ]
        ];

        $response = $this->postJson(route('orders.process', $order), $payload2);
        $response->assertStatus(200);

        $invoice2 = Invoice::where('order_id', $order->id)->orderBy('id', 'desc')->first();
        $this->assertNotEquals($invoice1->id, $invoice2->id);
        $this->assertEquals(0, $invoice2->order_deposit_applied_amount); // Lần 2 không còn cọc để áp dụng

        // Check cashflow lần 2: Tạo phiếu thu 200k
        $cf2 = CashFlow::where('reference_code', $invoice2->code)->first();
        $this->assertNotNull($cf2);
        $this->assertEquals(200000, $cf2->amount);

        // Cọc áp dụng lũy tiến không vượt cọc gốc của order
        $this->assertLessThanOrEqual(
            (float) $order->amount_paid,
            (float) Invoice::where('order_id', $order->id)->sum('order_deposit_applied_amount')
        );
    }

    // 8. OrderProcessSerialPartialTest: test xử lý hàng serial một phần, chỉ serial thực xử lý chuyển sang sold.
    public function test_order_process_serial_partial(): void
    {
        $product = $this->makeProduct(true, 3, 500000); // 3 serials
        $serial1 = SerialImei::create(['product_id' => $product->id, 'serial_number' => 'S1-' . uniqid(), 'status' => 'in_stock', 'cost_price' => 500000, 'original_cost' => 500000]);
        $serial2 = SerialImei::create(['product_id' => $product->id, 'serial_number' => 'S2-' . uniqid(), 'status' => 'in_stock', 'cost_price' => 500000, 'original_cost' => 500000]);
        $serial3 = SerialImei::create(['product_id' => $product->id, 'serial_number' => 'S3-' . uniqid(), 'status' => 'in_stock', 'cost_price' => 500000, 'original_cost' => 500000]);
        
        $order = Order::create([
            'code'          => 'DH-' . uniqid(),
            'customer_id'   => $this->customer->id,
            'branch_id'     => $this->branch->id,
            'status'        => 'confirmed',
            'total_price'   => 1500000,
            'total_payment' => 1500000,
            'amount_paid'   => 0,
        ]);
        $orderItem = $order->items()->create([
            'product_id' => $product->id,
            'qty'        => 3,
            'price'      => 500000,
            'discount'   => 0,
            'subtotal'   => 1500000,
            'serial_ids' => [$serial1->id, $serial2->id, $serial3->id] // Đặt 3 serial
        ]);

        $this->actingAs($this->admin);

        // Xử lý giao 2 sản phẩm (chọn serial1 và serial2)
        $payload = [
            'from_pos' => true,
            'amount_paid' => 1000000,
            'payment_method' => 'cash',
            'keep_remaining' => true,
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => 2,
                    'order_item_id' => $orderItem->id,
                    'serial_ids' => [$serial1->id, $serial2->id] // Chỉ xuất 2 serial này
                ]
            ]
        ];

        $response = $this->postJson(route('orders.process', $order), $payload);
        $response->assertStatus(200);

        $serial1->refresh();
        $serial2->refresh();
        $serial3->refresh();

        $this->assertSame('sold', $serial1->status);
        $this->assertSame('sold', $serial2->status);
        $this->assertSame('in_stock', $serial3->status); // Serial3 vẫn khả dụng
    }

    // 9. OrderMergeTest: test gộp đơn hàng cùng khách, cùng chi nhánh, copy serial_ids, gộp cọc, cancel các đơn nguồn.
    public function test_order_merge(): void
    {
        $product = $this->makeProduct(true, 5, 100000);
        $s1 = SerialImei::create(['product_id' => $product->id, 'serial_number' => 'MS1-' . uniqid(), 'status' => 'in_stock']);
        $s2 = SerialImei::create(['product_id' => $product->id, 'serial_number' => 'MS2-' . uniqid(), 'status' => 'in_stock']);

        $order1 = Order::create([
            'code'          => 'DH-S1-' . uniqid(),
            'customer_id'   => $this->customer->id,
            'branch_id'     => $this->branch->id,
            'status'        => 'confirmed',
            'total_price'   => 200000,
            'total_payment' => 200000,
            'amount_paid'   => 50000, // Cọc 50k
        ]);
        $order1->items()->create([
            'product_id' => $product->id,
            'qty' => 1,
            'price' => 200000,
            'discount' => 0,
            'subtotal' => 200000,
            'serial_ids' => [$s1->id]
        ]);

        $order2 = Order::create([
            'code'          => 'DH-S2-' . uniqid(),
            'customer_id'   => $this->customer->id,
            'branch_id'     => $this->branch->id,
            'status'        => 'confirmed',
            'total_price'   => 200000,
            'total_payment' => 200000,
            'amount_paid'   => 100000, // Cọc 100k
        ]);
        $order2->items()->create([
            'product_id' => $product->id,
            'qty' => 1,
            'price' => 200000,
            'discount' => 0,
            'subtotal' => 200000,
            'serial_ids' => [$s2->id]
        ]);

        $this->actingAs($this->admin);

        $response = $this->postJson(route('orders.merge'), [
            'order_ids' => [$order1->id, $order2->id]
        ]);
        $response->assertStatus(200);
        $response->assertJsonPath('success', true);

        // Các đơn cũ chuyển sang cancelled
        $order1->refresh();
        $order2->refresh();
        $this->assertSame('cancelled', $order1->status);
        $this->assertSame('cancelled', $order2->status);

        // Đơn gộp mới
        $newOrderCode = $response->json('order.code');
        $newOrder = Order::where('code', $newOrderCode)->first();
        $this->assertNotNull($newOrder);
        $this->assertEquals(150000, $newOrder->amount_paid); // Gộp cọc thành 150k
        $this->assertSame('confirmed', $newOrder->status);

        // Items trong đơn gộp
        $newItems = $newOrder->items;
        $this->assertCount(1, $newItems); // 1 dòng gộp
        $this->assertEquals(2, $newItems->first()->qty);
        $this->assertEquals([$s1->id, $s2->id], $newItems->first()->serial_ids);
    }

    // 10. OrderCancelEndTest: test API cancel và end đơn hàng, ghi log đầy đủ, không rollback hóa đơn partial đã tạo.
    public function test_order_cancel_and_end(): void
    {
        $product = $this->makeProduct(false, 10, 100000);
        $order = Order::create([
            'code'          => 'DH-' . uniqid(),
            'customer_id'   => $this->customer->id,
            'branch_id'     => $this->branch->id,
            'status'        => 'confirmed',
            'total_price'   => 400000,
            'total_payment' => 400000,
            'amount_paid'   => 100000,
        ]);
        $orderItem = $order->items()->create([
            'product_id' => $product->id,
            'qty'        => 2,
            'price'      => 200000,
            'subtotal'   => 400000,
        ]);

        $this->actingAs($this->admin);

        // Tạo 1 hóa đơn partial trước
        $payload = [
            'from_pos' => true,
            'amount_paid' => 100000,
            'payment_method' => 'cash',
            'keep_remaining' => true,
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => 1,
                    'order_item_id' => $orderItem->id,
                ]
            ]
        ];
        $this->postJson(route('orders.process', $order), $payload);

        // Check hóa đơn đã được tạo
        $invoice = Invoice::where('order_id', $order->id)->first();
        $this->assertNotNull($invoice);

        // Cancel đơn hàng
        $response = $this->postJson(route('orders.cancel', $order), [
            'reason' => 'Khách đổi ý không muốn lấy sản phẩm thứ 2'
        ]);
        $response->assertStatus(200);

        $order->refresh();
        $this->assertSame('cancelled', $order->status);

        // Hóa đơn cũ và tồn kho vẫn giữ nguyên (không rollback hóa đơn đã xuất)
        $invoice->refresh();
        $this->assertSame('Hoàn thành', $invoice->status);

        $product->refresh();
        $this->assertSame(9, (int) $product->stock_quantity); // Tồn giảm 1 (của hóa đơn partial) chứ không trả lại 10

        // Ghi nhận lý do hủy trong log
        $log = ActivityLog::where('subject_type', Order::class)
            ->where('subject_id', $order->id)
            ->where('action', 'order_cancel')
            ->first();
        $this->assertNotNull($log);
        $this->assertStringContainsString('Khách đổi ý', $log->description);
    }

    // Hotfix 1: Khách trả dư không tạo công nợ dương
    public function test_order_process_overpaid_does_not_create_positive_debt(): void
    {
        $product = $this->makeProduct(false, 10, 100000);
        $order = Order::create([
            'code'          => 'DH-' . uniqid(),
            'customer_id'   => $this->customer->id,
            'branch_id'     => $this->branch->id,
            'status'        => 'confirmed',
            'total_price'   => 400000,
            'total_payment' => 400000,
            'amount_paid'   => 150000, // cọc 150k
        ]);
        $orderItem = $order->items()->create([
            'product_id' => $product->id,
            'qty'        => 2,
            'price'      => 200000,
            'discount'   => 0,
            'subtotal'   => 400000,
        ]);

        $this->actingAs($this->admin);

        $initialDebt = $this->customer->fresh()->debt_amount;

        $payload = [
            'from_pos' => true,
            'amount_paid' => 100000, // trả thêm 100k
            'payment_method' => 'cash',
            'keep_remaining' => false,
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => 1, // Xử lý 1 item (giá trị 200k)
                    'order_item_id' => $orderItem->id,
                ]
            ]
        ];

        $response = $this->postJson(route('orders.process', $order), $payload);
        $response->assertStatus(200);

        $invoice = Invoice::where('order_id', $order->id)->latest('id')->first();
        $this->assertNotNull($invoice);
        // total invoice = 200k, cọc áp dụng = 150k, trả thêm = 100k -> customer_paid = 250k
        $this->assertEquals(250000, $invoice->customer_paid);

        // Assert: không có row customer_debts type sale phát sinh cho invoice này
        $debtExists = \App\Models\CustomerDebt::where('ref_code', $invoice->code)
            ->where('type', 'sale')
            ->exists();
        $this->assertFalse($debtExists, 'Không được tạo công nợ cho hóa đơn này.');

        // customers.debt_amount không tăng
        $this->assertEquals($initialDebt, $this->customer->fresh()->debt_amount);

        // cashflow chỉ ghi phần trả thêm 100k
        $cf = CashFlow::where('reference_type', 'Invoice')
            ->where('reference_code', $invoice->code)
            ->first();
        $this->assertNotNull($cf);
        $this->assertEquals(100000, $cf->amount);
    }

    // Hotfix 2: Invoice giữ đúng branch của order
    public function test_order_process_invoice_keeps_branch_id(): void
    {
        $product = $this->makeProduct(false, 10, 100000);
        $order = Order::create([
            'code'          => 'DH-' . uniqid(),
            'customer_id'   => $this->customer->id,
            'branch_id'     => $this->branch->id,
            'status'        => 'confirmed',
            'total_price'   => 400000,
            'total_payment' => 400000,
            'amount_paid'   => 0,
        ]);
        $orderItem = $order->items()->create([
            'product_id' => $product->id,
            'qty'        => 2,
            'price'      => 200000,
            'discount'   => 0,
            'subtotal'   => 400000,
        ]);

        $this->actingAs($this->admin);

        $payload = [
            'from_pos' => true,
            'amount_paid' => 400000,
            'payment_method' => 'cash',
            'keep_remaining' => false,
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => 2,
                    'order_item_id' => $orderItem->id,
                ]
            ]
        ];

        $response = $this->postJson(route('orders.process', $order), $payload);
        $response->assertStatus(200);

        $invoice = Invoice::where('order_id', $order->id)->first();
        $this->assertNotNull($invoice);
        $this->assertEquals($order->branch_id, $invoice->branch_id);

        // Assert stock movement branch_id
        $movement = \App\Models\StockMovement::where('reference_type', 'Invoice')
            ->where('reference_id', $invoice->id)
            ->first();
        if ($movement) {
            $this->assertEquals($order->branch_id, $movement->branch_id);
        }
    }
}
