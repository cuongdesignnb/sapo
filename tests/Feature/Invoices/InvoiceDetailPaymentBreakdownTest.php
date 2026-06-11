<?php

namespace Tests\Feature\Invoices;

use App\Models\Branch;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class InvoiceDetailPaymentBreakdownTest extends TestCase
{
    use DatabaseTransactions;

    private User     $admin;
    private Customer $customer;
    private Branch   $branch;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::create([
            'name'     => 'Test Admin Breakdown',
            'email'    => 'test-admin-breakdown-' . uniqid() . '@test.local',
            'password' => bcrypt('password'),
            'role_id'  => null,
        ]);

        $this->customer = Customer::create([
            'code'        => 'KH-' . uniqid(),
            'name'        => 'Test Customer Breakdown',
            'phone'       => '09' . rand(10000000, 99999999),
            'debt_amount' => 0,
            'total_spent' => 0,
        ]);

        $this->branch = Branch::create([
            'name' => 'Branch Test Breakdown',
            'address' => '456 Test Rd',
        ]);
    }

    public function test_invoice_detail_breakdown_partial_deposit(): void
    {
        $this->actingAs($this->admin);

        // Case 1: order có cọc nhưng chưa trả đủ
        $invoice = Invoice::create([
            'code' => 'HD' . rand(100000, 999999),
            'customer_id' => $this->customer->id,
            'branch_id' => $this->branch->id,
            'total' => 1500000,
            'subtotal' => 1500000,
            'discount' => 0,
            'customer_paid' => 1000000,
            'order_deposit_applied_amount' => 1000000,
            'status' => 'Hoàn thành',
            'payment_method' => 'cash',
        ]);

        $response = $this->getJson("/invoices/{$invoice->id}/detail");
        $response->assertStatus(200);
        $response->assertJson([
            'total' => 1500000,
            'customer_paid' => 1000000,
            'order_deposit_applied_amount' => 1000000,
            'remaining_amount' => 500000,
            'debt_amount' => 500000,
            'paid_excluding_deposit' => 0,
        ]);

        // Case 2: sau khi thu nợ đủ
        $invoice->update(['customer_paid' => 1500000]);

        $response = $this->getJson("/invoices/{$invoice->id}/detail");
        $response->assertStatus(200);
        $response->assertJson([
            'total' => 1500000,
            'customer_paid' => 1500000,
            'order_deposit_applied_amount' => 1000000,
            'remaining_amount' => 0,
            'debt_amount' => 0,
            'paid_excluding_deposit' => 500000,
        ]);

        // Case 3: khách trả dư
        $invoice->update(['customer_paid' => 1700000]);

        $response = $this->getJson("/invoices/{$invoice->id}/detail");
        $response->assertStatus(200);
        $response->assertJson([
            'total' => 1500000,
            'customer_paid' => 1700000,
            'order_deposit_applied_amount' => 1000000,
            'remaining_amount' => 0,
            'debt_amount' => 0,
            'paid_excluding_deposit' => 700000,
        ]);
    }
}
