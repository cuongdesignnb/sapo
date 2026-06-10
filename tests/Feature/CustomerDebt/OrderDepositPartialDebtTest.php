<?php

namespace Tests\Feature\CustomerDebt;

use App\Models\Category;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\CashFlow;
use App\Models\User;
use App\Models\Branch;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class OrderDepositPartialDebtTest extends TestCase
{
    use DatabaseTransactions;

    private User     $admin;
    private Customer $customer;
    private Branch   $branch;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::create([
            'name'     => 'Test Admin Partial',
            'email'    => 'test-admin-partial-' . uniqid() . '@test.local',
            'password' => bcrypt('password'),
            'role_id'  => null,
        ]);

        $this->customer = Customer::create([
            'code'        => 'KH-' . uniqid(),
            'name'        => 'Test Customer ' . uniqid(),
            'phone'       => '091' . rand(1000000, 9999999),
            'debt_amount' => 0,
            'total_spent' => 0,
        ]);

        $this->branch = Branch::create([
            'name' => 'Branch Test ' . uniqid(),
            'address' => '123 Test Rd',
        ]);
    }

    private function makeProduct(int $stock = 100, float $price = 1000000): Product
    {
        $category = Category::firstOrCreate(['name' => 'Test Cat Partial']);
        return Product::create([
            'sku'                  => 'PROD-' . uniqid(),
            'name'                 => 'Test Product Partial',
            'cost_price'           => $price / 2,
            'retail_price'         => $price,
            'stock_quantity'       => $stock,
            'inventory_total_cost' => $stock * ($price / 2),
            'is_active'            => true,
            'has_serial'           => false,
            'category_id'          => $category->id,
        ]);
    }

    /**
     * Case: OrderDepositPartialDebtTest
     * - Order total 5.000.000.
     * - Cọc 1.000.000.
     * - Process lần 1 invoice total 2.000.000, khách trả thêm 0.
     * - Kỳ vọng:
     *   - invoice 1 `order_deposit_applied_amount = 1.000.000`
     *   - invoice 1 `customer_paid = 1.000.000`
     *   - debt phát sinh = 1.000.000
     *   - `customers.debt_amount = 1.000.000`
     *   - có 1 row `customer_debts` type `sale`, amount `1.000.000`
     *   - không tạo cashflow cọc lần 2
     *
     * - Process lần 2 invoice total 3.000.000, khách trả thêm 3.000.000.
     * - Kỳ vọng:
     *   - invoice 2 `order_deposit_applied_amount = 0`
     *   - invoice 2 `customer_paid = 3.000.000`
     *   - debt phát sinh = 0
     *   - `customers.debt_amount` vẫn `1.000.000`
     *   - cashflow chỉ ghi 3.000.000 tiền trả thêm
     */
    public function test_order_deposit_partial_fulfillment_debt_flow(): void
    {
        $product = $this->makeProduct(10, 1000000); // retail_price = 1M
        $this->actingAs($this->admin);

        // 1. Tạo order total 5M, cọc 1M
        $orderPayload = [
            'customer_id' => $this->customer->id,
            'branch_id' => $this->branch->id,
            'status' => 'confirmed',
            'total_price' => 5000000,
            'discount' => 0,
            'other_fees' => 0,
            'total_payment' => 5000000,
            'amount_paid' => 1000000, // Cọc 1M
            'payment_method' => 'cash',
            'items' => [
                [
                    'product_id' => $product->id,
                    'qty' => 5,
                    'price' => 1000000,
                    'discount' => 0,
                ]
            ]
        ];

        $response = $this->post(route('orders.store'), $orderPayload);
        $response->assertStatus(302);

        $order = Order::where('customer_id', $this->customer->id)->latest()->first();
        $this->assertNotNull($order);
        $this->assertEquals(1000000, $order->amount_paid);

        $this->customer->refresh();
        $this->assertEquals(0, $this->customer->debt_amount); // Cọc chưa ghi công nợ

        // Check cashflow cọc
        $cashflowDeposit = CashFlow::where('reference_type', 'Order')
            ->where('reference_code', $order->code)
            ->first();
        $this->assertNotNull($cashflowDeposit);
        $this->assertEquals(1000000, $cashflowDeposit->amount);

        // 2. Process lần 1: invoice total 2.000.000, khách trả thêm 0
        $orderItem = $order->items->first();
        $process1Payload = [
            'amount_paid' => 0,
            'payment_method' => 'cash',
            'keep_remaining' => true,
            'items' => [
                [
                    'order_item_id' => $orderItem->id,
                    'product_id' => $product->id,
                    'quantity' => 2, // 2M
                ]
            ]
        ];

        $process1Response = $this->postJson(route('orders.process', $order->id), $process1Payload);
        $process1Response->assertJsonPath('success', true);

        $invoice1 = Invoice::where('order_id', $order->id)->orderBy('id', 'asc')->first();
        $this->assertNotNull($invoice1);
        $this->assertEquals(2000000, $invoice1->total);
        $this->assertEquals(1000000, $invoice1->order_deposit_applied_amount);
        $this->assertEquals(1000000, $invoice1->customer_paid); // applied cọc (1M) + paid (0) = 1M

        $this->customer->refresh();
        // Debt should increase by 2.0M - 1.0M = 1.0M
        $this->assertEquals(1000000, $this->customer->debt_amount);

        // Có 1 row customer_debts type sale, amount 1.000.000
        $debts1 = DB::table('customer_debts')->where('customer_id', $this->customer->id)->get();
        $this->assertCount(1, $debts1);
        $this->assertEquals('sale', $debts1[0]->type);
        $this->assertEquals(1000000, (float) $debts1[0]->amount);

        // Không tạo cashflow cọc lần 2 (vì newPayment = 0)
        // Cashflow receipt cho HD 1: no cashflow generated because amount_paid = 0
        $cashflowInvoice1 = CashFlow::where('reference_type', 'Invoice')
            ->where('reference_code', $invoice1->code)
            ->first();
        $this->assertNull($cashflowInvoice1);

        // 3. Process lần 2: invoice total 3.000.000, khách trả thêm 3.000.000
        $orderItem->refresh();
        $process2Payload = [
            'amount_paid' => 3000000,
            'payment_method' => 'cash',
            'items' => [
                [
                    'order_item_id' => $orderItem->id,
                    'product_id' => $product->id,
                    'quantity' => 3, // 3M
                ]
            ]
        ];

        $process2Response = $this->postJson(route('orders.process', $order->id), $process2Payload);
        $process2Response->assertJsonPath('success', true);

        $invoice2 = Invoice::where('order_id', $order->id)->orderBy('id', 'desc')->first();
        $this->assertNotNull($invoice2);
        $this->assertNotEquals($invoice1->id, $invoice2->id);
        $this->assertEquals(3000000, $invoice2->total);
        $this->assertEquals(0, $invoice2->order_deposit_applied_amount);
        $this->assertEquals(3000000, $invoice2->customer_paid);

        $this->customer->refresh();
        // Debt should still be 1.0M (first invoice debt) since second invoice is fully paid
        $this->assertEquals(1000000, $this->customer->debt_amount);

        // Check ledger rows: should still be only 1 row from the first invoice, because second invoice has no debt
        $debts2 = DB::table('customer_debts')->where('customer_id', $this->customer->id)->get();
        $this->assertCount(1, $debts2);

        // Cashflow ghi nhận 3M tiền trả thêm
        $cashflowInvoice2 = CashFlow::where('reference_type', 'Invoice')
            ->where('reference_code', $invoice2->code)
            ->first();
        $this->assertNotNull($cashflowInvoice2);
        $this->assertEquals(3000000, $cashflowInvoice2->amount);
        $this->assertEquals('receipt', $cashflowInvoice2->type);
    }

    /**
     * Case: OrderFullPaidNoDebtTest
     * - Invoice total 2.000.000.
     * - Customer paid đủ 2.000.000.
     * - Không tạo `customer_debts`.
     * - `customers.debt_amount` không tăng.
     */
    public function test_order_fully_paid_creates_no_debt_ledger(): void
    {
        $product = $this->makeProduct(10, 1000000); // retail_price = 1M
        $this->actingAs($this->admin);

        // Tạo order total 2M, cọc 0
        $orderPayload = [
            'customer_id' => $this->customer->id,
            'branch_id' => $this->branch->id,
            'status' => 'confirmed',
            'total_price' => 2000000,
            'discount' => 0,
            'other_fees' => 0,
            'total_payment' => 2000000,
            'amount_paid' => 0,
            'payment_method' => 'cash',
            'items' => [
                [
                    'product_id' => $product->id,
                    'qty' => 2,
                    'price' => 1000000,
                    'discount' => 0,
                ]
            ]
        ];

        $response = $this->post(route('orders.store'), $orderPayload);
        $response->assertStatus(302);

        $order = Order::where('customer_id', $this->customer->id)->latest()->first();
        $orderItem = $order->items->first();

        // Process fully paid (paid 2M)
        $processPayload = [
            'amount_paid' => 2000000,
            'payment_method' => 'cash',
            'items' => [
                [
                    'order_item_id' => $orderItem->id,
                    'product_id' => $product->id,
                    'quantity' => 2,
                ]
            ]
        ];

        $processResponse = $this->postJson(route('orders.process', $order->id), $processPayload);
        $processResponse->assertJsonPath('success', true);

        $this->customer->refresh();
        $this->assertEquals(0, $this->customer->debt_amount);

        $debts = DB::table('customer_debts')->where('customer_id', $this->customer->id)->get();
        $this->assertCount(0, $debts);
    }

    /**
     * Case: OrderUnpaidCreatesDebtLedgerTest
     * - Invoice total 2.000.000.
     * - Customer paid 500.000.
     * - Debt = 1.500.000.
     * - Có `customer_debts` type sale amount 1.500.000.
     * - `customers.debt_amount` tăng 1.500.000.
     */
    public function test_order_unpaid_creates_debt_ledger(): void
    {
        $product = $this->makeProduct(10, 1000000); // retail_price = 1M
        $this->actingAs($this->admin);

        // Tạo order total 2M, cọc 0
        $orderPayload = [
            'customer_id' => $this->customer->id,
            'branch_id' => $this->branch->id,
            'status' => 'confirmed',
            'total_price' => 2000000,
            'discount' => 0,
            'other_fees' => 0,
            'total_payment' => 2000000,
            'amount_paid' => 0,
            'payment_method' => 'cash',
            'items' => [
                [
                    'product_id' => $product->id,
                    'qty' => 2,
                    'price' => 1000000,
                    'discount' => 0,
                ]
            ]
        ];

        $response = $this->post(route('orders.store'), $orderPayload);
        $response->assertStatus(302);

        $order = Order::where('customer_id', $this->customer->id)->latest()->first();
        $orderItem = $order->items->first();

        // Process paid 500.000
        $processPayload = [
            'amount_paid' => 500000,
            'payment_method' => 'cash',
            'items' => [
                [
                    'order_item_id' => $orderItem->id,
                    'product_id' => $product->id,
                    'quantity' => 2,
                ]
            ]
        ];

        $processResponse = $this->postJson(route('orders.process', $order->id), $processPayload);
        $processResponse->assertJsonPath('success', true);

        $this->customer->refresh();
        $this->assertEquals(1500000, $this->customer->debt_amount);

        $debts = DB::table('customer_debts')->where('customer_id', $this->customer->id)->get();
        $this->assertCount(1, $debts);
        $this->assertEquals('sale', $debts[0]->type);
        $this->assertEquals(1500000, (float) $debts[0]->amount);
    }
}
