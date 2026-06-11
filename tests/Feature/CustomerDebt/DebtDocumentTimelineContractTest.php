<?php

namespace Tests\Feature\CustomerDebt;

use App\Models\Branch;
use App\Models\CashFlow;
use App\Models\Customer;
use App\Models\CustomerDebt;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\Purchase;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class DebtDocumentTimelineContractTest extends TestCase
{
    use DatabaseTransactions;

    private User $admin;
    private Customer $partner;
    private Branch $branch;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::create([
            'name' => 'Debt Document Timeline Admin',
            'email' => 'debt-document-' . uniqid() . '@test.local',
            'password' => bcrypt('password'),
            'role_id' => null,
        ]);
        $this->partner = Customer::create([
            'code' => 'KH-NCC-' . uniqid(),
            'name' => 'Debt Document Partner',
            'debt_amount' => 0,
            'supplier_debt_amount' => 0,
            'is_customer' => true,
            'is_supplier' => true,
        ]);
        $this->branch = Branch::create([
            'name' => 'Debt Document Branch ' . uniqid(),
            'address' => 'Test',
        ]);
    }

    public function test_invoice_uses_documents_and_splits_deposit_from_real_payment(): void
    {
        $time = Carbon::parse('2026-06-01 10:00:00');
        $order = Order::create([
            'code' => 'DH-DOC-' . uniqid(),
            'customer_id' => $this->partner->id,
            'branch_id' => $this->branch->id,
            'total_price' => 15_000_000,
            'total_payment' => 15_000_000,
            'amount_paid' => 5_000_000,
            'status' => 'completed',
        ]);
        $invoice = Invoice::create([
            'code' => 'HD-DOC-' . uniqid(),
            'customer_id' => $this->partner->id,
            'branch_id' => $this->branch->id,
            'order_id' => $order->id,
            'subtotal' => 15_000_000,
            'total' => 15_000_000,
            'discount' => 0,
            'customer_paid' => 10_000_000,
            'order_deposit_applied_amount' => 5_000_000,
            'status' => 'completed',
            'transaction_date' => $time,
            'created_at' => $time,
        ]);
        $payment = CashFlow::create([
            'code' => 'PT-DOC-' . uniqid(),
            'type' => 'receipt',
            'amount' => 5_000_000,
            'time' => $time,
            'target_type' => 'Khách hàng',
            'target_id' => $this->partner->id,
            'reference_type' => 'Invoice',
            'reference_code' => $invoice->code,
            'status' => 'completed',
            'created_at' => $time,
        ]);
        $ledger = CustomerDebt::create([
            'customer_id' => $this->partner->id,
            'order_id' => $order->id,
            'ref_code' => $invoice->code,
            'type' => 'sale',
            'amount' => 5_000_000,
            'debt_total' => 5_000_000,
            'recorded_at' => $time,
        ]);
        $this->partner->update(['debt_amount' => 5_000_000]);

        $response = $this->actingAs($this->admin)
            ->getJson("/customers/{$this->partner->id}/debt-history");

        $response->assertOk()
            ->assertJsonPath('summary.current_debt', 5_000_000)
            ->assertJsonPath('reconcile.status', 'ok');

        $entries = collect($response->json('entries'));
        $this->assertCount(3, $entries);
        $this->assertNotNull($entries->firstWhere('code', $invoice->code));
        $this->assertNotNull($entries->firstWhere('code', $payment->code));
        $this->assertNotNull($entries->firstWhere('code', 'COC-' . $invoice->code));
        $this->assertFalse($entries->contains(fn ($entry) => str_contains((string) $entry['code'], 'OPENING-BALANCE')));

        $ledgerEntries = collect($response->json('ledger_entries'));
        $this->assertNotNull($ledgerEntries->firstWhere('reference_id', $ledger->id));
        $this->assertFalse($entries->contains(fn ($entry) => ($entry['reference_type'] ?? null) === 'CustomerDebt'));
    }

    public function test_same_timestamp_calculates_invoice_before_payment_but_displays_payment_first(): void
    {
        $time = Carbon::parse('2026-06-01 10:00:00');
        $invoice = Invoice::create([
            'code' => 'HD-SEQ-' . uniqid(),
            'customer_id' => $this->partner->id,
            'branch_id' => $this->branch->id,
            'subtotal' => 15_000_000,
            'total' => 15_000_000,
            'discount' => 0,
            'customer_paid' => 10_000_000,
            'order_deposit_applied_amount' => 0,
            'status' => 'completed',
            'transaction_date' => $time,
            'created_at' => $time,
        ]);
        $payment = CashFlow::create([
            'code' => 'PT-SEQ-' . uniqid(),
            'type' => 'receipt',
            'amount' => 10_000_000,
            'time' => $time,
            'target_type' => 'Khách hàng',
            'target_id' => $this->partner->id,
            'reference_type' => 'Invoice',
            'reference_code' => $invoice->code,
            'status' => 'active',
            'created_at' => $time,
        ]);
        $this->partner->update(['debt_amount' => 5_000_000]);

        $entries = $this->actingAs($this->admin)
            ->getJson("/customers/{$this->partner->id}/debt-history")
            ->assertOk()
            ->json('entries');

        $this->assertSame($payment->code, $entries[0]['code']);
        $this->assertSame($invoice->code, $entries[1]['code']);
        $this->assertEquals(5_000_000, $entries[0]['customer_display_running_balance']);
        $this->assertEquals(15_000_000, $entries[1]['customer_display_running_balance']);
    }

    public function test_cashflow_over_allocation_is_capped_and_warned(): void
    {
        $invoice = Invoice::create([
            'code' => 'HD-OVER-' . uniqid(),
            'customer_id' => $this->partner->id,
            'branch_id' => $this->branch->id,
            'subtotal' => 15_000_000,
            'total' => 15_000_000,
            'discount' => 0,
            'customer_paid' => 10_000_000,
            'order_deposit_applied_amount' => 0,
            'status' => 'completed',
        ]);
        CashFlow::create([
            'code' => 'PT-OVER-' . uniqid(),
            'type' => 'receipt',
            'amount' => 12_000_000,
            'target_type' => 'Khách hàng',
            'target_id' => $this->partner->id,
            'reference_type' => 'Invoice',
            'reference_code' => $invoice->code,
            'status' => 'active',
        ]);
        $this->partner->update(['debt_amount' => 5_000_000]);

        $response = $this->actingAs($this->admin)
            ->getJson("/customers/{$this->partner->id}/debt-history");

        $response->assertOk()
            ->assertJsonPath('reconcile.status', 'mismatch')
            ->assertJsonPath('reconcile.user_warning', true);
        $payment = collect($response->json('entries'))->firstWhere('event_kind', 'invoice_payment');
        $this->assertEquals(-10_000_000, $payment['customer_display_balance_effect']);
    }

    public function test_pending_and_cancelled_cashflows_do_not_replace_invoice_payment(): void
    {
        $invoice = Invoice::create([
            'code' => 'HD-STATUS-' . uniqid(),
            'customer_id' => $this->partner->id,
            'branch_id' => $this->branch->id,
            'subtotal' => 15_000_000,
            'total' => 15_000_000,
            'discount' => 0,
            'customer_paid' => 10_000_000,
            'status' => 'completed',
        ]);
        foreach (['pending', 'Đã hủy'] as $index => $status) {
            CashFlow::create([
                'code' => 'PT-INVALID-' . $index . '-' . uniqid(),
                'type' => 'receipt',
                'amount' => 10_000_000,
                'target_type' => 'Khách hàng',
                'target_id' => $this->partner->id,
                'reference_type' => 'Invoice',
                'reference_code' => $invoice->code,
                'status' => $status,
            ]);
        }
        $this->partner->update(['debt_amount' => 5_000_000]);

        $response = $this->actingAs($this->admin)
            ->getJson("/customers/{$this->partner->id}/debt-history")
            ->assertOk();
        $entries = collect($response->json('entries'));

        $this->assertCount(2, $entries);
        $this->assertNotNull($entries->firstWhere('code', 'TTHD' . preg_replace('/^HD/', '', $invoice->code)));
        $this->assertFalse($entries->contains(fn ($entry) => str_starts_with((string) $entry['code'], 'PT-INVALID-')));
    }

    public function test_filtered_timeline_keeps_historical_running_balance(): void
    {
        $invoice = Invoice::create([
            'code' => 'HD-FILTER-' . uniqid(),
            'customer_id' => $this->partner->id,
            'branch_id' => $this->branch->id,
            'subtotal' => 15_000_000,
            'total' => 15_000_000,
            'discount' => 0,
            'customer_paid' => 10_000_000,
            'status' => 'completed',
        ]);
        CashFlow::create([
            'code' => 'PT-FILTER-' . uniqid(),
            'type' => 'receipt',
            'amount' => 10_000_000,
            'target_type' => 'Khách hàng',
            'target_id' => $this->partner->id,
            'reference_type' => 'Invoice',
            'reference_code' => $invoice->code,
            'status' => 'completed',
        ]);
        $this->partner->update(['debt_amount' => 5_000_000]);

        $response = $this->actingAs($this->admin)
            ->getJson("/customers/{$this->partner->id}/debt-history?filter=sale")
            ->assertOk()
            ->assertJsonPath('summary.current_debt', 5_000_000);

        $this->assertCount(1, $response->json('entries'));
        $this->assertEquals(15_000_000, $response->json('entries.0.customer_display_running_balance'));
    }

    public function test_dual_role_four_document_timeline_has_opposite_perspectives(): void
    {
        $invoice = Invoice::create([
            'code' => 'HD-FOUR-' . uniqid(),
            'customer_id' => $this->partner->id,
            'branch_id' => $this->branch->id,
            'subtotal' => 7_000_000,
            'total' => 7_000_000,
            'discount' => 0,
            'customer_paid' => 5_000_000,
            'status' => 'completed',
            'transaction_date' => Carbon::parse('2026-06-07 10:00:00'),
        ]);
        CashFlow::create([
            'code' => 'PT-FOUR-' . uniqid(),
            'type' => 'receipt',
            'amount' => 5_000_000,
            'time' => Carbon::parse('2026-06-08 10:00:00'),
            'target_type' => 'Khách hàng',
            'target_id' => $this->partner->id,
            'reference_type' => 'Invoice',
            'reference_code' => $invoice->code,
            'status' => 'completed',
        ]);
        $purchase = Purchase::create([
            'code' => 'PN-FOUR-' . uniqid(),
            'supplier_id' => $this->partner->id,
            'branch_id' => $this->branch->id,
            'total_amount' => 5_000_000,
            'paid_amount' => 0,
            'debt_amount' => 5_000_000,
            'status' => 'completed',
            'purchase_date' => Carbon::parse('2026-06-09 10:00:00'),
        ]);
        $supplierPayment = CashFlow::create([
            'code' => 'PCPN-FOUR-' . uniqid(),
            'type' => 'payment',
            'amount' => 3_000_000,
            'time' => Carbon::parse('2026-06-10 10:00:00'),
            'target_type' => 'Nhà cung cấp',
            'target_id' => $this->partner->id,
            'reference_type' => 'Purchase',
            'reference_code' => $purchase->code,
            'status' => 'completed',
        ]);
        $this->partner->update([
            'debt_amount' => 2_000_000,
            'supplier_debt_amount' => 2_000_000,
        ]);

        $customer = $this->actingAs($this->admin)
            ->getJson("/customers/{$this->partner->id}/debt-history")
            ->assertOk()
            ->json();
        $supplier = $this->getJson(
            "/api/suppliers/{$this->partner->id}/debt-transactions?view=partner&per_page=100&page=1"
        )->assertOk()->json();

        $this->assertEquals(0, $customer['summary']['current_debt']);
        $this->assertEquals(0, $supplier['summary']['current_debt']);
        $this->assertSame($supplierPayment->code, $customer['entries'][0]['code']);
        $this->assertSame($supplierPayment->code, $supplier['entries'][0]['code']);
        $this->assertEquals(0, $customer['entries'][0]['customer_display_running_balance']);
        $this->assertEquals(0, $supplier['entries'][0]['supplier_display_running_balance']);
        $this->assertFalse(collect($customer['entries'])->contains(
            fn ($entry) => str_contains((string) $entry['code'], 'OPENING-BALANCE')
        ));
        $this->assertFalse(collect($supplier['entries'])->contains(
            fn ($entry) => str_contains((string) $entry['code'], 'OPENING-BALANCE')
        ));
    }
}
