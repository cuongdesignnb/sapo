<?php

namespace Tests\Feature\SupplierDebt;

use App\Models\Branch;
use App\Models\CashFlow;
use App\Models\Customer;
use App\Models\Purchase;
use App\Models\User;
use App\Models\Invoice;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class SupplierDebtTimelineTest extends TestCase
{
    use DatabaseTransactions;

    private User     $admin;
    private Customer $partner;
    private Branch   $branch;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::create([
            'name'     => 'Test Admin Supplier Debt',
            'email'    => 'test-admin-supplier-debt-' . uniqid() . '@test.local',
            'password' => bcrypt('password'),
            'role_id'  => null,
        ]);

        $this->partner = Customer::create([
            'code'                 => 'KH-NCC-' . uniqid(),
            'name'                 => 'Test Dual Role Partner',
            'phone'                => '09' . rand(10000000, 99999999),
            'debt_amount'          => 0,
            'supplier_debt_amount' => 0,
            'is_customer'          => true,
            'is_supplier'          => true,
        ]);

        $this->branch = Branch::create([
            'name'    => 'Branch Test Supplier Debt',
            'address' => '123 Supplier Rd',
        ]);
    }

    public function test_supplier_debt_timeline_calculations_and_ordering_newest_first(): void
    {
        $this->actingAs($this->admin);

        // 1. Purchase on 2026-06-01
        $p1 = Purchase::create([
            'code'          => 'PN' . rand(100000, 999999),
            'supplier_id'   => $this->partner->id,
            'branch_id'     => $this->branch->id,
            'total_amount'  => 15000000,
            'paid_amount'   => 0,
            'debt_amount'   => 15000000,
            'status'        => 'completed',
            'purchase_date' => Carbon::parse('2026-06-01 10:00:00'),
            'created_at'    => Carbon::parse('2026-06-01 10:00:00'),
        ]);

        // Update supplier debt amount
        $this->partner->update(['supplier_debt_amount' => 15000000]);

        // 2. Supplier payment on 2026-06-02
        $cf = CashFlow::create([
            'code'           => 'PCPN' . rand(100000, 999999),
            'type'           => 'payment',
            'amount'         => 10000000,
            'time'           => Carbon::parse('2026-06-02 10:00:00'),
            'target_type'    => 'Nhà cung cấp',
            'target_id'      => $this->partner->id,
            'reference_type' => 'Purchase',
            'reference_id'   => $p1->id,
            'reference_code' => $p1->code,
            'status'         => 'completed',
            'created_at'     => Carbon::parse('2026-06-02 10:00:00'),
        ]);

        // Update supplier debt amount after payment
        $this->partner->update(['supplier_debt_amount' => 5000000]);

        // Get supplier partner view timeline
        $response = $this->getJson("/api/suppliers/{$this->partner->id}/debt-transactions?view=partner&per_page=100&page=1");
        $response->assertStatus(200);

        $data = $response->json();
        $entries = $data['entries'];

        // Assert sorting: newest first (PCPN should be first, PN should be second)
        $this->assertCount(2, $entries);
        $this->assertEquals($cf->code, $entries[0]['code']);
        $this->assertEquals($p1->code, $entries[1]['code']);

        // Assert current debt is on top summary
        $currentDebt = $data['summary']['current_debt'];
        $this->assertEquals(5000000, $currentDebt);

        // Assert first row's running balance equals current debt
        $this->assertEquals($currentDebt, $entries[0]['supplier_display_running_balance']);
    }

    public function test_supplier_debt_timeline_dual_role_opposite_signs(): void
    {
        $this->actingAs($this->admin);

        // Purchase PN01 = 10,000,000 (Store owes Supplier)
        $p = Purchase::create([
            'code'          => 'PN' . rand(100000, 999999),
            'supplier_id'   => $this->partner->id,
            'total_amount'  => 10000000,
            'paid_amount'   => 0,
            'debt_amount'   => 10000000,
            'status'        => 'completed',
            'purchase_date' => Carbon::parse('2026-06-01 10:00:00'),
            'created_at'    => Carbon::parse('2026-06-01 10:00:00'),
        ]);

        // Sale Invoice HD01 = 7,000,000 (Customer owes Store)
        $inv = Invoice::create([
            'code'             => 'HD' . rand(100000, 999999),
            'customer_id'      => $this->partner->id,
            'total'            => 7000000,
            'subtotal'         => 7000000,
            'discount'         => 0,
            'customer_paid'    => 0,
            'status'           => 'completed',
            'transaction_date' => Carbon::parse('2026-06-02 10:00:00'),
            'created_at'       => Carbon::parse('2026-06-02 10:00:00'),
        ]);

        // Update stored balances
        $this->partner->update([
            'debt_amount'          => 7000000,
            'supplier_debt_amount' => 10000000,
        ]);

        // 1. Customer screen view (receivable perspective)
        // target = debt_amount - supplier_debt_amount = 7m - 10m = -3m
        $custResponse = $this->getJson("/customers/{$this->partner->id}/debt-history");
        $custResponse->assertStatus(200);
        $custData = $custResponse->json();
        $this->assertEquals(-3000000, $custData['summary']['current_debt']);
        $this->assertEquals(-3000000, $custData['entries'][0]['customer_display_running_balance']);

        // 2. Supplier screen partner view (payable perspective)
        // target = supplier_debt_amount - debt_amount = 10m - 7m = +3m
        $supResponse = $this->getJson("/api/suppliers/{$this->partner->id}/debt-transactions?view=partner&per_page=100&page=1");
        $supResponse->assertStatus(200);
        $supData = $supResponse->json();
        $this->assertEquals(3000000, $supData['summary']['current_debt']);
        $this->assertEquals(3000000, $supData['entries'][0]['supplier_display_running_balance']);
    }
}
