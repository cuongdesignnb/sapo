<?php

namespace Tests\Feature\Reports;

use App\Models\Customer;
use App\Models\Employee;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Product;
use App\Models\User;
use App\Support\Reports\SellerResolver;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * HOTFIX 24.27 — Prevent duplicate sellers and align invoice filter consistency.
 *
 * Key invariants:
 *   - User and employee with same name and linked via user_id → one canonical key
 *   - Two employees with same name but different people → disambiguated labels
 *   - Report and invoice list use same SellerResolver → same seller matches same invoices
 *   - Employee with no invoices has no revenue
 *   - Mismatched created_by (employee id = user id) → correct assignment via name check
 *   - Admin still appears (no regression from HOTFIX 24.26)
 *   - Profit 8 columns still correct
 *   - Cancelled invoices excluded
 */
class HOTFIX2427SellerDuplicateAndInvoiceFilterTest extends TestCase
{
    use DatabaseTransactions;

    private function adminUser(string $name = 'Admin 2427'): User
    {
        return User::create([
            'name'     => $name,
            'email'    => 'admin-2427-' . uniqid() . '@test.local',
            'password' => bcrypt('password'),
            'role_id'  => null,
            'status'   => 'active',
        ]);
    }

    private function regularUser(string $name = 'Staff 2427'): User
    {
        return User::create([
            'name'     => $name,
            'email'    => 'staff-2427-' . uniqid() . '@test.local',
            'password' => bcrypt('password'),
            'role_id'  => 1,
            'status'   => 'active',
        ]);
    }

    private function employee(string $name = 'NV 2427', ?string $code = null, ?int $userId = null): Employee
    {
        return Employee::create([
            'code'      => $code ?? ('NV-2427-' . uniqid()),
            'name'      => $name,
            'is_active' => true,
            'user_id'   => $userId,
        ]);
    }

    private function product(int $cost = 600_000, int $retail = 1_500_000): Product
    {
        return Product::create([
            'sku'                  => 'SKU-2427-' . uniqid(),
            'name'                 => 'SP 2427',
            'cost_price'           => $cost,
            'retail_price'         => $retail,
            'stock_quantity'       => 100,
            'inventory_total_cost' => $cost * 100,
            'has_serial'           => false,
        ]);
    }

    private function invoice(
        ?int $createdBy,
        ?string $createdByName,
        Product $product,
        int $qty = 1,
        int $price = 1_000_000,
        int $discount = 0,
        string $status = 'Hoàn thành',
        ?int $costPrice = 600_000
    ): Invoice {
        $subtotal = $qty * $price;
        $inv = Invoice::create([
            'code'             => 'HD-2427-' . uniqid(),
            'created_by'       => $createdBy,
            'created_by_name'  => $createdByName,
            'subtotal'         => $subtotal,
            'discount'         => $discount,
            'total'            => $subtotal - $discount,
            'customer_paid'    => $subtotal - $discount,
            'status'           => $status,
            'sales_channel'    => 'Bán trực tiếp',
        ]);
        InvoiceItem::create([
            'invoice_id' => $inv->id,
            'product_id' => $product->id,
            'quantity'   => $qty,
            'price'      => $price,
            'cost_price' => $costPrice,
        ]);
        $inv->created_at = Carbon::now()->startOfDay()->addMinute();
        $inv->save();
        return $inv;
    }

