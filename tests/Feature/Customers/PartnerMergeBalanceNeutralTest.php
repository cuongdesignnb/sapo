<?php

namespace Tests\Feature\Customers;

use App\Models\Customer;
use App\Models\CustomerDebt;
use App\Models\PartnerMerge;
use App\Models\SupplierDebtTransaction;
use App\Models\User;
use App\Services\PartnerMergeService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class PartnerMergeBalanceNeutralTest extends TestCase
{
    use DatabaseTransactions;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::create([
            'name' => 'Partner Merge Admin',
            'email' => 'partner-merge-' . uniqid() . '@test.local',
            'password' => bcrypt('password'),
            'role_id' => null,
        ]);
        $this->actingAs($this->admin);
    }

    public function test_merge_sums_each_balance_once_and_creates_unique_zero_marker(): void
    {
        $source = $this->partner('SOURCE', 300_000, 0, true, false);
        $target = $this->partner('TARGET', 0, 500_000, false, true);
        $source->update([
            'total_spent' => 1_500_000,
            'total_returns' => 100_000,
            'total_bought' => 250_000,
        ]);
        $target->update([
            'total_spent' => 500_000,
            'total_returns' => 50_000,
            'total_bought' => 750_000,
        ]);

        $preview = app(PartnerMergeService::class)->merge($source, $target);
        $markerCode = "MERGE-PARTNER-{$source->id}-TO-{$target->id}";

        $this->assertEquals(300_000, $preview['after']['debt_amount']);
        $this->assertEquals(500_000, $preview['after']['supplier_debt_amount']);
        $this->assertEquals(-200_000, $preview['after']['customer_net_position']);
        $this->assertEquals(200_000, $preview['after']['supplier_net_position']);

        $target->refresh();
        $source->refresh();
        $this->assertEquals(300_000, (float) $target->debt_amount);
        $this->assertEquals(500_000, (float) $target->supplier_debt_amount);
        $this->assertTrue((bool) $target->is_customer);
        $this->assertTrue((bool) $target->is_supplier);
        $this->assertEquals(2_000_000, (float) $target->total_spent);
        $this->assertEquals(150_000, (float) $target->total_returns);
        $this->assertEquals(1_000_000, (float) $target->total_bought);
        $this->assertSame('inactive', $source->status);
        $this->assertEquals($target->id, $source->merged_into_id);
        $this->assertEquals(0, (float) $source->debt_amount);
        $this->assertEquals(0, (float) $source->total_spent);

        $marker = CustomerDebt::where('customer_id', $target->id)
            ->where('ref_code', $markerCode)
            ->firstOrFail();
        $this->assertEquals(0, (float) $marker->amount);
        $this->assertSame('merge_marker', $marker->type);
        $merge = PartnerMerge::where('ref_code', $markerCode)->firstOrFail();
        $this->assertEquals(1_500_000, (float) $merge->source_total_spent_before);
        $this->assertEquals(500_000, (float) $merge->target_total_spent_before);
        $this->assertEquals(2_000_000, (float) $merge->target_total_spent_after);
        $this->assertEquals(300_000, (float) $merge->target_debt_amount_after);
        $this->assertSame(1, PartnerMerge::where('ref_code', $markerCode)->count());
        $this->assertSame(0, SupplierDebtTransaction::where('code', $markerCode)->count());

        try {
            app(PartnerMergeService::class)->merge($source->fresh(), $target->fresh());
            $this->fail('A repeated merge must not be applied twice.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('merge_with_id', $exception->errors());
        }

        $this->assertEquals(300_000, (float) $target->fresh()->debt_amount);
        $this->assertEquals(500_000, (float) $target->fresh()->supplier_debt_amount);
        $this->assertSame(1, PartnerMerge::where('ref_code', $markerCode)->count());
        $this->assertSame(1, CustomerDebt::where('ref_code', $markerCode)->count());
    }

    public function test_merge_marker_does_not_block_legacy_opening_for_unexplained_credit(): void
    {
        $source = $this->partner('CREDIT', -200_000, 0, true, false);
        $target = $this->partner('SUPPLIER', 0, 0, false, true);

        app(PartnerMergeService::class)->merge($source, $target);

        $response = $this->getJson("/customers/{$target->id}/debt-history")
            ->assertOk()
            ->assertJsonPath('summary.current_debt', -200_000);
        $entries = collect($response->json('entries'));

        $this->assertTrue($entries->contains(
            fn (array $entry) => str_contains((string) $entry['code'], 'OPENING-BALANCE')
        ));
        $this->assertNotNull($entries->firstWhere(
            'code',
            "MERGE-PARTNER-{$source->id}-TO-{$target->id}"
        ));
    }

    private function partner(
        string $prefix,
        float $debt,
        float $supplierDebt,
        bool $isCustomer,
        bool $isSupplier
    ): Customer {
        return Customer::create([
            'code' => $prefix . '-' . uniqid(),
            'name' => $prefix,
            'debt_amount' => $debt,
            'supplier_debt_amount' => $supplierDebt,
            'total_spent' => 0,
            'total_bought' => 0,
            'is_customer' => $isCustomer,
            'is_supplier' => $isSupplier,
            'status' => 'active',
        ]);
    }
}
