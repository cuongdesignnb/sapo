<?php

namespace Tests\Feature\Customers;

use App\Models\Customer;
use App\Models\Product;
use App\Models\User;
use App\Services\InvoiceSaleService;
use App\Services\PartnerTransactionGuard;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class MergedPartnerTransactionGuardTest extends TestCase
{
    use DatabaseTransactions;

    private User $admin;
    private Customer $source;
    private Customer $target;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::create([
            'name' => 'Merged Partner Guard Admin',
            'email' => 'merged-partner-guard-' . uniqid() . '@test.local',
            'password' => bcrypt('password'),
            'role_id' => null,
        ]);
        $this->target = Customer::create([
            'code' => 'KH-TARGET-' . uniqid(),
            'name' => 'Target Partner',
            'is_customer' => true,
            'is_supplier' => true,
            'status' => 'active',
        ]);
        $this->source = Customer::create([
            'code' => 'KH-SOURCE-' . uniqid(),
            'name' => 'Merged Source',
            'is_customer' => true,
            'is_supplier' => true,
            'status' => 'inactive',
            'merged_into_id' => $this->target->id,
            'merged_at' => now(),
        ]);
        $this->actingAs($this->admin);
    }

    public function test_domain_guard_and_invoice_service_reject_merged_source(): void
    {
        try {
            app(PartnerTransactionGuard::class)->assertCanTransact($this->source->id, 'customer_id');
            $this->fail('Merged source must be rejected.');
        } catch (ValidationException $exception) {
            $this->assertStringContainsString($this->target->code, $exception->errors()['customer_id'][0]);
        }

        try {
            app(InvoiceSaleService::class)->createSale([
                'customer_id' => $this->source->id,
                'subtotal' => 100_000,
                'total' => 100_000,
                'customer_paid' => 0,
                'items' => [],
            ]);
            $this->fail('Internal invoice service must reject merged source.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('customer_id', $exception->errors());
        }
    }

    public function test_order_payment_and_purchase_endpoints_reject_merged_source(): void
    {
        $product = Product::create([
            'sku' => 'SP-GUARD-' . uniqid(),
            'name' => 'Guard Product',
            'type' => 'standard',
            'cost_price' => 50_000,
            'retail_price' => 100_000,
            'stock_quantity' => 10,
            'has_serial' => false,
            'is_active' => true,
        ]);

        $this->post('/orders', [
            'customer_id' => $this->source->id,
            'status' => 'draft',
            'total_price' => 100_000,
            'total_payment' => 100_000,
            'amount_paid' => 0,
            'items' => [[
                'product_id' => $product->id,
                'qty' => 1,
                'price' => 100_000,
                'discount' => 0,
            ]],
        ])->assertSessionHasErrors('customer_id');

        $this->postJson("/customers/{$this->source->id}/debt-payment", [
            'mode' => 'auto',
            'amount' => 100_000,
        ])->assertStatus(422)->assertJsonValidationErrors('customer_id');

        $this->postJson('/api/pos/checkout', [
            'subtotal' => 100_000,
            'discount' => 0,
            'total' => 100_000,
            'customer_paid' => 0,
            'customer_id' => $this->source->id,
            'payment_method' => 'cash',
            'items' => [[
                'product_id' => $product->id,
                'quantity' => 1,
                'price' => 100_000,
                'discount' => 0,
            ]],
        ])->assertStatus(422)->assertJsonValidationErrors('customer_id');

        $this->post('/purchases', [
            'supplier_id' => $this->source->id,
            'items' => [[
                'product_id' => $product->id,
                'quantity' => 1,
                'price' => 50_000,
            ]],
            'paid_amount' => 0,
        ])->assertSessionHasErrors('supplier_id');
    }
}