    // ── TC-01 — User and employee same name, linked via user_id → no duplicate ──
    public function test_user_and_employee_same_name_same_person_no_duplicate(): void
    {
        $user = $this->regularUser('Vũ Hồng Nhung');
        $emp  = $this->employee('Vũ Hồng Nhung', 'NV001', $user->id);
        $product = $this->product();

        // Invoice created by user (created_by = NULL, created_by_name = user.name)
        $this->invoice(null, $user->name, $product, 1, 2_000_000);

        $res = $this->actingAs($user)->get('/reports/employees?concern=profit&view=report');
        $res->assertOk();
        $rows = $res->viewData('page')['props']['reportRows'];

        // Only 1 row for Vũ Hồng Nhung, not 2
        $matchingRows = collect($rows)->filter(fn($r) => str_contains($r['name'], 'Vũ Hồng Nhung'));
        $this->assertCount(1, $matchingRows, 'Same person (user + employee linked) must appear as one row');

        // Canonical key should be employee:<emp_id> (merged)
        $row = $matchingRows->first();
        $this->assertSame("employee:{$emp->id}", $row['id']);

        // Dropdown also no duplicate
        $options = $res->viewData('page')['props']['employees'];
        $nhungOptions = collect($options)->filter(fn($o) => str_contains($o['name'], 'Vũ Hồng Nhung'));
        $this->assertCount(1, $nhungOptions, 'Dropdown must not duplicate same person');
    }

    // ── TC-02 — Two employees same name different people → disambiguated labels ──
    public function test_two_employees_same_name_different_people_disambiguated(): void
    {
        $admin = $this->adminUser();
        $emp1 = $this->employee('Vũ Hồng Nhung', 'NV001');
        $emp2 = $this->employee('Vũ Hồng Nhung', 'NV002');
        $product = $this->product();

        $this->invoice($emp1->id, $emp1->name, $product, 1, 1_000_000);
        $this->invoice($emp2->id, $emp2->name, $product, 1, 2_000_000);

        $res = $this->actingAs($admin)->get('/reports/employees?concern=sales&view=report');
        $res->assertOk();
        $rows = $res->viewData('page')['props']['reportRows'];

        $nhungRows = collect($rows)->filter(fn($r) => str_contains($r['name'], 'Vũ Hồng Nhung'));
        $this->assertCount(2, $nhungRows, 'Two different employees must appear as two rows');

        // Labels must be disambiguated (contain code)
        $names = $nhungRows->pluck('name')->all();
        $this->assertNotEquals($names[0], $names[1], 'Duplicate names must be disambiguated by code');
        $this->assertTrue(
            str_contains($names[0], 'NV001') || str_contains($names[0], 'NV002'),
            'Label must contain employee code for disambiguation'
        );
    }

    // ── TC-03 — Invoice report and invoice list filter use same seller key ──
    public function test_invoice_report_and_invoice_list_use_same_seller_key(): void
    {
        $user = $this->adminUser('Seller TC03');
        $product = $this->product();
        $inv = $this->invoice(null, $user->name, $product, 1, 3_000_000);

        // Get the seller key from report
        $resReport = $this->actingAs($user)->get('/reports/employees?concern=sales&view=report');
        $resReport->assertOk();
        $rows = $resReport->viewData('page')['props']['reportRows'];
        $row = collect($rows)->firstWhere('seller_name', $user->name);
        $this->assertNotNull($row, 'Seller must appear in report');
        $sellerKey = $row['seller_key'];

        // Filter invoices with same seller key
        $resInvoice = $this->actingAs($user)->get("/invoices?seller_key={$sellerKey}");
        $resInvoice->assertOk();
        $invoices = $resInvoice->viewData('page')['props']['invoices']['data'];

        $this->assertNotEmpty($invoices, 'Invoice list must return invoices for the seller key');
        $invoiceIds = collect($invoices)->pluck('id')->all();
        $this->assertContains($inv->id, $invoiceIds, 'Same invoice must appear in both report and invoice list');
    }

    // ── TC-04 — Employee with no invoices has no revenue ──
    public function test_employee_with_no_invoices_has_no_revenue(): void
    {
        $admin = $this->adminUser();
        $emp = $this->employee('Ghost Employee', 'NV999');
        $product = $this->product();
        // Create invoice for admin only
        $this->invoice(null, $admin->name, $product, 1, 5_000_000);

        $res = $this->actingAs($admin)->get('/reports/employees?concern=sales&view=report');
        $res->assertOk();
        $rows = $res->viewData('page')['props']['reportRows'];

        $ghostRow = collect($rows)->firstWhere('id', "employee:{$emp->id}");
        // Ghost employee should not appear in report rows (no invoices = no row)
        $this->assertNull($ghostRow, 'Employee with no invoices must not appear in report with revenue');
    }

