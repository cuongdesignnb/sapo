<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CashReceipt;
use App\Models\CashPayment;
use App\Models\Warehouse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CashLedgerController extends Controller
{
    public function index(Request $request)
    {
        try {
            $warehouseId = $request->get('warehouse_id');
            $dateFrom = $request->get('date_from', date('Y-m-01'));
            $dateeTo = $request->get('date_to', date('Y-m-d'));
            $type = $request->get('type'); // receipt, payment, all

            // Get receipts
            $receiptsQuery = CashReceipt::with(['receiptType', 'warehouse', 'creator'])
                                      ->where('status', 'approved');

            if ($warehouseId) {
                $receiptsQuery->where('warehouse_id', $warehouseId);
            }

            $receiptsQuery->whereBetween('receipt_date', [$dateFrom, $dateeTo]);

            // Get payments
            $paymentsQuery = CashPayment::with(['paymentType', 'warehouse', 'creator'])
                                       ->where('status', 'approved');

            if ($warehouseId) {
                $paymentsQuery->where('warehouse_id', $warehouseId);
            }

            $paymentsQuery->whereBetween('payment_date', [$dateFrom, $dateeTo]);

            $transactions = collect();

            if (in_array($type, ['receipt', 'all', null])) {
                $receipts = $receiptsQuery->get();
                foreach ($receipts as $receipt) {
                    // Load recipient
                    if ($receipt->recipient_type === 'customer') {
                        $recipient = \App\Models\Customer::find($receipt->recipient_id);
                    } else {
                        $recipient = \App\Models\Supplier::find($receipt->recipient_id);
                    }

                    $transactions->push([
                        'id' => $receipt->id,
                        'type' => 'receipt',
                        'code' => $receipt->code,
                        'date' => $receipt->receipt_date,
                        'amount' => $receipt->amount,
                        'note' => $receipt->note,
                        'recipient_type' => $receipt->recipient_type,
                        'recipient_name' => $recipient->name ?? '',
                        'voucher_type' => $receipt->receiptType->name,
                        'payment_method' => $receipt->payment_method,
                        'warehouse' => $receipt->warehouse->name,
                        'creator' => $receipt->creator->name,
                        'created_at' => $receipt->created_at,
                    ]);
                }
            }

            if (in_array($type, ['payment', 'all', null])) {
                $payments = $paymentsQuery->get();
                foreach ($payments as $payment) {
                    // Load recipient
                    if ($payment->recipient_type === 'customer') {
                        $recipient = \App\Models\Customer::find($payment->recipient_id);
                    } else {
                        $recipient = \App\Models\Supplier::find($payment->recipient_id);
                    }

                    $transactions->push([
                        'id' => $payment->id,
                        'type' => 'payment',
                        'code' => $payment->code,
                        'date' => $payment->payment_date,
                        'amount' => $payment->amount,
                        'note' => $payment->note,
                        'recipient_type' => $payment->recipient_type,
                        'recipient_name' => $recipient->name ?? '',
                        'voucher_type' => $payment->paymentType->name,
                        'payment_method' => $payment->payment_method,
                        'warehouse' => $payment->warehouse->name,
                        'creator' => $payment->creator->name,
                        'created_at' => $payment->created_at,
                    ]);
                }
            }

            // Sort by date
            $transactions = $transactions->sortBy('date')->values();

            // Calculate running balance
            $balance = 0;
            $transactions = $transactions->map(function($transaction) use (&$balance) {
                if ($transaction['type'] === 'receipt') {
                    $balance += $transaction['amount'];
                } else {
                    $balance -= $transaction['amount'];
                }
                $transaction['balance'] = $balance;
                return $transaction;
            });

            return response()->json([
                'success' => true,
                'data' => [
                    'transactions' => $transactions,
                    'summary' => [
                        'total_receipts' => $receiptsQuery->sum('amount'),
                        'total_payments' => $paymentsQuery->sum('amount'),
                        'net_balance' => $receiptsQuery->sum('amount') - $paymentsQuery->sum('amount'),
                        'receipts_count' => $receiptsQuery->count(),
                        'payments_count' => $paymentsQuery->count(),
                    ]
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Lỗi khi lấy sổ quỹ: ' . $e->getMessage()
            ], 500);
        }
    }

    public function summary(Request $request)
    {
        try {
            $warehouseId = $request->get('warehouse_id');
            $dateFrom = $request->get('date_from', date('Y-m-01'));
            $dateeTo = $request->get('date_to', date('Y-m-d'));

            $query = "
                SELECT 
                    'receipt' as type,
                    SUM(amount) as total_amount,
                    COUNT(*) as count
                FROM cash_receipts 
                WHERE status = 'approved' 
                AND receipt_date BETWEEN ? AND ?
                " . ($warehouseId ? "AND warehouse_id = ?" : "") . "
                UNION ALL
                SELECT 
                    'payment' as type,
                    SUM(amount) as total_amount,
                    COUNT(*) as count
                FROM cash_payments 
                WHERE status = 'approved' 
                AND payment_date BETWEEN ? AND ?
                " . ($warehouseId ? "AND warehouse_id = ?" : "");

            $params = [$dateFrom, $dateeTo];
            if ($warehouseId) {
                $params[] = $warehouseId;
            }
            $params = array_merge($params, [$dateFrom, $dateeTo]);
            if ($warehouseId) {
                $params[] = $warehouseId;
            }

            $results = DB::select($query, $params);

            $summary = [
                'total_receipts' => 0,
                'total_payments' => 0,
                'receipts_count' => 0,
                'payments_count' => 0,
                'net_balance' => 0,
            ];

            foreach ($results as $result) {
                if ($result->type === 'receipt') {
                    $summary['total_receipts'] = $result->total_amount ?? 0;
                    $summary['receipts_count'] = $result->count ?? 0;
                } else {
                    $summary['total_payments'] = $result->total_amount ?? 0;
                    $summary['payments_count'] = $result->count ?? 0;
                }
            }

            $summary['net_balance'] = $summary['total_receipts'] - $summary['total_payments'];

            return response()->json([
                'success' => true,
                'data' => $summary
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Lỗi khi lấy thống kê: ' . $e->getMessage()
            ], 500);
        }
    }

    public function export(Request $request)
    {
        try {
            // This can be extended to export Excel/PDF
            $data = $this->index($request)->getData()->data;

            return response()->json([
                'success' => true,
                'message' => 'Xuất dữ liệu thành công',
                'data' => $data
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Lỗi khi xuất dữ liệu: ' . $e->getMessage()
            ], 500);
        }
    }
}