<?php

namespace App\Services;

use App\Models\CashFlow;
use App\Models\Customer;
use App\Models\CustomerPaymentAllocation;
use App\Models\Invoice;
use App\Support\Status\BusinessStatus;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CustomerPaymentService
{
    public const CANCELLED = 'cancelled';
    public const ALREADY_CANCELLED = 'already_cancelled';
    public const SOURCE_DOCUMENT_REQUIRED = 'source_document_required';

    public function collect(
        Customer $customer,
        float $paymentAmount,
        string $mode = 'auto',
        array $requestedAllocations = [],
        ?string $note = null,
        Carbon|string|null $paidAt = null
    ): array {
        if ($paymentAmount <= 0) {
            throw ValidationException::withMessages(['amount' => 'Số tiền thanh toán phải lớn hơn 0.']);
        }

        return DB::transaction(function () use (
            $customer,
            $paymentAmount,
            $mode,
            $requestedAllocations,
            $note,
            $paidAt
        ) {
            app(PartnerTransactionGuard::class)->assertCanTransact($customer->id, 'customer_id');
            $lockedCustomer = Customer::query()->lockForUpdate()->findOrFail($customer->id);
            $debtBefore = (float) $lockedCustomer->debt_amount;
            $allocations = $mode === 'manual'
                ? $this->resolveManualAllocations($lockedCustomer, $paymentAmount, $requestedAllocations)
                : $this->resolveAutomaticAllocations($lockedCustomer, $paymentAmount);
            $allocatedAmount = (float) collect($allocations)->sum('amount');
            $unallocatedAmount = max(0.0, $paymentAmount - $allocatedAmount);
            $paymentTime = $paidAt ? Carbon::parse($paidAt) : now();

            $cashFlow = CashFlow::create([
                'code' => 'PT' . date('ymdHis') . random_int(10, 99),
                'type' => 'receipt',
                'amount' => $paymentAmount,
                'time' => $paymentTime,
                'category' => 'Thu nợ khách hàng',
                'target_type' => 'Khách hàng',
                'target_id' => $lockedCustomer->id,
                'target_name' => $lockedCustomer->name,
                'reference_type' => 'DebtPayment',
                'reference_code' => null,
                'description' => $note ?: 'Thu nợ khách hàng ' . $lockedCustomer->name,
                'status' => 'active',
            ]);

            if ($paidAt) {
                $cashFlow->created_at = $paymentTime;
                $cashFlow->save();
            }

            $allocationCodes = [];
            foreach ($allocations as $allocation) {
                $invoice = Invoice::query()->lockForUpdate()->findOrFail($allocation['invoice_id']);
                $invoice->increment('customer_paid', $allocation['amount']);
                CustomerPaymentAllocation::create([
                    'cash_flow_id' => $cashFlow->id,
                    'customer_id' => $lockedCustomer->id,
                    'invoice_id' => $invoice->id,
                    'amount' => $allocation['amount'],
                ]);
                $allocationCodes[] = $invoice->code . ':' . number_format($allocation['amount'], 2, '.', '');
            }

            $cashFlow->reference_code = implode(';', $allocationCodes);
            $cashFlow->save();

            app(CustomerDebtService::class)->recordPayment(
                $lockedCustomer->id,
                $paymentAmount,
                null,
                $note ?: "Thu nợ khách hàng {$lockedCustomer->name}",
                ['ref_code' => $cashFlow->code]
            );

            $debtAfter = (float) $lockedCustomer->fresh()->debt_amount;

            return [
                'payment_amount' => $paymentAmount,
                'allocated_amount' => $allocatedAmount,
                'unallocated_amount' => $unallocatedAmount,
                'debt_before' => $debtBefore,
                'debt_after' => $debtAfter,
                'is_overpayment' => $unallocatedAmount > 0.0,
                'overpayment_amount' => $unallocatedAmount,
                'cash_flow_id' => $cashFlow->id,
                'cash_flow_code' => $cashFlow->code,
            ];
        });
    }

    public function cancel(CashFlow $cashFlow): string
    {
        return DB::transaction(function () use ($cashFlow) {
            $flow = CashFlow::withTrashed()->lockForUpdate()->findOrFail($cashFlow->id);
            if (!BusinessStatus::isValidCashFlow($flow->status) || $flow->trashed()) {
                return self::ALREADY_CANCELLED;
            }

            if ($flow->reference_type === 'DebtPayment') {
                $this->cancelDebtPayment($flow);
            } elseif ($flow->reference_type === 'Invoice') {
                $this->cancelInvoicePayment($flow);
            } elseif (in_array($flow->reference_type, [
                'Order',
                'OrderReturn',
                'Purchase',
                'PurchaseReturn',
                'SupplierPayment',
            ], true)) {
                return self::SOURCE_DOCUMENT_REQUIRED;
            }

            $flow->status = 'cancelled';
            $flow->save();
            $flow->delete();

            return self::CANCELLED;
        });
    }

    public function isFinanciallyLinked(CashFlow $cashFlow): bool
    {
        return in_array($cashFlow->reference_type, [
            'DebtPayment',
            'Invoice',
            'Order',
            'OrderReturn',
            'Purchase',
            'PurchaseReturn',
            'SupplierPayment',
        ], true);
    }

    private function resolveAutomaticAllocations(Customer $customer, float $paymentAmount): array
    {
        $remaining = $paymentAmount;
        $allocations = [];
        $invoices = app(CustomerReceivableInvoiceService::class)->query($customer)->get();

        foreach ($invoices as $invoice) {
            if ($remaining < 0.01) {
                break;
            }

            $invoiceRemaining = app(CustomerReceivableInvoiceService::class)->remaining($invoice);
            $allocated = min($remaining, $invoiceRemaining);
            if ($allocated < 0.01) {
                continue;
            }
            $allocations[] = ['invoice_id' => $invoice->id, 'amount' => $allocated];
            $remaining -= $allocated;
        }

        return $allocations;
    }

    private function resolveManualAllocations(
        Customer $customer,
        float $paymentAmount,
        array $requestedAllocations
    ): array {
        $allocations = [];
        $allocatedTotal = 0.0;
        $seenInvoiceIds = [];

        foreach ($requestedAllocations as $requested) {
            $invoiceId = (int) ($requested['invoice_id'] ?? 0);
            $amount = (float) ($requested['amount'] ?? 0);
            if ($amount <= 0) {
                throw ValidationException::withMessages([
                    'allocations' => 'Số tiền phân bổ phải lớn hơn 0.',
                ]);
            }
            if (isset($seenInvoiceIds[$invoiceId])) {
                throw ValidationException::withMessages([
                    'allocations' => 'Mỗi hóa đơn chỉ được xuất hiện một lần trong danh sách phân bổ.',
                ]);
            }
            $seenInvoiceIds[$invoiceId] = true;

            $invoice = app(CustomerReceivableInvoiceService::class)->query($customer)
                ->whereKey($invoiceId)
                ->lockForUpdate()
                ->first();
            if (!$invoice) {
                throw ValidationException::withMessages([
                    'allocations' => 'Hóa đơn phân bổ không hợp lệ hoặc không còn nợ.',
                ]);
            }

            $invoiceRemaining = app(CustomerReceivableInvoiceService::class)->remaining($invoice);
            if ($amount > $invoiceRemaining + 0.01) {
                throw ValidationException::withMessages([
                    'allocations' => "Số phân bổ cho hóa đơn {$invoice->code} vượt số còn phải thu.",
                ]);
            }
            $allocatedTotal += $amount;
            if ($allocatedTotal > $paymentAmount + 0.01) {
                throw ValidationException::withMessages([
                    'allocations' => 'Tổng phân bổ không được vượt số tiền thực nhận.',
                ]);
            }
            $allocations[] = ['invoice_id' => $invoice->id, 'amount' => $amount];
        }

        return $allocations;
    }

    private function cancelDebtPayment(CashFlow $flow): void
    {
        $allocations = CustomerPaymentAllocation::query()
            ->where('cash_flow_id', $flow->id)
            ->lockForUpdate()
            ->get();

        foreach ($allocations as $allocation) {
            $invoice = Invoice::query()->lockForUpdate()->find($allocation->invoice_id);
            if ($invoice) {
                $invoice->customer_paid = max(
                    0.0,
                    (float) $invoice->customer_paid - (float) $allocation->amount
                );
                $invoice->save();
            }
        }

        if ($flow->target_id && (float) $flow->amount > 0) {
            app(CustomerDebtService::class)->recordAdjustment(
                (int) $flow->target_id,
                (float) $flow->amount,
                "Hủy phiếu thu {$flow->code}",
                ['ref_code' => $flow->code, 'type' => 'payment_cancel']
            );
        }
    }

    private function cancelInvoicePayment(CashFlow $flow): void
    {
        $invoice = Invoice::query()
            ->where('code', $flow->reference_code)
            ->lockForUpdate()
            ->first();
        if (!$invoice || BusinessStatus::isCancelled($invoice->status)) {
            return;
        }

        $reversalAmount = min((float) $flow->amount, max(0.0, (float) $invoice->customer_paid));
        $invoice->customer_paid = (float) $invoice->customer_paid - $reversalAmount;
        $invoice->save();

        if ($invoice->customer_id && $reversalAmount >= 0.01) {
            app(CustomerDebtService::class)->recordAdjustment(
                (int) $invoice->customer_id,
                $reversalAmount,
                "Hủy phiếu thu {$flow->code} của hóa đơn {$invoice->code}",
                ['ref_code' => $invoice->code, 'type' => 'payment_cancel']
            );
        }
    }
}