    // ── TC-05 — Mismatched created_by doesn't assign wrong person's revenue ──
    public function test_mismatched_created_by_does_not_assign_wrong_revenue(): void
    {
        $admin = $this->adminUser();
        $emp = $this->employee('Nhân viên Thật', 'NV-REAL');
        $user = $this->regularUser('User Khác');
        $product = $this->product();

        // Invoice created by the user (created_by = user.id, created_by_name = user.name)
        $inv = $this->invoice($user->id, $user->name, $product, 1, 10_000_000);

        $resolver = new SellerResolver();
        $map = $resolver->invoiceSellerMap(Invoice::whereKey($inv->id));

        $sellerKey = $map[$inv->id];
        // The key must NOT be employee:<emp_id> unless employee is linked to this user
        if (str_starts_with($sellerKey, 'employee:')) {
            $empId = (int) substr($sellerKey, 9);
            $linkedEmp = Employee::find($empId);
            $this->assertNotNull($linkedEmp);
            $this->assertSame($user->id, $linkedEmp->user_id,
                'If mapped to employee, must be linked via user_id');
        } else {
            // Must map to user key, not to the employee with coincidentally same numeric ID
            $this->assertTrue(
                str_starts_with($sellerKey, 'user:'),
                "Invoice by user must map to user: key, got: {$sellerKey}"
            );
        }
    }

    // ── TC-06 — Admin still appears (no regression from 24.26) ──
    public function test_admin_still_appears_in_report(): void
    {
        $admin = $this->adminUser('Admin Persistence');
        $product = $this->product();
        $this->invoice(null, $admin->name, $product, 1, 1_500_000);

        $res = $this->actingAs($admin)->get('/reports/employees?concern=sales&view=report');
        $res->assertOk();
        $rows = $res->viewData('page')['props']['reportRows'];

        // Admin must appear
        $adminRow = collect($rows)->first(fn($r) =>
            str_contains($r['id'], 'user:') || str_contains($r['id'], 'employee:')
        );
        $this->assertNotNull($adminRow, 'Admin must still appear in report');
        $this->assertEquals(1_500_000, (int) $adminRow['revenue']);

        // Filter dropdown must contain admin
        $options = $res->viewData('page')['props']['employees'];
        $adminOpt = collect($options)->first(fn($o) => $o['name'] === 'Admin Persistence');
        $this->assertNotNull($adminOpt, 'Admin must be in dropdown');
    }

    // ── TC-07 — Profit 8 columns still correct ──
    public function test_profit_8_columns_still_correct(): void
    {
        $admin = $this->adminUser('Profit TC-07');
        $product = $this->product(cost: 600_000);
        $this->invoice(null, $admin->name, $product, 2, 1_000_000, discount: 100_000, costPrice: 600_000);

        $res = $this->actingAs($admin)->get('/reports/employees?concern=profit&view=report');
        $res->assertOk();
        $rows = $res->viewData('page')['props']['reportRows'];
        $row = collect($rows)->first(fn($r) => str_contains($r['seller_name'] ?? $r['name'], 'Profit TC-07'));
        $this->assertNotNull($row);

        $this->assertArrayHasKey('gross_revenue', $row);
        $this->assertArrayHasKey('invoice_discount', $row);
        $this->assertArrayHasKey('revenue_after_discount', $row);
        $this->assertArrayHasKey('return_value', $row);
        $this->assertArrayHasKey('net_revenue', $row);
        $this->assertArrayHasKey('total_cogs', $row);
        $this->assertArrayHasKey('gross_profit', $row);

        $this->assertEquals(2_000_000, (int) $row['gross_revenue']);
        $this->assertEquals(100_000, (int) $row['invoice_discount']);
        $this->assertEquals(1_900_000, (int) $row['revenue_after_discount']);
        $this->assertEquals(1_200_000, (int) $row['total_cogs']);
        $this->assertEquals(700_000, (int) $row['gross_profit']);
    }

