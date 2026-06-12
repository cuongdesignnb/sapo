<?php

namespace Tests\Feature\Print;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\Product;
use App\Models\Role;
use App\Models\User;
use App\Services\PrintableOrderService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class SimpleOrderPrintTest extends TestCase
{
    use DatabaseTransactions;

    private User $user;
    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();

        $role = Role::create([
            'name' => 'print-test-' . uniqid(),
            'display_name' => 'Print Test',
            'permissions' => ['orders.view', 'invoices.print'],
        ]);

        $this->user = User::create([
            'name' => 'Print User',
            'email' => 'print-' . uniqid() . '@test.local',
            'password' => bcrypt('password'),
            'role_id' => $role->id,
        ]);

        $this->customer = Customer::create([
            'code' => 'KH-PRINT-' . uniqid(),
            'name' => 'Chị Ngọc SS Hạ Long',
            'phone' => '0815973005',
            'email' => null,
            'address' => '62 Phan Đăng Lưu, Hạ Long',
            'debt_amount' => 0,
            'total_spent' => 0,
        ]);
    }

    public function test_order_mapper_and_a4_route_render_required_fields(): void
    {
        $firstProduct = $this->product('BBSz', 'Bombay Sapphire Gin');
        $firstProduct->units()->create([
            'unit_name' => 'Chai',
            'conversion_rate' => 1,
            'is_base_unit' => true,
        ]);
        $secondProduct = $this->product(
            'JAGERz',
            'Jagermeister 0,7L Z - tên sản phẩm rất dài để kiểm tra khả năng tự xuống dòng trong bảng in'
        );

        $order = Order::create([
            'code' => 'SON00002-' . uniqid(),
            'customer_id' => $this->customer->id,
            'status' => 'confirmed',
            'total_price' => 4320000,
            'discount' => 0,
            'other_fees' => 0,
            'total_payment' => 4320000,
            'amount_paid' => 1000000,
            'note' => null,
            'created_at' => Carbon::parse('2026-06-11 09:30:00'),
        ]);

        $order->items()->createMany([
            [
                'product_id' => $firstProduct->id,
                'qty' => 6,
                'price' => 400000,
                'discount' => 0,
                'subtotal' => 2400000,
            ],
            [
                'product_id' => $secondProduct->id,
                'qty' => 6,
                'price' => 320000,
                'discount' => 0,
                'subtotal' => 1920000,
            ],
        ]);

        $printable = app(PrintableOrderService::class)->forOrder($order);

        $this->assertSame('Đơn đặt hàng', $printable['title']);
        $this->assertSame('Mã đơn hàng', $printable['code_label']);
        $this->assertSame('11-06-2026', $printable['created_at']);
        $this->assertSame('Chai', $printable['items'][0]['unit']);
        $this->assertSame('', $printable['items'][1]['unit']);
        $this->assertSame(12.0, $printable['totals']['total_quantity']);
        $this->assertSame(1000000.0, $printable['totals']['deposit']);
        $this->assertSame(3320000.0, $printable['totals']['remaining']);

        $response = $this->actingAs($this->user)
            ->get(route('orders.print', $order) . '?preview=1');

        $response->assertOk()
            ->assertSee('Đơn đặt hàng')
            ->assertSee('SON00002')
            ->assertSee('11-06-2026')
            ->assertSee('Bombay Sapphire Gin')
            ->assertSee('Khách đã đặt cọc')
            ->assertSee('3.320.000đ')
            ->assertSee('Người lập đơn')
            ->assertDontSee('undefined')
            ->assertDontSee('N/A');
    }

    public function test_invoice_mapper_uses_order_line_discount_for_partial_invoice(): void
    {
        $product = $this->product('PARTIAL', 'Sản phẩm giao một phần');
        $order = Order::create([
            'code' => 'ORDER-PARTIAL-' . uniqid(),
            'customer_id' => $this->customer->id,
            'status' => 'confirmed',
            'total_price' => 2340000,
            'discount' => 0,
            'other_fees' => 0,
            'total_payment' => 2340000,
            'amount_paid' => 200000,
        ]);
        $orderItem = $order->items()->create([
            'product_id' => $product->id,
            'qty' => 6,
            'price' => 400000,
            'discount' => 60000,
            'subtotal' => 2340000,
        ]);

        $invoice = Invoice::create([
            'code' => 'HD-PARTIAL-' . uniqid(),
            'order_id' => $order->id,
            'customer_id' => $this->customer->id,
            'subtotal' => 1200000,
            'discount' => 30000,
            'total' => 1170000,
            'customer_paid' => 500000,
            'order_deposit_applied_amount' => 200000,
            'status' => 'Hoàn thành',
            'created_at' => Carbon::parse('2026-06-12 10:00:00'),
        ]);
        $invoice->items()->create([
            'product_id' => $product->id,
            'order_item_id' => $orderItem->id,
            'quantity' => 3,
            'price' => 400000,
            'discount' => 0,
            'subtotal' => 0,
        ]);

        $printable = app(PrintableOrderService::class)->forInvoice($invoice);

        $this->assertSame(30000.0, $printable['items'][0]['discount']);
        $this->assertSame('Mã hóa đơn', $printable['code_label']);
        $this->assertSame(1170000.0, $printable['items'][0]['total']);
        $this->assertSame(200000.0, $printable['totals']['deposit']);
        $this->assertSame(300000.0, $printable['totals']['paid']);
        $this->assertSame(670000.0, $printable['totals']['remaining']);

        $response = $this->actingAs($this->user)
            ->get(route('invoices.print-a4', $invoice) . '?preview=1');

        $response->assertOk()
            ->assertSee('Hóa đơn bán hàng')
            ->assertSee('30.000đ')
            ->assertSee('Khách đã trả')
            ->assertSee('670.000đ')
            ->assertSee('Người bán');
    }

    public function test_invoice_without_source_order_cannot_use_a4_route(): void
    {
        $invoice = Invoice::create([
            'code' => 'HD-RETAIL-' . uniqid(),
            'subtotal' => 100000,
            'discount' => 0,
            'total' => 100000,
            'customer_paid' => 100000,
            'status' => 'Hoàn thành',
        ]);

        $this->actingAs($this->user)
            ->get(route('invoices.print-a4', $invoice))
            ->assertNotFound();
    }

    public function test_a4_invoice_route_requires_invoice_print_permission(): void
    {
        $role = Role::create([
            'name' => 'no-print-' . uniqid(),
            'display_name' => 'No Print',
            'permissions' => ['invoices.view'],
        ]);
        $unauthorizedUser = User::create([
            'name' => 'No Print User',
            'email' => 'no-print-' . uniqid() . '@test.local',
            'password' => bcrypt('password'),
            'role_id' => $role->id,
        ]);
        $order = Order::create([
            'code' => 'ORDER-AUTH-' . uniqid(),
            'status' => 'confirmed',
            'total_price' => 0,
            'total_payment' => 0,
        ]);
        $invoice = Invoice::create([
            'code' => 'HD-AUTH-' . uniqid(),
            'order_id' => $order->id,
            'subtotal' => 0,
            'discount' => 0,
            'total' => 0,
            'customer_paid' => 0,
            'status' => 'Hoàn thành',
        ]);

        $this->actingAs($unauthorizedUser)
            ->get(route('invoices.print-a4', $invoice))
            ->assertRedirect('/');
    }

    private function product(string $sku, string $name): Product
    {
        return Product::create([
            'sku' => $sku . '-' . uniqid(),
            'name' => $name,
            'cost_price' => 100000,
            'retail_price' => 200000,
            'stock_quantity' => 20,
            'inventory_total_cost' => 2000000,
            'is_active' => true,
            'has_serial' => false,
        ]);
    }
}
