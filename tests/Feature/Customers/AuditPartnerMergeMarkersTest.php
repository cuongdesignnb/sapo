<?php

namespace Tests\Feature\Customers;

use App\Models\Customer;
use App\Models\CustomerDebt;
use App\Models\Invoice;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class AuditPartnerMergeMarkersTest extends TestCase
{
    use DatabaseTransactions;

    public function test_audit_reports_nonzero_legacy_marker_without_modifying_it(): void
    {
        $partner = Customer::create([
            'code' => 'KH-AUDIT-' . uniqid(),
            'name' => 'Audit Merge Marker',
            'debt_amount' => 300_000,
            'supplier_debt_amount' => 0,
            'is_customer' => true,
        ]);
        Invoice::create([
            'code' => 'HD-AUDIT-' . uniqid(),
            'customer_id' => $partner->id,
            'subtotal' => 300_000,
            'total' => 300_000,
            'customer_paid' => 0,
            'status' => 'completed',
        ]);
        $marker = CustomerDebt::create([
            'customer_id' => $partner->id,
            'ref_code' => 'MERGE-CUSTOMER-' . $partner->id,
            'amount' => 300_000,
            'debt_total' => 300_000,
            'type' => 'adjustment',
            'recorded_at' => now(),
        ]);

        Artisan::call('partners:audit-merge-markers', [
            '--partner' => $partner->id,
            '--json' => true,
        ]);
        $rows = json_decode(Artisan::output(), true, flags: JSON_THROW_ON_ERROR);

        $this->assertCount(1, $rows);
        $this->assertSame('manual_review_required', $rows[0]['suggested_action']);
        $this->assertTrue($rows[0]['has_explanatory_documents']);
        $this->assertEquals(300_000, $rows[0]['amount']);
        $this->assertEquals(300_000, (float) $marker->fresh()->amount);
    }
}