    // ── TC-08 — Cancelled invoice not counted ──
    public function test_cancelled_invoice_not_counted(): void
    {
        $admin = $this->adminUser('Cancel TC-08');
        $product = $this->product();
        $this->invoice(null, $admin->name, $product, 1, 5_000_000, status: 'Đã hủy');
        $this->invoice(null, $admin->name, $product, 1, 2_000_000, status: 'Hoàn thành');

        $res = $this->actingAs($admin)->get('/reports/employees?concern=sales&view=report');
        $res->assertOk();
        $rows = $res->viewData('page')['props']['reportRows'];
        $row = collect($rows)->first(fn($r) => str_contains($r['name'], 'Cancel TC-08'));
        $this->assertNotNull($row);
        $this->assertEquals(2_000_000, (int) $row['revenue'], 'Only non-cancelled invoice should count');
    }

    // ── TC-09 — SellerResolver canonical merge: user with employee row ──
    public function test_seller_resolver_merges_user_with_employee(): void
    {
        $user = $this->regularUser('Merged Person');
        $emp  = $this->employee('Merged Person', 'NV-MERGE', $user->id);
        $product = $this->product();

        // Invoice with created_by = user.id
        $inv = $this->invoice($user->id, $user->name, $product, 1, 1_000_000);

        $resolver = new SellerResolver();
        $map = $resolver->invoiceSellerMap(Invoice::whereKey($inv->id));

        // Should merge to employee:<emp_id> since employee.user_id = user.id
        $this->assertSame("employee:{$emp->id}", $map[$inv->id],
            'User with linked employee must merge to employee canonical key');
    }

    // ── TC-10 — SellerResolver orphan name matching one employee → merges ──
    public function test_orphan_name_matching_one_employee_merges(): void
    {
        $admin = $this->adminUser();
        $emp = $this->employee('Unique Seller', 'NV-UNIQ');
        $product = $this->product();

        // Invoice with no created_by, created_by_name = employee name
        $inv = $this->invoice(null, 'Unique Seller', $product, 1, 1_000_000);

        $resolver = new SellerResolver();
        $map = $resolver->invoiceSellerMap(Invoice::whereKey($inv->id));

        $this->assertSame("employee:{$emp->id}", $map[$inv->id],
            'Orphan name matching exactly one employee must merge to employee key');
    }

    // ── TC-11 — SellerResolver orphan name matching multiple employees stays orphan ──
    public function test_orphan_name_matching_multiple_employees_stays_orphan(): void
    {
        $admin = $this->adminUser();
        $this->employee('Ambiguous Name', 'NV-AMB1');
        $this->employee('Ambiguous Name', 'NV-AMB2');
        $product = $this->product();

        $inv = $this->invoice(null, 'Ambiguous Name', $product, 1, 1_000_000);

        $resolver = new SellerResolver();
        $map = $resolver->invoiceSellerMap(Invoice::whereKey($inv->id));

        $this->assertSame('orphan:Ambiguous Name', $map[$inv->id],
            'Orphan name matching multiple employees must stay orphan');
    }

    // ── TC-12 — Invoice filter options contain sellers from SellerResolver ──
    public function test_invoice_filter_options_contain_sellers(): void
    {
        $admin = $this->adminUser('Invoice Filter Admin');
        $product = $this->product();
        $this->invoice(null, $admin->name, $product);

        $res = $this->actingAs($admin)->get('/invoices');
        $res->assertOk();
        $filterOptions = $res->viewData('page')['props']['filterOptions'];
        $this->assertArrayHasKey('sellers', $filterOptions, 'Invoice page must have sellers in filter options');

        $adminOpt = collect($filterOptions['sellers'])->first(fn($s) => $s['name'] === 'Invoice Filter Admin');
        $this->assertNotNull($adminOpt, 'Admin must appear in invoice seller options');
    }
}
