<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CashReceipt;
use App\Models\CashReceiptType;
use App\Models\Customer;
use App\Models\Supplier;
use App\Services\CashVoucherImpactService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CashReceiptController extends Controller
{
    protected $impactService;

    public function __construct(CashVoucherImpactService $impactService)
    {
        $this->impactService = $impactService;
    }

    public function index(Request $request)
    {
        try {
            $query = CashReceipt::with(['receiptType', 'warehouse', 'creator', 'approver']);

            if ($request->has('status') && $request->status !== '') {
                $query->byStatus($request->status);
            }

            if ($request->has('recipient_type')) {
                $query->byRecipientType($request->recipient_type);
            }

            if ($request->has('warehouse_id')) {
                $query->where('warehouse_id', $request->warehouse_id);
            }

            if ($request->has('date_from')) {
                $query->where('receipt_date', '>=', $request->date_from);
            }

            if ($request->has('date_to')) {
                $query->where('receipt_date', '<=', $request->date_to);
            }

            if ($request->has('search')) {
                $search = $request->search;
                $query->where(function($q) use ($search) {
                    $q->where('code', 'like', "%{$search}%")
                      ->orWhere('note', 'like', "%{$search}%")
                      ->orWhere('reference_number', 'like', "%{$search}%");
                });
            }

            $receipts = $query->orderBy('created_at', 'desc')
                            ->paginate($request->get('per_page', 15));

            // Load recipient data manually
            foreach ($receipts as $receipt) {
                if ($receipt->recipient_type === 'customer') {
                    $customer = Customer::find($receipt->recipient_id);
                    $receipt->recipient = $customer ? [
                        'id' => $customer->id,
                        'name' => $customer->name,
                        'phone' => $customer->phone ?? '',
                        'total_debt' => $customer->total_debt ?? 0
                    ] : null;
                } else {
                    $supplier = Supplier::find($receipt->recipient_id);
                    $receipt->recipient = $supplier ? [
                        'id' => $supplier->id,
                        'name' => $supplier->name,
                        'phone' => $supplier->phone ?? '',
                        'total_debt' => $supplier->total_debt ?? 0
                    ] : null;
                }
            }

            Log::info('📋 Cash receipts loaded from database', [
                'total' => $receipts->total(),
                'current_page' => $receipts->currentPage()
            ]);

            return response()->json([
                'success' => true,
                'data' => $receipts
            ]);

        } catch (\Exception $e) {
            Log::error('❌ Error loading cash receipts', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Lỗi khi lấy danh sách phiếu thu: ' . $e->getMessage()
            ], 500);
        }
    }

    public function store(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'type_id' => 'required|exists:cash_receipt_types,id',
                'recipient_type' => 'required|in:customer,supplier',
                'recipient_id' => 'required|integer',
                'warehouse_id' => 'required|exists:warehouses,id',
                'amount' => 'required|numeric|min:0',
                'note' => 'nullable|string',
                'payment_method' => 'required|in:cash,transfer',
                'reference_number' => 'nullable|string|max:255',
                'receipt_date' => 'required|date',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Dữ liệu không hợp lệ',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Validate recipient exists
            if ($request->recipient_type === 'customer') {
                $recipient = Customer::find($request->recipient_id);
            } else {
                $recipient = Supplier::find($request->recipient_id);
            }

            if (!$recipient) {
                return response()->json([
                    'success' => false,
                    'message' => 'Không tìm thấy đối tượng được chọn'
                ], 404);
            }

            $receiptData = array_merge($request->all(), [
                'created_by' => auth()->id(),
                'status' => 'draft'
            ]);

            $receipt = CashReceipt::create($receiptData);
            $receipt->load(['receiptType', 'warehouse', 'creator']);

            Log::info('✅ Cash receipt created', [
                'id' => $receipt->id,
                'code' => $receipt->code,
                'amount' => $receipt->amount,
                'recipient_type' => $receipt->recipient_type,
                'recipient_id' => $receipt->recipient_id
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Tạo phiếu thu thành công',
                'data' => $receipt
            ], 201);

        } catch (\Exception $e) {
            Log::error('❌ Error creating cash receipt', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Lỗi khi tạo phiếu thu: ' . $e->getMessage()
            ], 500);
        }
    }

    public function show($id)
    {
        try {
            $receipt = CashReceipt::with(['receiptType', 'warehouse', 'creator', 'approver', 'transactions'])
                                 ->findOrFail($id);

            // Load recipient
            if ($receipt->recipient_type === 'customer') {
                $receipt->recipient = Customer::find($receipt->recipient_id);
            } else {
                $receipt->recipient = Supplier::find($receipt->recipient_id);
            }

            return response()->json([
                'success' => true,
                'data' => $receipt
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Không tìm thấy phiếu thu'
            ], 404);
        }
    }

    public function update(Request $request, $id)
    {
        try {
            $receipt = CashReceipt::findOrFail($id);

            if ($receipt->status !== 'draft') {
                return response()->json([
                    'success' => false,
                    'message' => 'Chỉ có thể sửa phiếu ở trạng thái nháp'
                ], 400);
            }

            $validator = Validator::make($request->all(), [
                'type_id' => 'required|exists:cash_receipt_types,id',
                'recipient_type' => 'required|in:customer,supplier',
                'recipient_id' => 'required|integer',
                'warehouse_id' => 'required|exists:warehouses,id',
                'amount' => 'required|numeric|min:0',
                'note' => 'nullable|string',
                'payment_method' => 'required|in:cash,transfer',
                'reference_number' => 'nullable|string|max:255',
                'receipt_date' => 'required|date',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Dữ liệu không hợp lệ',
                    'errors' => $validator->errors()
                ], 422);
            }

            $receipt->update($request->all());
            $receipt->load(['receiptType', 'warehouse', 'creator']);

            return response()->json([
                'success' => true,
                'message' => 'Cập nhật phiếu thu thành công',
                'data' => $receipt
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Lỗi khi cập nhật phiếu thu: ' . $e->getMessage()
            ], 500);
        }
    }

    public function submitForApproval($id)
    {
        try {
            $receipt = CashReceipt::findOrFail($id);

            if ($receipt->status !== 'draft') {
                return response()->json([
                    'success' => false,
                    'message' => 'Chỉ có thể gửi duyệt phiếu ở trạng thái nháp'
                ], 400);
            }

            $receipt->update(['status' => 'pending']);

            return response()->json([
                'success' => true,
                'message' => 'Gửi phiếu thu để duyệt thành công'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Lỗi khi gửi duyệt: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * 🎯 QUAN TRỌNG: Approve receipt và apply impact vào database
     */
    public function approve($id)
    {
        try {
            $receipt = CashReceipt::with('receiptType')->findOrFail($id);

            if ($receipt->status !== 'pending') {
                return response()->json([
                    'success' => false,
                    'message' => 'Chỉ có thể duyệt phiếu ở trạng thái chờ duyệt'
                ], 400);
            }

            Log::info('🎯 Starting approval process', [
                'receipt_id' => $receipt->id,
                'code' => $receipt->code,
                'amount' => $receipt->amount,
                'recipient_type' => $receipt->recipient_type,
                'recipient_id' => $receipt->recipient_id,
                'impact_applied' => $receipt->impact_applied
            ]);

            DB::beginTransaction();

            // Update receipt status
            $receipt->update([
                'status' => 'approved',
                'approved_by' => auth()->id(),
                'approved_at' => now()
            ]);

            // 🎯 APPLY IMPACT - Quan trọng!
            if (!$receipt->impact_applied) {
                Log::info('📉 Applying impact to customer debt', [
                    'receipt_id' => $receipt->id,
                    'customer_id' => $receipt->recipient_id
                ]);

                $this->impactService->applyReceiptImpact($receipt);
                
                Log::info('✅ Impact applied successfully', [
                    'receipt_id' => $receipt->id
                ]);
            } else {
                Log::warning('⚠️ Impact already applied', [
                    'receipt_id' => $receipt->id
                ]);
            }

            DB::commit();

            // Reload fresh data
            $receipt->load(['receiptType', 'warehouse', 'creator', 'approver']);

            return response()->json([
                'success' => true,
                'message' => 'Duyệt phiếu thu thành công! Công nợ khách hàng đã được cập nhật.',
                'data' => $receipt
            ]);

        } catch (\Exception $e) {
            DB::rollback();
            
            Log::error('❌ Error approving receipt', [
                'receipt_id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Lỗi khi duyệt phiếu thu: ' . $e->getMessage()
            ], 500);
        }
    }

    public function cancel($id)
    {
        try {
            $receipt = CashReceipt::findOrFail($id);

            if (!in_array($receipt->status, ['draft', 'pending'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Không thể hủy phiếu ở trạng thái này'
                ], 400);
            }

            DB::beginTransaction();

            // If impact was applied, reverse it
            if ($receipt->impact_applied) {
                $this->impactService->reverseReceiptImpact($receipt);
            }

            $receipt->update(['status' => 'cancelled']);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Hủy phiếu thu thành công'
            ]);

        } catch (\Exception $e) {
            DB::rollback();
            return response()->json([
                'success' => false,
                'message' => 'Lỗi khi hủy phiếu thu: ' . $e->getMessage()
            ], 500);
        }
    }

    public function getRecipients(Request $request)
    {
        try {
            $type = $request->get('type'); // customer or supplier
            
            if (!in_array($type, ['customer', 'supplier'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Loại đối tượng không hợp lệ. Phải là "customer" hoặc "supplier"'
                ], 422);
            }

            if ($type === 'customer') {
                $recipients = Customer::select('id', 'code', 'name', 'phone', 'total_debt')
                                    ->where('status', 'active')
                                    ->orderBy('name')
                                    ->get();
            } else {
                $recipients = Supplier::select('id', 'code', 'name', 'phone', 'total_debt')
                                    ->where('status', 'active')
                                    ->orderBy('name')
                                    ->get();
            }

            \Log::info('CashReceiptController@getRecipients: Loaded recipients', [
                'type' => $type,
                'count' => count($recipients)
            ]);

            return response()->json([
                'success' => true,
                'data' => $recipients
            ]);

        } catch (\Exception $e) {
            \Log::error('CashReceiptController@getRecipients: Error', [
                'error' => $e->getMessage()
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Lỗi khi lấy danh sách: ' . $e->getMessage()
            ], 500);
        }
    }
    public function print($id)
    {
        try {
            $receipt = CashReceipt::with([
                'receiptType', 
                'warehouse', 
                'creator', 
                'approver', 
                'transactions'
            ])->findOrFail($id);

            // Load recipient based on type
            if ($receipt->recipient_type === 'customer') {
                $receipt->recipient = Customer::find($receipt->recipient_id);
            } else {
                $receipt->recipient = Supplier::find($receipt->recipient_id);
            }

            // Chỉ cho phép in phiếu đã được duyệt
            if ($receipt->status !== 'approved') {
                return response()->json([
                    'success' => false,
                    'message' => 'Chỉ có thể in phiếu đã được duyệt'
                ], 400);
            }

            return view('cash-receipts.print', compact('receipt'));

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Không tìm thấy phiếu thu để in'
            ], 404);
        }
    }
}