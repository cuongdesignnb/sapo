<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Supplier;
use App\Models\CustomerDebt;
use App\Models\SupplierDebt;
use App\Models\CashReceiptTransaction;
use App\Models\CashPaymentTransaction;
use Illuminate\Support\Facades\DB;

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

    private function handleReceiptDebtImpact($receipt, $type)
    {
        if ($receipt->recipient_type === 'customer' && $type->impact_action === 'decrease') {
            // Thu nợ khách hàng
            $customer = Customer::find($receipt->recipient_id);
            if (!$customer) {
                throw new \Exception('Không tìm thấy khách hàng');
            }

            if ($customer->total_debt < $receipt->amount) {
                throw new \Exception('Số tiền thu vượt quá công nợ hiện tại');
            }

            $oldDebt = $customer->total_debt;
            $newDebt = $oldDebt - $receipt->amount;
            
            $customer->update(['total_debt' => $newDebt]);

            // Tạo customer debt record
            CustomerDebt::create([
                'customer_id' => $customer->id,
                'ref_code' => $receipt->code,
                'amount' => -$receipt->amount,
                'debt_total' => $newDebt,
                'note' => 'Thu nợ từ phiếu ' . $receipt->code,
                'created_by' => $receipt->created_by,
                'recorded_at' => $receipt->receipt_date,
            ]);

            // Tạo transaction log
            CashReceiptTransaction::create([
                'receipt_id' => $receipt->id,
                'target_model' => 'Customer',
                'target_id' => $customer->id,
                'field_affected' => 'total_debt',
                'old_value' => $oldDebt,
                'new_value' => $newDebt,
                'change_amount' => -$receipt->amount,
                'transaction_type' => 'debt_decrease',
            ]);
        }
    }

    private function handleReceiptRevenueImpact($receipt, $type)
    {
        // Thu từ NCC hoặc thu nhập khác - có thể extend thêm logic tính revenue
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
        // Thu đặt cọc từ khách hàng - có thể extend thêm logic quản lý tạm ứng
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

    private function handlePaymentDebtImpact($payment, $type)
    {
        if ($payment->recipient_type === 'supplier' && $type->impact_action === 'decrease') {
            // Trả nợ nhà cung cấp
            $supplier = Supplier::find($payment->recipient_id);
            if (!$supplier) {
                throw new \Exception('Không tìm thấy nhà cung cấp');
            }

            if ($supplier->total_debt < $payment->amount) {
                throw new \Exception('Số tiền trả vượt quá công nợ hiện tại');
            }

            $oldDebt = $supplier->total_debt;
            $newDebt = $oldDebt - $payment->amount;
            
            $supplier->update(['total_debt' => $newDebt]);

            // Tạo supplier debt record
            SupplierDebt::create([
                'supplier_id' => $supplier->id,
                'ref_code' => $payment->code,
                'amount' => -$payment->amount,
                'debt_total' => $newDebt,
                'type' => 'payment',
                'note' => 'Trả nợ từ phiếu ' . $payment->code,
                'created_by' => $payment->created_by,
                'recorded_at' => $payment->payment_date,
            ]);

            // Tạo transaction log
            CashPaymentTransaction::create([
                'payment_id' => $payment->id,
                'target_model' => 'Supplier',
                'target_id' => $supplier->id,
                'field_affected' => 'total_debt',
                'old_value' => $oldDebt,
                'new_value' => $newDebt,
                'change_amount' => -$payment->amount,
                'transaction_type' => 'debt_decrease',
            ]);
        }
    }

    private function handlePaymentExpenseImpact($payment, $type)
    {
        // Chi phí hoạt động - có thể extend thêm logic tính expense
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
        // Tạm ứng nhân viên - có thể extend thêm logic quản lý tạm ứng
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

    public function reverseReceiptImpact($receipt)
    {
        if (!$receipt->impact_applied) {
            throw new \Exception('Phiếu chưa được áp dụng impact');
        }

        DB::beginTransaction();

        try {
            $type = $receipt->receiptType;
            
            if ($type->impact_type === 'debt' && $receipt->recipient_type === 'customer') {
                $customer = Customer::find($receipt->recipient_id);
                $oldDebt = $customer->total_debt;
                $newDebt = $oldDebt + $receipt->amount;
                
                $customer->update(['total_debt' => $newDebt]);

                CustomerDebt::create([
                    'customer_id' => $customer->id,
                    'ref_code' => $receipt->code . '_REVERSE',
                    'amount' => $receipt->amount,
                    'debt_total' => $newDebt,
                    'note' => 'Hoàn tác phiếu ' . $receipt->code,
                    'created_by' => auth()->id(),
                    'recorded_at' => now(),
                ]);
            }

            if ($type->impact_type === 'debt' && $receipt->recipient_type === 'supplier') {
                $supplier = Supplier::find($receipt->recipient_id);
                $oldDebt = $supplier->total_debt;
                $newDebt = $oldDebt + $receipt->amount;
                
                $supplier->update(['total_debt' => $newDebt]);

                SupplierDebt::create([
                    'supplier_id' => $supplier->id,
                    'ref_code' => $receipt->code . '_REVERSE',
                    'amount' => $receipt->amount,
                    'debt_total' => $newDebt,
                    'type' => 'adjustment',
                    'note' => 'Hoàn tác phiếu ' . $receipt->code,
                    'created_by' => auth()->id(),
                    'recorded_at' => now(),
                ]);
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
                $supplier = Supplier::find($payment->recipient_id);
                $oldDebt = $supplier->total_debt;
                $newDebt = $oldDebt + $payment->amount;
                
                $supplier->update(['total_debt' => $newDebt]);

                SupplierDebt::create([
                    'supplier_id' => $supplier->id,
                    'ref_code' => $payment->code . '_REVERSE',
                    'amount' => $payment->amount,
                    'debt_total' => $newDebt,
                    'type' => 'adjustment',
                    'note' => 'Hoàn tác phiếu ' . $payment->code,
                    'created_by' => auth()->id(),
                    'recorded_at' => now(),
                ]);
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