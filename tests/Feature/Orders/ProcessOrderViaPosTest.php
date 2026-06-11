<?php

namespace Tests\Feature\Orders;

use App\Http\Controllers\OrderController;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\InvoiceItemSerial;
use App\Models\Order;
use App\Models\Product;
use App\Models\Role;
use App\Models\SerialImei;
use App\Models\StockMovement;
use App\Models\CashFlow;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Tests\TestCase;

class ProcessOrderViaPosTest extends TestCase
{
    use DatabaseTransactions;

    private User     $admin;
    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::create([
            'name'     => 'Admin POS',
            'email'    => 'admin-pos-' . uniqid() . '@test.local',
            'password' => bcrypt('password'),
            'role_id'  => null,
        ]);

        $this->customer = Customer::create([
            'code'        => 'KH-POS-' . uniqid(),
            'name'        => 'KH POS ' . uniqid(),
            'phone'       => '090' . rand(1000000, 9999999),
            'email'       => 'kh-pos-' . uniqid() . '@test.local',
            'debt_amount' => 0,
            'total_spent' => 0,
        ]);
    }

    private function makeProduct(bool $hasSerial = false, int $stock = 10, float $cost = 100000): Product
    {
        $category = Category::firstOrCreate(['name' => 'Cat POS']);
        return Product::create([
            'sku'                  => 'PROD-POS-' . uniqid(),
            'name'                 => 'Product POS',
            'cost_price'           => $cost,
            'retail_price'         => $cost * 2,
            'stock_quantity'       => $stock,
            'inventory_total_cost' => $stock * $cost,
            'is_active'            => true,
            'has_serial'           => $hasSerial,
            'category_id'          => $category->id,
        ]);
    }

    private function userWithPermissions(array $permissions): User
    {
        $role = Role::create([
            'name' => 'order-pos-' . uniqid(),
            'display_name' => 'Order POS',
            'permissions' => $permissions,
            'is_system' => false,
        ]);

        return User::create([
            'name' => 'POS User',
            'email' => 'order-pos-user-' . uniqid() . '@test.local',
            'password' => bcrypt('password'),
            'role_id' => $role->id,
        ]);
    }

    private function makeOrder(Product $product, int $qty, float $price): Order
    {
        $order = Order::create([
            'code'             => 'DH-POS-' . uniqid(),
            'customer_id'      => $this->customer->id,
            'status'           => 'draft',
            'total_price'      => $qty * $price,
            'discount'         => 0,
            'other_fees'       => 0,
            'total_payment'    => $qty * $price,
            'amount_paid'      => 0,
            'created_by_name'  => 'Admin',
            'assigned_to_name' => 'Admin',
            'price_book_name'  => 'Bảng giá chung',
        ]);
        $order->items()->create([
            'product_id' => $product->id,
            'qty'        => $qty,
            'price'      => $price,
            'discount'   => 0,
            'subtotal'   => $qty * $price,
        ]);
        return $order;
    }

    public function test_pos_payload_via_numeric_id(): void
    {
        $product = $this->makeProduct(false, 10, 100000);
        $order = $this->makeOrder($product, 3, 200000);

        $this->actingAs($this->admin);
        $response = $this->getJson(route('orders.pos-payload', ['orderKey' => $order->id]));

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('order.id', $order->id);
        $response->assertJsonPath('order.code', $order->code);
        $response->assertJsonPath('order.items.0.product_id', $product->id);
        $response->assertJsonPath('order.items.0.product.id', $product->id);
        $response->assertJsonPath('order.items.0.product.name', $product->name);
        $response->assertJsonPath('order.items.0.qty', 3);
        $response->assertJsonPath('order.items.0.quantity', 3);
        $response->assertJsonPath('order.items.0.price', 200000);
        $response->assertJsonPath('order.items.0.discount', 0);
        $response->assertJsonPath('order.items.0.total', 600000);

        $order->refresh();
        $this->assertSame('draft', $order->status);
        $this->assertSame(0, Invoice::where('order_id', $order->id)->count());
    }

    public function test_pos_payload_via_order_code(): void
    {
        $product = $this->makeProduct(false, 10, 100000);
        $order = $this->makeOrder($product, 3, 200000);

        $this->actingAs($this->admin);
        $response = $this->getJson(route('orders.pos-payload', ['orderKey' => $order->code]));

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('order.id', $order->id);
        $response->assertJsonPath('order.code', $order->code);
    }

    public function test_pos_only_user_can_load_payload_by_id_and_code(): void
    {
        $product = $this->makeProduct(false, 10, 100000);
        $order = $this->makeOrder($product, 1, 200000);
        $user = $this->userWithPermissions(['pos.use']);

        $this->actingAs($user)
            ->getJson(route('orders.pos-payload', ['orderKey' => $order->id]))
            ->assertOk()
            ->assertJsonPath('order.code', $order->code);

        $this->actingAs($user)
            ->getJson(route('orders.pos-payload', ['orderKey' => $order->code]))
            ->assertOk()
            ->assertJsonPath('order.id', $order->id);
    }

    public function test_pos_payload_uses_option_a_payments_and_ignores_cancelled_invoices(): void
    {
        $product = $this->makeProduct(false, 10, 100000);
        $paidOrder = $this->makeOrder($product, 1, 10_000_000);
        $paidOrder->update(['amount_paid' => 2_000_000]);
        Invoice::create([
            'code' => 'HD-POS-PAID-' . uniqid(),
            'order_id' => $paidOrder->id,
            'customer_id' => $this->customer->id,
            'subtotal' => 10_000_000,
            'total' => 10_000_000,
            'customer_paid' => 10_000_000,
            'order_deposit_applied_amount' => 2_000_000,
            'status' => 'completed',
        ]);

        $cancelledOrder = $this->makeOrder($product, 1, 10_000_000);
        $cancelledOrder->update(['amount_paid' => 2_000_000]);
        Invoice::create([
            'code' => 'HD-POS-CANCELLED-' . uniqid(),
            'order_id' => $cancelledOrder->id,
            'customer_id' => $this->customer->id,
            'subtotal' => 10_000_000,
            'total' => 10_000_000,
            'customer_paid' => 10_000_000,
            'order_deposit_applied_amount' => 2_000_000,
            'status' => 'Đã hủy',
        ]);

        $clampedOrder = $this->makeOrder($product, 1, 2_000_000);
        $clampedOrder->update(['amount_paid' => 2_000_000]);
        Invoice::create([
            'code' => 'HD-POS-CLAMP-' . uniqid(),
            'order_id' => $clampedOrder->id,
            'customer_id' => $this->customer->id,
            'subtotal' => 2_000_000,
            'total' => 2_000_000,
            'customer_paid' => 1_000_000,
            'order_deposit_applied_amount' => 2_000_000,
            'status' => 'completed',
        ]);

        $this->actingAs($this->admin)
            ->getJson(route('orders.pos-payload', ['orderKey' => $paidOrder->id]))
            ->assertOk()
            ->assertJsonPath('order.totals.order_deposit_original', 2_000_000)
            ->assertJsonPath('order.totals.paid_after_deposit', 8_000_000)
            ->assertJsonPath('order.totals.total_paid_for_order', 10_000_000)
            ->assertJsonPath('order.totals.remaining', 0)
            ->assertJsonPath('order.totals.deposit_remaining', 0);

        $this->actingAs($this->admin)
            ->getJson(route('orders.pos-payload', ['orderKey' => $cancelledOrder->id]))
            ->assertOk()
            ->assertJsonPath('order.totals.paid_after_deposit', 0)
            ->assertJsonPath('order.totals.remaining', 8_000_000)
            ->assertJsonPath('order.totals.deposit_remaining', 2_000_000);

        $this->actingAs($this->admin)
            ->getJson(route('orders.pos-payload', ['orderKey' => $clampedOrder->id]))
            ->assertOk()
            ->assertJsonPath('order.totals.paid_after_deposit', 0)
            ->assertJsonPath('order.totals.remaining', 0);
    }

    public function test_pos_only_user_can_process_order(): void
    {
        $product = $this->makeProduct(false, 10, 100000);
        $order = $this->makeOrder($product, 1, 200000);
        $user = $this->userWithPermissions(['pos.use']);

        $response = $this->actingAs($user)->postJson(route('orders.process', $order), [
            'from_pos' => true,
            'amount_paid' => 200000,
            'payment_method' => 'cash',
            'items' => [[
                'product_id' => $product->id,
                'quantity' => 1,
            ]],
        ]);

        $response->assertOk()->assertJsonPath('success', true);
        $this->assertSame('completed', $order->fresh()->status);
    }

    public function test_orders_edit_user_can_process_order_without_pos_permission(): void
    {
        $product = $this->makeProduct(false, 10, 100000);
        $order = $this->makeOrder($product, 1, 200000);
        $user = $this->userWithPermissions(['orders.edit']);

        $response = $this->actingAs($user)->postJson(route('orders.process', $order), [
            'from_pos' => true,
            'amount_paid' => 200000,
            'payment_method' => 'cash',
            'items' => [[
                'product_id' => $product->id,
                'quantity' => 1,
            ]],
        ]);

        $response->assertOk()->assertJsonPath('success', true);
    }

    public function test_pos_payload_missing_order_returns_404_json(): void
    {
        $this->actingAs($this->admin);
        $response = $this->getJson(route('orders.pos-payload', ['orderKey' => 'NONEXISTENT_CODE']));

        $response->assertStatus(404);
        $response->assertJsonPath('success', false);
        $response->assertJsonPath('error_code', 'ORDER_NOT_FOUND');
    }

    public function test_pos_payload_rejects_completed_or_invalid_orders(): void
    {
        $product = $this->makeProduct(false, 10, 100000);
        $order = $this->makeOrder($product, 3, 200000);

        $this->actingAs($this->admin);

        $order->update(['status' => 'completed']);
        $response = $this->getJson(route('orders.pos-payload', ['orderKey' => $order->id]));
        $response->assertStatus(422);
        $response->assertJsonPath('error_code', 'ORDER_ALREADY_COMPLETED');

        $order->update(['status' => 'cancelled']);
        $response = $this->getJson(route('orders.pos-payload', ['orderKey' => $order->id]));
        $response->assertStatus(422);
        $response->assertJsonPath('error_code', 'ORDER_CANCELLED');

        $order->update(['status' => 'ended']);
        $response = $this->getJson(route('orders.pos-payload', ['orderKey' => $order->id]));
        $response->assertStatus(422);
        $response->assertJsonPath('error_code', 'ORDER_ENDED');
    }

    public function test_pos_processing_creates_invoice_completes_order_correctly(): void
    {
        $product = $this->makeProduct(false, 10, 100000);
        $order = $this->makeOrder($product, 2, 200000); 

        $this->actingAs($this->admin);

        $payload = [
            'from_pos' => true,
            'amount_paid' => 400000,
            'payment_method' => 'cash',
            'delivery' => [
                'is_delivery' => true,
                'delivery_mode' => 'partner',
                'delivery_partner' => 'GHN',
                'receiver_name' => 'John Doe',
                'receiver_phone' => '0987654321',
                'receiver_address' => '123 Test St',
                'delivery_fee' => 30000,
                'cod_amount' => 0,
            ],
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => 2,
                ]
            ]
        ];

        $response = $this->postJson(route('orders.process', $order), $payload);

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);

        $order->refresh();
        $this->assertSame('completed', $order->status);
        $this->assertSame(0.0, (float) $order->amount_paid); // Cọc gốc giữ nguyên
        $this->assertTrue((bool)$order->is_delivery);
        $this->assertSame('GHN', $order->delivery_partner);
        $this->assertSame('John Doe', $order->receiver_name);

        $invoice = Invoice::where('order_id', $order->id)->first();
        $this->assertNotNull($invoice);
        $this->assertSame('Hoàn thành', $invoice->status);
        $this->assertSame(400000.0, (float) $invoice->customer_paid);
        $this->assertTrue((bool)$invoice->is_delivery);
        $this->assertSame('GHN', $invoice->delivery_partner);

        $product->refresh();
        $this->assertSame(8, (int) $product->stock_quantity);
    }

    public function test_pos_processing_rejects_quantity_mismatch_in_phase_1(): void
    {
        $product = $this->makeProduct(false, 10, 100000);
        $order = $this->makeOrder($product, 2, 200000);

        $this->actingAs($this->admin);

        $payload = [
            'from_pos' => true,
            'amount_paid' => 400000,
            'payment_method' => 'cash',
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => 3, 
                ]
            ]
        ];

        $response = $this->postJson(route('orders.process', $order), $payload);
        $response->assertStatus(422);
        $response->assertJsonPath('success', false);

        $payload['items'][0]['product_id'] = $product->id + 999;
        $response = $this->postJson(route('orders.process', $order), $payload);
        $response->assertStatus(422);
    }

    public function test_pos_processing_serial_override(): void
    {
        $product = $this->makeProduct(true, 0, 5000000);
        $serialA = SerialImei::create([
            'product_id'    => $product->id,
            'serial_number' => 'SN-A-' . uniqid(),
            'status'        => 'in_stock',
            'cost_price'    => 5000000,
            'original_cost' => 5000000,
        ]);
        $serialB = SerialImei::create([
            'product_id'    => $product->id,
            'serial_number' => 'SN-B-' . uniqid(),
            'status'        => 'in_stock',
            'cost_price'    => 5000000,
            'original_cost' => 5000000,
        ]);
        $product->update(['stock_quantity' => 2, 'inventory_total_cost' => 10000000]);

        $order = $this->makeOrder($product, 1, 8000000);
        $order->items()->first()->update(['serial_ids' => [$serialA->id]]);

        $this->actingAs($this->admin);

        $payload = [
            'from_pos' => true,
            'amount_paid' => 8000000,
            'payment_method' => 'cash',
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => 1,
                    'serial_ids' => [$serialB->id] 
                ]
            ]
        ];

        $response = $this->postJson(route('orders.process', $order), $payload);
        $response->assertStatus(200);

        $serialA->refresh();
        $serialB->refresh();

        $this->assertSame('in_stock', $serialA->status);
        $this->assertSame('sold', $serialB->status);
    }

    public function test_pos_processing_deposits_cash_flow_limit(): void
    {
        $product = $this->makeProduct(false, 10, 100000);
        $order = $this->makeOrder($product, 2, 200000); 
        $order->update(['amount_paid' => 150000]); 

        $this->actingAs($this->admin);

        $payload = [
            'from_pos' => true,
            'amount_paid' => 250000, 
            'payment_method' => 'cash',
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => 2,
                ]
            ]
        ];

        $response = $this->postJson(route('orders.process', $order), $payload);
        $response->assertStatus(200);

        $order->refresh();
        $this->assertSame(150000.0, (float) $order->amount_paid); // Cọc gốc giữ nguyên

        $invoice = Invoice::where('order_id', $order->id)->first();
        $this->assertSame(400000.0, (float) $invoice->customer_paid);

        $newCashFlows = CashFlow::where('reference_code', $invoice->code)->get();
        $this->assertCount(1, $newCashFlows);
        $this->assertSame(250000.0, (float) $newCashFlows->first()->amount);
    }

    public function test_cancelling_overpaid_invoice_restores_credit_and_total_spent(): void
    {
        $product = $this->makeProduct(false, 10, 100000);
        $order = $this->makeOrder($product, 1, 200000);

        $this->actingAs($this->admin)
            ->postJson(route('orders.process', $order), [
                'from_pos' => true,
                'amount_paid' => 250000,
                'payment_method' => 'cash',
                'items' => [[
                    'product_id' => $product->id,
                    'quantity' => 1,
                ]],
            ])
            ->assertOk();

        $invoice = Invoice::where('order_id', $order->id)->firstOrFail();
        $this->assertEquals(-50000, (float) $this->customer->fresh()->debt_amount);
        $this->assertEquals(200000, (float) $this->customer->fresh()->total_spent);

        $this->delete(route('invoices.destroy', $invoice))->assertRedirect();

        $this->assertEquals(0, (float) $this->customer->fresh()->debt_amount);
        $this->assertEquals(0, (float) $this->customer->fresh()->total_spent);
        $this->assertSame('Đã hủy', $invoice->fresh()->status);
        $this->assertSame(
            'cancelled',
            CashFlow::where('reference_type', 'Invoice')
                ->where('reference_code', $invoice->code)
                ->firstOrFail()
                ->status
        );
    }
}
