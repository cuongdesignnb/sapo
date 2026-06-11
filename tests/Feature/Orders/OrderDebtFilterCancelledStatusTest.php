<?php

namespace Tests\Feature\Orders;

use App\Models\Branch;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class OrderDebtFilterCancelledStatusTest extends TestCase
{
    use DatabaseTransactions;

    public function test_debt_filter_ignores_cancelled_invoices_and_clamps_negative_paid_amount(): void
    {
        $admin = User::create([
            'name' => 'Order Debt Filter Admin',
            'email' => 'order-debt-filter-' . uniqid() . '@test.local',
            'password' => bcrypt('password'),
            'role_id' => null,
        ]);
        $customer = Customer::create([
            'code' => 'KH-ORDER-DEBT-' . uniqid(),
            'name' => 'Order Debt Filter Customer',
            'debt_amount' => 0,
        ]);
        $branch = Branch::create([
            'name' => 'Order Debt Filter Branch ' . uniqid(),
            'address' => 'Test',
        ]);

        $cancelledOrder = $this->createOrder($customer->id, $branch->id, 10_000_000, 2_000_000);
        Invoice::create([
            'code' => 'HD-CANCELLED-' . uniqid(),
            'customer_id' => $customer->id,
            'branch_id' => $branch->id,
            'order_id' => $cancelledOrder->id,
            'subtotal' => 10_000_000,
            'total' => 10_000_000,
            'discount' => 0,
            'customer_paid' => 8_000_000,
            'order_deposit_applied_amount' => 2_000_000,
            'status' => 'Đã huỷ',
        ]);

        $paidOrder = $this->createOrder($customer->id, $branch->id, 10_000_000, 2_000_000);
        Invoice::create([
            'code' => 'HD-ACTIVE-' . uniqid(),
            'customer_id' => $customer->id,
            'branch_id' => $branch->id,
            'order_id' => $paidOrder->id,
            'subtotal' => 10_000_000,
            'total' => 10_000_000,
            'discount' => 0,
            'customer_paid' => 10_000_000,
            'order_deposit_applied_amount' => 2_000_000,
            'status' => 'completed',
        ]);

        $clampedOrder = $this->createOrder($customer->id, $branch->id, 2_000_000, 2_000_000);
        Invoice::create([
            'code' => 'HD-CLAMP-' . uniqid(),
            'customer_id' => $customer->id,
            'branch_id' => $branch->id,
            'order_id' => $clampedOrder->id,
            'subtotal' => 2_000_000,
            'total' => 2_000_000,
            'discount' => 0,
            'customer_paid' => 1_000_000,
            'order_deposit_applied_amount' => 2_000_000,
            'status' => 'completed',
        ]);

        $debtResponse = $this->actingAs($admin)->get('/orders?has_debt=1');
        $debtResponse->assertOk();
        $debtIds = collect($this->props($debtResponse)['orders']['data'] ?? [])->pluck('id');
        $this->assertContains($cancelledOrder->id, $debtIds);
        $this->assertNotContains($paidOrder->id, $debtIds);
        $this->assertNotContains($clampedOrder->id, $debtIds);

        $paidResponse = $this->actingAs($admin)->get('/orders?has_debt=0');
        $paidResponse->assertOk();
        $paidIds = collect($this->props($paidResponse)['orders']['data'] ?? [])->pluck('id');
        $this->assertNotContains($cancelledOrder->id, $paidIds);
        $this->assertContains($paidOrder->id, $paidIds);
        $this->assertContains($clampedOrder->id, $paidIds);
    }

    private function createOrder(int $customerId, int $branchId, float $total, float $paid): Order
    {
        return Order::create([
            'code' => 'DH-FILTER-' . uniqid(),
            'customer_id' => $customerId,
            'branch_id' => $branchId,
            'total_price' => $total,
            'total_payment' => $total,
            'amount_paid' => $paid,
            'status' => 'confirmed',
        ]);
    }

    private function props($response): array
    {
        $page = $response->original->getData()['page'] ?? null;

        return $page['props'] ?? $response->json();
    }
}
