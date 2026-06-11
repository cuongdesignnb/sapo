<?php

namespace Tests\Feature\CustomerDebt;

use App\Models\Branch;
use App\Models\CashFlow;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class CustomerDebtTimelineNoDoubleCountTest extends TestCase
{
    use DatabaseTransactions;

    private User     $admin;
    private Customer $customer;
    private Branch   $branch;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::create([
            'name'     => 'Test Admin No Double Count',
            'email'    => 'test-admin-double-count-' . uniqid() . '@test.local',
            'password' => bcrypt('password'),
            'role_id'  => null,
        ]);

        $this->customer = Customer::create([
            'code'        => 'KH-' . uniqid(),
            'name'        => 'Test Customer No Double Count',
            'phone'       => '09' . rand(10000000, 99999999),
            'debt_amount' => 500000,
            'total_spent' => 0,
        ]);

        $this->branch = Branch::create([
            'name' => 'Branch Test No Double Count',
            'address' => '789 Test Rd',
        ]);
    }

    public function test_customer_debt_timeline_no_double_count_with_real_cash_flow(): void
    {
        $this->actingAs($this->admin);

        // 1. Create Order
        $order = Order::create([
            'code' => 'DH' . rand(100000, 999999),
            'customer_id' => $this->customer->id,
            'branch_id' => $this->branch->id,
            'total_price' => 1500000,
            'total_payment' => 1500000,
            'amount_paid' => 1000000,
            'status' => 'completed',
        ]);

        // 2. Create Invoice linked to order
        $invoice = Invoice::create([
            'code' => 'HD' . rand(100000, 999999),
            'customer_id' => $this->customer->id,
            'branch_id' => $this->branch->id,
            'order_id' => $order->id,
            'total' => 1500000,
            'subtotal' => 1500000,
            'discount' => 0,
            'customer_paid' => 1000000,
            'order_deposit_applied_amount' => 1000000,
            'status' => 'Hoàn thành',
            'payment_method' => 'cash',
        ]);

        // 3. Create CashFlow receipt for order deposit (this will not be filtered out)
        $cashFlow = CashFlow::create([
            'code' => 'PT' . rand(100000, 999999),
            'type' => 'receipt',
            'amount' => 1000000,
            'time' => now(),
            'category' => 'Thu đặt cọc đơn đặt hàng',
            'target_type' => 'Khách hàng',
            'target_id' => $this->customer->id,
            'reference_type' => 'Order',
            'reference_id' => $order->id,
            'reference_code' => $order->code,
            'status' => 'active',
        ]);

        // 4. Request timeline
        $response = $this->getJson("/customers/{$this->customer->id}/debt-history");
        $response->assertStatus(200);

        $data = $response->json();
        $entries = collect($data['entries']);

        // The order deposit is represented once as an applied-deposit document.
        $tthdEntry = $entries->first(fn($e) => str_starts_with($e['code'] ?? '', 'TTHD'));
        $cfEntry = $entries->first(fn($e) => str_starts_with($e['code'] ?? '', 'PT'));
        $depositEntry = $entries->first(fn($e) => str_starts_with($e['code'] ?? '', 'COC-'));

        $this->assertNull($tthdEntry);
        $this->assertNull($cfEntry);
        $this->assertNotNull($depositEntry);
        $this->assertFalse($depositEntry['is_reference_only']);
        $this->assertTrue($depositEntry['affects_debt_balance']);
        $this->assertEquals(-1000000.0, (float)$depositEntry['balance_effect']);
    }
}
