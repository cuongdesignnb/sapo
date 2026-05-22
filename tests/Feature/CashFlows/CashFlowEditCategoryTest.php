<?php

namespace Tests\Feature\CashFlows;

use App\Models\CashFlow;
use App\Models\Setting;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class CashFlowEditCategoryTest extends TestCase
{
    use DatabaseTransactions;

    private function admin(): User
    {
        return User::factory()->create(['role_id' => null]);
    }

    private function createPaymentCashFlow(array $overrides = []): CashFlow
    {
        return CashFlow::create(array_merge([
            'code' => 'PC-EDIT-CAT-' . uniqid(),
            'type' => 'payment',
            'amount' => 250000,
            'time' => Carbon::now(),
            'category' => 'Chi khác',
            'target_type' => 'Khác',
            'target_name' => 'Người nhận test',
            'accounting_result' => true,
            'payment_method' => 'cash',
            'bank_account_id' => null,
            'description' => 'Test đổi loại chi',
            'status' => 'active',
        ], $overrides));
    }

    public function test_update_payment_cashflow_can_change_category_without_changing_type(): void
    {
        Setting::where('key', 'lock_date')->delete();
        $cashFlow = $this->createPaymentCashFlow();

        $this->actingAs($this->admin())
            ->put(route('cash_flows.update', $cashFlow), [
                'time' => Carbon::parse($cashFlow->time)->format('Y-m-d\TH:i'),
                'category' => 'Bảo hiểm',
                'target_type' => $cashFlow->target_type,
                'target_name' => $cashFlow->target_name,
                'amount' => $cashFlow->amount,
                'description' => $cashFlow->description,
                'accounting_result' => $cashFlow->accounting_result,
                'payment_method' => $cashFlow->payment_method,
                'bank_account_id' => null,
            ])
            ->assertRedirect();

        $cashFlow->refresh();

        $this->assertSame('Bảo hiểm', $cashFlow->category);
        $this->assertSame('payment', $cashFlow->type);
    }

    public function test_update_payment_cashflow_ignores_type_payload(): void
    {
        Setting::where('key', 'lock_date')->delete();
        $cashFlow = $this->createPaymentCashFlow();

        $this->actingAs($this->admin())
            ->put(route('cash_flows.update', $cashFlow), [
                'type' => 'receipt',
                'time' => Carbon::parse($cashFlow->time)->format('Y-m-d\TH:i'),
                'category' => 'Bảo hiểm',
                'target_type' => $cashFlow->target_type,
                'target_name' => $cashFlow->target_name,
                'amount' => $cashFlow->amount,
                'description' => $cashFlow->description,
                'accounting_result' => $cashFlow->accounting_result,
                'payment_method' => $cashFlow->payment_method,
                'bank_account_id' => null,
            ])
            ->assertRedirect();

        $cashFlow->refresh();

        $this->assertSame('Bảo hiểm', $cashFlow->category);
        $this->assertSame('payment', $cashFlow->type);
    }
}
