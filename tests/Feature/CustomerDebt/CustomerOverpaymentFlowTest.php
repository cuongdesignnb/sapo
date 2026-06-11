<?php

namespace Tests\Feature\CustomerDebt;

use App\Models\CashFlow;
use App\Models\Customer;
use App\Models\CustomerPaymentAllocation;
use App\Models\Invoice;
use App\Models\User;
use App\Services\CustomerDebtService;
use App\Services\CustomerPaymentService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class CustomerOverpaymentFlowTest extends TestCase
{
    use DatabaseTransactions;

    private User $admin;
    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::create([
            'name' => 'Customer Overpayment Admin',
            'email' => 'customer-overpayment-' . uniqid() . '@test.local',
            'password' => bcrypt('password'),
            'role_id' => null,
        ]);
        $this->customer = Customer::create([
            'code' => 'KH-OVER-' . uniqid(),
            'name' => 'Customer Overpayment',
            'debt_amount' => 0,
            'total_spent' => 0,
            'is_customer' => true,
        ]);
    }

    public function test_collecting_more_than_receivable_preserves_full_cash_and_negative_debt(): void
    {
        $invoice = $this->createReceivableInvoice(1_300_000);

        $result = $this->actingAs($this->admin)
            ->postJson("/customers/{$this->customer->id}/debt-payment", [
                'mode' => 'auto',
                'amount' => 1_500_000,
            ])
            ->assertOk()
            ->assertJsonPath('payment.payment_amount', 1_500_000)
            ->assertJsonPath('payment.allocated_amount', 1_300_000)
            ->assertJsonPath('payment.unallocated_amount', 200_000)
            ->assertJsonPath('payment.debt_before', 1_300_000)
            ->assertJsonPath('payment.debt_after', -200_000)
            ->json('payment');

        $cashFlow = CashFlow::findOrFail($result['cash_flow_id']);
        $this->assertEquals(1_500_000, (float) $cashFlow->amount);
        $this->assertEquals(-200_000, (float) $this->customer->fresh()->debt_amount);
        $this->assertEquals(1_300_000, (float) $invoice->fresh()->customer_paid);
        $this->assertDatabaseHas('customer_payment_allocations', [
            'cash_flow_id' => $cashFlow->id,
            'invoice_id' => $invoice->id,
            'amount' => 1_300_000,
        ]);

        $entries = collect(
            $this->actingAs($this->admin)
                ->getJson("/customers/{$this->customer->id}/debt-history")
                ->assertOk()
                ->assertJsonPath('summary.current_debt', -200_000)
                ->json('entries')
        );
        $payment = $entries->firstWhere('code', $cashFlow->code);
        $this->assertNotNull($payment);
        $this->assertEquals(-1_500_000, $payment['customer_display_balance_effect']);
        $this->assertEquals(-200_000, $entries->first()['customer_display_running_balance']);
    }

    public function test_cancelling_debt_payment_reverses_allocations_and_customer_credit(): void
    {
        $invoice = $this->createReceivableInvoice(1_300_000);
        $result = app(CustomerPaymentService::class)->collect($this->customer, 1_500_000);
        $cashFlow = CashFlow::findOrFail($result['cash_flow_id']);

        app(CustomerPaymentService::class)->cancel($cashFlow);

        $this->assertEquals(1_300_000, (float) $this->customer->fresh()->debt_amount);
        $this->assertEquals(0, (float) $invoice->fresh()->customer_paid);
        $this->assertTrue($cashFlow->fresh()->trashed());
        $this->assertSame('cancelled', $cashFlow->fresh()->status);
        $this->assertSame(1, CustomerPaymentAllocation::where('cash_flow_id', $cashFlow->id)->count());
    }

    public function test_existing_customer_credit_offsets_future_invoice_without_becoming_deposit(): void
    {
        $oldInvoice = $this->createReceivableInvoice(1_300_000);
        app(CustomerPaymentService::class)->collect($this->customer, 1_500_000);
        $this->assertEquals(-200_000, (float) $this->customer->fresh()->debt_amount);

        $newInvoice = Invoice::create([
            'code' => 'HD-FUTURE-' . uniqid(),
            'customer_id' => $this->customer->id,
            'subtotal' => 1_500_000,
            'total' => 1_500_000,
            'customer_paid' => 0,
            'order_deposit_applied_amount' => 0,
            'status' => 'completed',
        ]);
        app(CustomerDebtService::class)->recordAdjustment(
            $this->customer->id,
            1_500_000,
            'Future invoice',
            ['ref_code' => $newInvoice->code, 'type' => 'sale']
        );

        $this->assertEquals(1_300_000, (float) $this->customer->fresh()->debt_amount);
        $this->assertEquals(0, (float) $oldInvoice->fresh()->order_deposit_applied_amount);
        $this->assertEquals(0, (float) $newInvoice->fresh()->order_deposit_applied_amount);

        $entries = collect(
            $this->actingAs($this->admin)
                ->getJson("/customers/{$this->customer->id}/debt-history")
                ->assertOk()
                ->assertJsonPath('summary.current_debt', 1_300_000)
                ->json('entries')
        );
        $this->assertEquals(1_300_000, $entries->first()['customer_display_running_balance']);
        $this->assertNotNull($entries->firstWhere('code', $newInvoice->code));
    }

    public function test_cancelling_invoice_cashflow_reverses_payment_without_changing_sales(): void
    {
        $invoice = Invoice::create([
            'code' => 'HD-CASHFLOW-CANCEL-' . uniqid(),
            'customer_id' => $this->customer->id,
            'subtotal' => 1_500_000,
            'total' => 1_500_000,
            'customer_paid' => 1_800_000,
            'order_deposit_applied_amount' => 0,
            'status' => 'completed',
        ]);
        app(CustomerDebtService::class)->recordAdjustment(
            $this->customer->id,
            -300_000,
            'Overpaid invoice',
            ['ref_code' => $invoice->code, 'type' => 'sale']
        );
        $this->customer->increment('total_spent', 1_500_000);
        $cashFlow = CashFlow::create([
            'code' => 'PT-INVOICE-' . uniqid(),
            'type' => 'receipt',
            'amount' => 1_800_000,
            'time' => now(),
            'category' => 'Ban hang',
            'target_type' => 'Khách hàng',
            'target_id' => $this->customer->id,
            'reference_type' => 'Invoice',
            'reference_code' => $invoice->code,
            'status' => 'active',
        ]);

        app(CustomerPaymentService::class)->cancel($cashFlow);

        $this->assertEquals(1_500_000, (float) $this->customer->fresh()->debt_amount);
        $this->assertEquals(0, (float) $invoice->fresh()->customer_paid);
        $this->assertEquals(1_500_000, (float) $this->customer->fresh()->total_spent);
        $this->assertSame('cancelled', $cashFlow->fresh()->status);
    }

    private function createReceivableInvoice(float $total): Invoice
    {
        $invoice = Invoice::create([
            'code' => 'HD-RECEIVABLE-' . uniqid(),
            'customer_id' => $this->customer->id,
            'subtotal' => $total,
            'total' => $total,
            'customer_paid' => 0,
            'order_deposit_applied_amount' => 0,
            'status' => 'completed',
        ]);
        app(CustomerDebtService::class)->recordAdjustment(
            $this->customer->id,
            $total,
            'Receivable invoice',
            ['ref_code' => $invoice->code, 'type' => 'sale']
        );
        $this->customer->increment('total_spent', $total);

        return $invoice;
    }
}
