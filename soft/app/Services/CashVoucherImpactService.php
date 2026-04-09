<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Supplier;
use App\Models\CustomerDebt;
use App\Models\SupplierDebt;
use App\Models\CashReceiptTransaction;
use App\Models\CashPaymentTransaction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CashVoucherImpactService
{
    public function applyReceiptImpact($receipt)
    {
        if ($receipt->impact_applied) {
            throw new \Exception('Impact đã được áp dụng cho phiếu này');
        }

        DB::beginTransaction();

        try {
            $type = $receipt->receiptType;
            
            switch($type->impact_type) {
                case 'debt':
                    $this->handleReceiptDebtImpact($receipt, $type);
                    break;
                case 'revenue':
                    $this->handleReceiptRevenueImpact($receipt, $type);
                    break;
                case 'advance':
                    $this->handleReceiptAdvanceImpact($receipt, $type);
                    break;
            }

            $receipt->update(['impact_applied' => true]);
            
            DB::commit();
            return true;

        } catch (\Exception $e) {
            DB::rollback();
            throw $e;
        }
    }

    public function applyPaymentImpact($payment)
    {
        if ($payment->impact_applied) {
            throw new \Exception('Impact đã được áp dụng cho phiếu này');
        }

        DB::beginTransaction();

        try {
            $type = $payment->paymentType;
            
            switch($type->impact_type) {
                case 'debt':
                    $this->handlePaymentDebtImpact($payment, $type);
                    break;
                case 'expense':
                    $this->handlePaymentExpenseImpact($payment, $type);
                    break;
                case 'advance':
                    $this->handlePaymentAdvanceImpact($payment, $type);
                    break;
            }

            $payment->update(['impact_applied' => true]);
            
            DB::commit();
            return true;

        } catch (\Exception $e) {
            DB::rollback();
            throw $e;
        }
    }

    // ==========================================
    // RECEIPT IMPACTS (Phiếu thu)
    // ==========================================

    private function handleReceiptDebtImpact($receipt, $type)
    {
        if ($receipt->recipient_type === 'customer' && $type->impact_action === 'decrease') {
            // Thu nợ khách hàng → giảm nợ phải thu
            $customer = Customer::find($receipt->recipient_id);
            if (!$customer) {
                throw new \Exception('Không tìm thấy khách hàng');
            }

            if ($customer->total_debt < $receipt->amount) {
                throw new \Exception('Số tiền thu vượt quá công nợ hiện tại');
            }

            $oldDebt = $customer->total_debt;

            // Dùng method chuẩn có row locking
            CustomerDebt::createPayment(
                customerId: $customer->id,
                amount: $receipt->amount,
                refCode: $receipt->code,
                note: 'Thu nợ từ phiếu thu ' . $receipt->code
            );

            // Log transaction
            CashReceiptTransaction::create([
                'receipt_id' => $receipt->id,
                'target_model' => 'Customer',
                'target_id' => $customer->id,
                'field_affected' => 'total_debt',
                'old_value' => $oldDebt,
                'new_value' => $oldDebt - $receipt->amount,
                'change_amount' => -$receipt->amount,
                'transaction_type' => 'debt_decrease',
            ]);

            Log::info('✅ Thu nợ KH thành công', [
                'customer_id' => $customer->id,
                'receipt_code' => $receipt->code,
                'amount' => $receipt->amount,
                'old_debt' => $oldDebt,
                'new_debt' => $oldDebt - $receipt->amount,
            ]);
        }

        if ($receipt->recipient_type === 'supplier' && $type->impact_action === 'decrease') {
            // Thu tiền từ NCC (NCC hoàn trả tiền dư) → giảm nợ phải trả
            $supplier = Supplier::find($receipt->recipient_id);
            if (!$supplier) {
                throw new \Exception('Không tìm thấy nhà cung cấp');
            }

            $oldDebt = $supplier->total_debt;

            SupplierDebt::createPayment(
                supplierId: $supplier->id,
                amount: $receipt->amount,
                refCode: $receipt->code,
                note: 'Thu từ NCC - phiếu thu ' . $receipt->code
            );

            CashReceiptTransaction::create([
                'receipt_id' => $receipt->id,
                'target_model' => 'Supplier',
                'target_id' => $supplier->id,
                'field_affected' => 'total_debt',
                'old_value' => $oldDebt,
                'new_value' => $oldDebt - $receipt->amount,
                'change_amount' => -$receipt->amount,
                'transaction_type' => 'debt_decrease',
            ]);

            Log::info('✅ Thu tiền NCC thành công', [
                'supplier_id' => $supplier->id,
                'receipt_code' => $receipt->code,
                'amount' => $receipt->amount,
            ]);
        }
    }

    private function handleReceiptRevenueImpact($receipt, $type)
    {
        CashReceiptTransaction::create([
            'receipt_id' => $receipt->id,
            'target_model' => ucfirst($receipt->recipient_type),
            'target_id' => $receipt->recipient_id,
            'field_affected' => 'revenue',
            'old_value' => 0,
            'new_value' => $receipt->amount,
            'change_amount' => $receipt->amount,
            'transaction_type' => 'revenue_increase',
        ]);
    }

    private function handleReceiptAdvanceImpact($receipt, $type)
    {
        CashReceiptTransaction::create([
            'receipt_id' => $receipt->id,
            'target_model' => 'Customer',
            'target_id' => $receipt->recipient_id,
            'field_affected' => 'advance',
            'old_value' => 0,
            'new_value' => $receipt->amount,
            'change_amount' => $receipt->amount,
            'transaction_type' => 'advance_increase',
        ]);
    }

    // ==========================================
    // PAYMENT IMPACTS (Phiếu chi)
    // ==========================================

    private function handlePaymentDebtImpact($payment, $type)
    {
        if ($payment->recipient_type === 'supplier' && $type->impact_action === 'decrease') {
            // Trả nợ nhà cung cấp → giảm nợ phải trả
            $supplier = Supplier::find($payment->recipient_id);
            if (!$supplier) {
                throw new \Exception('Không tìm thấy nhà cung cấp');
            }

            if ($supplier->total_debt < $payment->amount) {
                throw new \Exception('Số tiền trả vượt quá công nợ hiện tại');
            }

            $oldDebt = $supplier->total_debt;

            // Dùng method chuẩn có row locking
            SupplierDebt::createPayment(
                supplierId: $supplier->id,
                amount: $payment->amount,
                refCode: $payment->code,
                note: 'Trả nợ từ phiếu chi ' . $payment->code
            );

            CashPaymentTransaction::create([
                'payment_id' => $payment->id,
                'target_model' => 'Supplier',
                'target_id' => $supplier->id,
                'field_affected' => 'total_debt',
                'old_value' => $oldDebt,
                'new_value' => $oldDebt - $payment->amount,
                'change_amount' => -$payment->amount,
                'transaction_type' => 'debt_decrease',
            ]);

            Log::info('✅ Trả nợ NCC thành công', [
                'supplier_id' => $supplier->id,
                'payment_code' => $payment->code,
                'amount' => $payment->amount,
                'old_debt' => $oldDebt,
                'new_debt' => $oldDebt - $payment->amount,
            ]);
        }

        if ($payment->recipient_type === 'customer' && $type->impact_action === 'decrease') {
            // Chi tiền cho KH (hoàn tiền KH) → giảm nợ phải thu
            $customer = Customer::find($payment->recipient_id);
            if (!$customer) {
                throw new \Exception('Không tìm thấy khách hàng');
            }

            $oldDebt = $customer->total_debt;

            CustomerDebt::createPayment(
                customerId: $customer->id,
                amount: $payment->amount,
                refCode: $payment->code,
                note: 'Hoàn tiền KH từ phiếu chi ' . $payment->code
            );

            CashPaymentTransaction::create([
                'payment_id' => $payment->id,
                'target_model' => 'Customer',
                'target_id' => $customer->id,
                'field_affected' => 'total_debt',
                'old_value' => $oldDebt,
                'new_value' => $oldDebt - $payment->amount,
                'change_amount' => -$payment->amount,
                'transaction_type' => 'debt_decrease',
            ]);

            Log::info('✅ Hoàn tiền KH thành công', [
                'customer_id' => $customer->id,
                'payment_code' => $payment->code,
                'amount' => $payment->amount,
            ]);
        }
    }

    private function handlePaymentExpenseImpact($payment, $type)
    {
        CashPaymentTransaction::create([
            'payment_id' => $payment->id,
            'target_model' => ucfirst($payment->recipient_type),
            'target_id' => $payment->recipient_id,
            'field_affected' => 'expense',
            'old_value' => 0,
            'new_value' => $payment->amount,
            'change_amount' => $payment->amount,
            'transaction_type' => 'expense_increase',
        ]);
    }

    private function handlePaymentAdvanceImpact($payment, $type)
    {
        CashPaymentTransaction::create([
            'payment_id' => $payment->id,
            'target_model' => ucfirst($payment->recipient_type),
            'target_id' => $payment->recipient_id,
            'field_affected' => 'advance',
            'old_value' => 0,
            'new_value' => $payment->amount,
            'change_amount' => $payment->amount,
            'transaction_type' => 'advance_increase',
        ]);
    }

    // ==========================================
    // REVERSE IMPACTS (Hoàn tác)
    // ==========================================

    public function reverseReceiptImpact($receipt)
    {
        if (!$receipt->impact_applied) {
            throw new \Exception('Phiếu chưa được áp dụng impact');
        }

        DB::beginTransaction();

        try {
            $type = $receipt->receiptType;
            
            if ($type->impact_type === 'debt' && $receipt->recipient_type === 'customer') {
                // Hoàn tác thu nợ KH → tăng lại nợ
                CustomerDebt::createAdjustment(
                    customerId: $receipt->recipient_id,
                    amount: $receipt->amount,
                    note: 'Hoàn tác phiếu thu ' . $receipt->code,
                    refCode: $receipt->code . '_REVERSE'
                );
            }

            if ($type->impact_type === 'debt' && $receipt->recipient_type === 'supplier') {
                // Hoàn tác thu tiền NCC → tăng lại nợ
                SupplierDebt::createAdjustment(
                    supplierId: $receipt->recipient_id,
                    amount: $receipt->amount,
                    note: 'Hoàn tác phiếu thu ' . $receipt->code,
                    refCode: $receipt->code . '_REVERSE'
                );
            }

            $receipt->update(['impact_applied' => false]);
            
            DB::commit();
            return true;

        } catch (\Exception $e) {
            DB::rollback();
            throw $e;
        }
    }

    public function reversePaymentImpact($payment)
    {
        if (!$payment->impact_applied) {
            throw new \Exception('Phiếu chưa được áp dụng impact');
        }

        DB::beginTransaction();

        try {
            $type = $payment->paymentType;
            
            if ($type->impact_type === 'debt' && $payment->recipient_type === 'supplier') {
                // Hoàn tác trả nợ NCC → tăng lại nợ
                SupplierDebt::createAdjustment(
                    supplierId: $payment->recipient_id,
                    amount: $payment->amount,
                    note: 'Hoàn tác phiếu chi ' . $payment->code,
                    refCode: $payment->code . '_REVERSE'
                );
            }

            if ($type->impact_type === 'debt' && $payment->recipient_type === 'customer') {
                // Hoàn tác hoàn tiền KH → tăng lại nợ
                CustomerDebt::createAdjustment(
                    customerId: $payment->recipient_id,
                    amount: $payment->amount,
                    note: 'Hoàn tác phiếu chi ' . $payment->code,
                    refCode: $payment->code . '_REVERSE'
                );
            }

            $payment->update(['impact_applied' => false]);
            
            DB::commit();
            return true;

        } catch (\Exception $e) {
            DB::rollback();
            throw $e;
        }
    }
}