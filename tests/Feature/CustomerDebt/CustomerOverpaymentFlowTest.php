<?php

namespace Tests\Feature\CustomerDebt;

use App\Models\CashFlow;
use App\Models\Customer;
use App\Models\CustomerDebt;
use App\Models\CustomerPaymentAllocation;
use App\Models\Invoice;
use App\Models\User;
use App\Services\CustomerDebtService;
use App\Services\CustomerPaymentService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
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

    public function test_sale_helpers_reject_negative_input_while_signed_invoice_effect_preserves_credit(): void
    {
        $service = app(CustomerDebtService::class);

        try {
            $service->recordSale($this->customer->id, -300_000);
            $this->fail('recordSale must reject negative values.');
        } catch (InvalidArgumentException $exception) {
            $this->assertStringContainsString('signed invoice balance', $exception->getMessage());
        }

        try {
            $service->recordSaleReversal($this->customer->id, -300_000);
            $this->fail('recordSaleReversal must reject negative values.');
        } catch (InvalidArgumentException $exception) {
            $this->assertStringContainsString('signed invoice balance', $exception->getMessage());
        }

        $service->recordInvoiceBalanceEffect($this->customer->id, -300_000);
        $this->assertEquals(-300_000, (float) $this->customer->fresh()->debt_amount);
    }

    public function test_manual_allocations_are_validated_inside_service(): void
    {
        $invoice = $this->createReceivableInvoice(1_000_000);
        $secondInvoice = $this->createReceivableInvoice(500_000);
        $service = app(CustomerPaymentService::class);

        foreach ([-1, 0] as $invalidAmount) {
            try {
                $service->collect($this->customer, 1_000_000, 'manual', [
                    ['invoice_id' => $invoice->id, 'amount' => $invalidAmount],
                ]);
                $this->fail('Non-positive allocation must be rejected.');
            } catch (ValidationException $exception) {
                $this->assertArrayHasKey('allocations', $exception->errors());
            }
        }

        $invalidCases = [
            [
                ['invoice_id' => $invoice->id, 'amount' => 600_000],
                ['invoice_id' => $invoice->id, 'amount' => 400_000],
            ],
            [
                ['invoice_id' => $invoice->id, 'amount' => 1_000_001],
            ],
            [
                ['invoice_id' => $invoice->id, 'amount' => 700_000],
                ['invoice_id' => $secondInvoice->id, 'amount' => 400_000],
            ],
        ];

        foreach ($invalidCases as $allocations) {
            try {
                $service->collect($this->customer, 1_000_000, 'manual', $allocations);
                $this->fail('Invalid manual allocation must be rejected.');
            } catch (ValidationException $exception) {
                $this->assertArrayHasKey('allocations', $exception->errors());
            }
        }

        $secondInvoice->update(['status' => 'Đã hủy']);
        try {
            $service->collect($this->customer, 100_000, 'manual', [
                ['invoice_id' => $secondInvoice->id, 'amount' => 100_000],
            ]);
            $this->fail('Cancelled invoice allocation must be rejected.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('allocations', $exception->errors());
        }
    }

    public function test_dirty_ledger_reference_cannot_allocate_another_customers_invoice(): void
    {
        $owner = Customer::create([
            'code' => 'KH-OWNER-' . uniqid(),
            'name' => 'Invoice Owner',
            'debt_amount' => 500_000,
            'is_customer' => true,
        ]);
        $invoice = Invoice::create([
            'code' => 'HD-DIRTY-' . uniqid(),
            'customer_id' => $owner->id,
            'subtotal' => 500_000,
            'total' => 500_000,
            'customer_paid' => 0,
            'status' => 'completed',
        ]);
        CustomerDebt::create([
            'customer_id' => $this->customer->id,
            'ref_code' => $invoice->code,
            'amount' => 500_000,
            'debt_total' => 500_000,
            'type' => 'sale',
            'recorded_at' => now(),
        ]);

        $auto = app(CustomerPaymentService::class)->collect($this->customer, 100_000);
        $this->assertEquals(0, $auto['allocated_amount']);
        $this->assertEquals(100_000, $auto['unallocated_amount']);
        $this->assertEquals(0, (float) $invoice->fresh()->customer_paid);

        try {
            app(CustomerPaymentService::class)->collect($this->customer, 100_000, 'manual', [
                ['invoice_id' => $invoice->id, 'amount' => 100_000],
            ]);
            $this->fail('Cross-customer allocation must be rejected.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('allocations', $exception->errors());
        }
    }

    public function test_cancel_endpoint_reports_already_cancelled_without_second_reversal(): void
    {
        $invoice = $this->createReceivableInvoice(300_000);
        $result = app(CustomerPaymentService::class)->collect($this->customer, 300_000);
        $cashFlow = CashFlow::findOrFail($result['cash_flow_id']);

        $this->actingAs($this->admin)
            ->deleteJson(route('cash_flows.destroy', $cashFlow->id))
            ->assertOk()
            ->assertJsonPath('status', CustomerPaymentService::CANCELLED);

        $this->assertEquals(300_000, (float) $this->customer->fresh()->debt_amount);
        $this->assertEquals(0, (float) $invoice->fresh()->customer_paid);

        $this->deleteJson(route('cash_flows.destroy', $cashFlow->id))
            ->assertStatus(409)
            ->assertJsonPath('status', CustomerPaymentService::ALREADY_CANCELLED)
            ->assertJsonPath('message', 'Phiếu thu này đã bị hủy trước đó.');

        $this->assertEquals(300_000, (float) $this->customer->fresh()->debt_amount);
        $this->assertEquals(0, (float) $invoice->fresh()->customer_paid);
    }

    public function test_customer_credit_refund_effect_moves_negative_balance_toward_zero(): void
    {
        app(CustomerDebtService::class)->recordInvoiceBalanceEffect($this->customer->id, -300_000);

        foreach ([200_000, 100_000] as $refund) {
            CashFlow::create([
                'code' => 'PC-CREDIT-' . uniqid(),
                'type' => 'payment',
                'amount' => $refund,
                'time' => now(),
                'category' => 'Hoàn tiền khách dư',
                'target_type' => 'Khách hàng',
                'target_id' => $this->customer->id,
                'target_name' => $this->customer->name,
                'reference_type' => 'CustomerCreditRefund',
                'status' => 'active',
                'accounting_result' => false,
            ]);
            app(CustomerDebtService::class)->recordAdjustment(
                $this->customer->id,
                $refund,
                'Hoàn tiền khách dư',
                ['type' => 'credit_refund']
            );
        }

        $this->assertEquals(0, (float) $this->customer->fresh()->debt_amount);
        $this->assertEquals(0, (float) $this->customer->fresh()->total_spent);
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
