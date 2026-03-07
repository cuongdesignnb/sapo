<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CashPayment;
use App\Models\CashPaymentType;
use App\Models\Customer;
use App\Models\Supplier;
use App\Models\Warehouse;
use App\Models\User;
use App\Services\CashVoucherImpactService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

class CashPaymentController extends Controller
{
    protected $impactService;

    public function __construct(CashVoucherImpactService $impactService = null)
    {
        // Make CashVoucherImpactService optional to avoid injection issues
        $this->impactService = $impactService ?: app(CashVoucherImpactService::class);
    }

    /**
     * Display a listing of cash payments
     */
    public function index(Request $request)
    {
        try {
            Log::info('CashPaymentController@index: Starting with params', $request->all());

            $query = CashPayment::query();

            // Apply filters
            if ($request->filled('status')) {
                $query->where('status', $request->status);
                Log::debug('Applied status filter', ['status' => $request->status]);
            }

            if ($request->filled('recipient_type')) {
                $query->where('recipient_type', $request->recipient_type);
                Log::debug('Applied recipient_type filter', ['type' => $request->recipient_type]);
            }

            if ($request->filled('warehouse_id')) {
                $query->where('warehouse_id', $request->warehouse_id);
                Log::debug('Applied warehouse_id filter', ['warehouse_id' => $request->warehouse_id]);
            }

            if ($request->filled('date_from')) {
                $query->where('payment_date', '>=', $request->date_from);
                Log::debug('Applied date_from filter', ['date_from' => $request->date_from]);
            }

            if ($request->filled('date_to')) {
                $query->where('payment_date', '<=', $request->date_to);
                Log::debug('Applied date_to filter', ['date_to' => $request->date_to]);
            }

            if ($request->filled('search')) {
                $search = $request->search;
                $query->where(function($q) use ($search) {
                    $q->where('code', 'like', "%{$search}%")
                      ->orWhere('note', 'like', "%{$search}%")
                      ->orWhere('reference_number', 'like', "%{$search}%");
                });
                Log::debug('Applied search filter', ['search' => $search]);
            }

            // Get paginated results
            $perPage = $request->get('per_page', 15);
            $payments = $query->orderBy('created_at', 'desc')->paginate($perPage);

            Log::info('Found payments', ['count' => $payments->count(), 'total' => $payments->total()]);

            // Enrich each payment with related data
            foreach ($payments as $payment) {
                $this->enrichPaymentData($payment);
            }

            return response()->json([
                'success' => true,
                'data' => $payments,
                'message' => 'Danh sách phiếu chi được tải thành công'
            ]);

        } catch (\Exception $e) {
            Log::error('CashPaymentController@index: Error loading payments', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Lỗi khi tải danh sách phiếu chi: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Store a newly created payment
     */
    /**
 * Store a newly created payment
 * 🆕 Hỗ trợ tạo với trạng thái "approved" để duyệt luôn
 */
public function store(Request $request)
{
    Log::info('CashPaymentController@store: Creating new payment', $request->all());

    $validator = Validator::make($request->all(), [
        'type_id' => 'required|exists:cash_payment_types,id',
        'recipient_type' => 'required|in:customer,supplier',
        'recipient_id' => 'required|integer|min:1',
        'warehouse_id' => 'required|exists:warehouses,id',
        'amount' => 'required|numeric|min:0.01|max:999999999.99',
        'payment_method' => 'required|in:cash,transfer',
        'payment_date' => 'required|date|before_or_equal:today',
        'status' => 'required|in:draft,pending,approved', // 🆕 Validate status
        'note' => 'nullable|string|max:1000',
        'reference_number' => 'nullable|string|max:255',
    ], [
        'type_id.required' => 'Vui lòng chọn loại phiếu chi',
        'type_id.exists' => 'Loại phiếu chi không hợp lệ',
        'recipient_type.required' => 'Vui lòng chọn loại người nhận',
        'recipient_type.in' => 'Loại người nhận không hợp lệ',
        'recipient_id.required' => 'Vui lòng chọn người nhận',
        'recipient_id.integer' => 'Người nhận không hợp lệ',
        'warehouse_id.required' => 'Vui lòng chọn chi nhánh',
        'warehouse_id.exists' => 'Chi nhánh không tồn tại',
        'amount.required' => 'Vui lòng nhập số tiền',
        'amount.numeric' => 'Số tiền phải là số',
        'amount.min' => 'Số tiền phải lớn hơn 0',
        'amount.max' => 'Số tiền quá lớn',
        'payment_method.required' => 'Vui lòng chọn hình thức thanh toán',
        'payment_method.in' => 'Hình thức thanh toán không hợp lệ',
        'payment_date.required' => 'Vui lòng chọn ngày thanh toán',
        'payment_date.date' => 'Ngày thanh toán không hợp lệ',
        'payment_date.before_or_equal' => 'Ngày thanh toán không được là tương lai',
        'status.required' => 'Vui lòng chọn trạng thái',
        'status.in' => 'Trạng thái không hợp lệ',
        'note.max' => 'Ghi chú không được quá 1000 ký tự',
        'reference_number.max' => 'Mã tham chiếu không được quá 255 ký tự',
    ]);

    if ($validator->fails()) {
        Log::warning('CashPaymentController@store: Validation failed', $validator->errors()->toArray());
        return response()->json([
            'success' => false,
            'message' => 'Dữ liệu không hợp lệ',
            'errors' => $validator->errors()
        ], 422);
    }

    try {
        DB::beginTransaction();

        // Validate recipient exists
        $recipient = $this->validateRecipient($request->recipient_type, $request->recipient_id);
        if (!$recipient) {
            return response()->json([
                'success' => false,
                'message' => 'Người nhận không tồn tại hoặc không hoạt động'
            ], 422);
        }

        // Validate payment type
        $paymentType = CashPaymentType::where('id', $request->type_id)
                                    ->where('is_active', true)
                                    ->first();
        if (!$paymentType) {
            return response()->json([
                'success' => false,
                'message' => 'Loại phiếu chi không hoạt động'
            ], 422);
        }

        // Prepare payment data
        $paymentData = [
            'type_id' => $request->type_id,
            'recipient_type' => $request->recipient_type,
            'recipient_id' => $request->recipient_id,
            'warehouse_id' => $request->warehouse_id,
            'amount' => $request->amount,
            'payment_method' => $request->payment_method,
            'payment_date' => $request->payment_date,
            'note' => $request->note,
            'reference_number' => $request->reference_number,
            'status' => $request->status, // 🆕 Use requested status
            'created_by' => auth()->id(),
        ];

        // 🆕 If creating with approved status, set approval fields
        if ($request->status === 'approved') {
            $paymentData['approved_by'] = auth()->id();
            $paymentData['approved_at'] = now();
            
            Log::info('🚀 Creating payment with immediate approval', [
                'amount' => $request->amount,
                'recipient_type' => $request->recipient_type,
                'recipient_id' => $request->recipient_id,
                'approved_by' => auth()->id()
            ]);
        }

        // Create payment
        $payment = CashPayment::create($paymentData);
        
        Log::info('✅ Payment created', [
            'id' => $payment->id, 
            'code' => $payment->code,
            'status' => $payment->status
        ]);

        // 🆕 Apply impact immediately if approved
        if ($payment->status === 'approved') {
            Log::info('⚡ Applying immediate financial impact');
            
            try {
                // Ensure we have the impact service
                if (!$this->impactService) {
                    $this->impactService = app(CashVoucherImpactService::class);
                }
                
                $this->impactService->applyPaymentImpact($payment);
                
                Log::info('✅ Immediate financial impact applied successfully', [
                    'payment_id' => $payment->id,
                    'impact_applied' => true
                ]);
                
            } catch (\Exception $e) {
                Log::error('❌ Failed to apply immediate financial impact', [
                    'payment_id' => $payment->id,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
                
                // Rollback and return error
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => 'Lỗi khi áp dụng tác động tài chính: ' . $e->getMessage()
                ], 500);
            }
        }

        // Enrich with related data for response
        $this->enrichPaymentData($payment);

        DB::commit();

        // 🆕 Success message based on status
        $successMessage = match($payment->status) {
            'draft' => 'Tạo phiếu chi thành công (trạng thái nháp)',
            'pending' => 'Tạo phiếu chi thành công và đã gửi duyệt',
            'approved' => 'Tạo và duyệt phiếu chi thành công! Đã tác động đến công nợ.',
            default => 'Tạo phiếu chi thành công'
        };

        return response()->json([
            'success' => true,
            'message' => $successMessage,
            'data' => $payment
        ], 201);

    } catch (\Exception $e) {
        DB::rollBack();
        Log::error('CashPaymentController@store: Error creating payment', [
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ]);

        return response()->json([
            'success' => false,
            'message' => 'Lỗi khi tạo phiếu chi: ' . $e->getMessage()
        ], 500);
    }
}

    /**
     * Display the specified payment
     */
    public function show($id)
    {
        try {
            Log::info('CashPaymentController@show: Getting payment details', ['id' => $id]);

            $payment = CashPayment::findOrFail($id);
            $this->enrichPaymentData($payment);

            return response()->json([
                'success' => true,
                'data' => $payment
            ]);

        } catch (\Exception $e) {
            Log::error('CashPaymentController@show: Error getting payment details', [
                'id' => $id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Lỗi khi lấy chi tiết phiếu chi: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update the specified payment
     */
    public function update(Request $request, $id)
    {
        Log::info('CashPaymentController@update: Updating payment', ['id' => $id, 'data' => $request->all()]);

        $validator = Validator::make($request->all(), [
            'type_id' => 'required|exists:cash_payment_types,id',
            'recipient_type' => 'required|in:customer,supplier',
            'recipient_id' => 'required|integer|min:1',
            'warehouse_id' => 'required|exists:warehouses,id',
            'amount' => 'required|numeric|min:0.01|max:999999999.99',
            'payment_method' => 'required|in:cash,transfer',
            'payment_date' => 'required|date|before_or_equal:today',
            'note' => 'nullable|string|max:1000',
            'reference_number' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Dữ liệu không hợp lệ',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $payment = CashPayment::findOrFail($id);

            // Check if payment can be updated
            if (!in_array($payment->status, ['draft'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Không thể sửa phiếu chi đã được duyệt hoặc hủy'
                ], 422);
            }

            DB::beginTransaction();

            // Validate recipient exists
            $recipient = $this->validateRecipient($request->recipient_type, $request->recipient_id);
            if (!$recipient) {
                return response()->json([
                    'success' => false,
                    'message' => 'Người nhận không tồn tại hoặc không hoạt động'
                ], 422);
            }

            // Update payment
            $payment->update([
                'type_id' => $request->type_id,
                'recipient_type' => $request->recipient_type,
                'recipient_id' => $request->recipient_id,
                'warehouse_id' => $request->warehouse_id,
                'amount' => $request->amount,
                'payment_method' => $request->payment_method,
                'payment_date' => $request->payment_date,
                'note' => $request->note,
                'reference_number' => $request->reference_number,
            ]);

            Log::info('CashPaymentController@update: Payment updated', ['id' => $payment->id]);

            // Enrich with related data for response
            $this->enrichPaymentData($payment);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Cập nhật phiếu chi thành công',
                'data' => $payment
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('CashPaymentController@update: Error updating payment', [
                'id' => $id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Lỗi khi cập nhật phiếu chi: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Submit payment for approval
     */
    public function submitForApproval($id)
    {
        try {
            Log::info('CashPaymentController@submitForApproval: Submitting payment', ['id' => $id]);

            $payment = CashPayment::findOrFail($id);

            if ($payment->status !== 'draft') {
                return response()->json([
                    'success' => false,
                    'message' => 'Chỉ có thể gửi duyệt phiếu chi ở trạng thái nháp'
                ], 422);
            }

            $payment->update(['status' => 'pending']);

            Log::info('CashPaymentController@submitForApproval: Payment submitted for approval', ['id' => $id]);

            return response()->json([
                'success' => true,
                'message' => 'Gửi phiếu chi để duyệt thành công'
            ]);

        } catch (\Exception $e) {
            Log::error('CashPaymentController@submitForApproval: Error submitting payment', [
                'id' => $id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Lỗi khi gửi duyệt phiếu chi: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Approve payment - CRITICAL METHOD cho việc trừ công nợ
     */
    public function approve($id)
    {
        try {
            DB::beginTransaction();

            Log::info('🎯 CashPaymentController@approve: Starting approval process', ['id' => $id]);

            $payment = CashPayment::with('paymentType')->findOrFail($id);

            if ($payment->status !== 'pending') {
                return response()->json([
                    'success' => false,
                    'message' => 'Chỉ có thể duyệt phiếu chi ở trạng thái chờ duyệt'
                ], 422);
            }

            Log::info('🎯 Payment details before approval', [
                'payment_id' => $payment->id,
                'code' => $payment->code,
                'amount' => $payment->amount,
                'recipient_type' => $payment->recipient_type,
                'recipient_id' => $payment->recipient_id,
                'impact_applied' => $payment->impact_applied,
                'payment_type' => $payment->paymentType->name ?? 'N/A',
                'impact_type' => $payment->paymentType->impact_type ?? 'N/A',
                'impact_action' => $payment->paymentType->impact_action ?? 'N/A'
            ]);

            // Update payment status
            $payment->update([
                'status' => 'approved',
                'approved_by' => auth()->id(),
                'approved_at' => now(),
            ]);

            Log::info('✅ Payment status updated to approved');

            // Apply financial impact - ALWAYS available now
            try {
                Log::info('📉 Applying financial impact via CashVoucherImpactService');
                
                // Ensure we have the service
                if (!$this->impactService) {
                    $this->impactService = app(CashVoucherImpactService::class);
                }
                
                $this->impactService->applyPaymentImpact($payment);
                
                Log::info('✅ Financial impact applied successfully', [
                    'payment_id' => $payment->id,
                    'impact_applied' => true
                ]);
            } catch (\Exception $e) {
                Log::error('❌ Failed to apply financial impact', [
                    'payment_id' => $payment->id,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
                
                // Don't fail the whole transaction, but log the error
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => 'Lỗi khi áp dụng tác động tài chính: ' . $e->getMessage()
                ], 500);
            }

            DB::commit();

            Log::info('🎉 Payment approval completed successfully', ['id' => $id]);

            return response()->json([
                'success' => true,
                'message' => 'Duyệt phiếu chi thành công và đã cập nhật công nợ'
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('❌ CashPaymentController@approve: Error approving payment', [
                'id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Lỗi khi duyệt phiếu chi: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Cancel payment
     */
    public function cancel($id)
    {
        try {
            Log::info('CashPaymentController@cancel: Cancelling payment', ['id' => $id]);

            $payment = CashPayment::findOrFail($id);

            if (!in_array($payment->status, ['draft', 'pending'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Không thể hủy phiếu chi đã được duyệt'
                ], 422);
            }

            $payment->update(['status' => 'cancelled']);

            Log::info('CashPaymentController@cancel: Payment cancelled', ['id' => $id]);

            return response()->json([
                'success' => true,
                'message' => 'Hủy phiếu chi thành công'
            ]);

        } catch (\Exception $e) {
            Log::error('CashPaymentController@cancel: Error cancelling payment', [
                'id' => $id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Lỗi khi hủy phiếu chi: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete payment (only for draft status)
     */
    public function destroy($id)
    {
        try {
            Log::info('CashPaymentController@destroy: Deleting payment', ['id' => $id]);

            $payment = CashPayment::findOrFail($id);

            if ($payment->status !== 'draft') {
                return response()->json([
                    'success' => false,
                    'message' => 'Chỉ có thể xóa phiếu chi ở trạng thái nháp'
                ], 422);
            }

            $payment->delete();

            Log::info('CashPaymentController@destroy: Payment deleted', ['id' => $id]);

            return response()->json([
                'success' => true,
                'message' => 'Xóa phiếu chi thành công'
            ]);

        } catch (\Exception $e) {
            Log::error('CashPaymentController@destroy: Error deleting payment', [
                'id' => $id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Lỗi khi xóa phiếu chi: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Print cash payment voucher
     */
    public function print($id)
    {
        try {
            $payment = CashPayment::with([
                'paymentType', 
                'warehouse', 
                'creator', 
                'approver'
            ])->findOrFail($id);

            // Load recipient
            if ($payment->recipient_type === 'customer') {
                $payment->recipient = Customer::find($payment->recipient_id);
            } else {
                $payment->recipient = Supplier::find($payment->recipient_id);
            }

            // Chỉ cho phép in phiếu đã duyệt
            if ($payment->status !== 'approved') {
                return redirect()->back()->with('error', 'Chỉ có thể in phiếu chi đã được duyệt');
            }

            return view('cash-payments.print', compact('payment'));

        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Không tìm thấy phiếu chi để in');
        }
    }

    /**
 * Get recipients for payment form
 * Endpoint: GET /api/cash-vouchers/payments/recipients?type={customer|supplier}
 */
public function getRecipients(Request $request)
{
    try {
        $type = $request->get('type');
        
        if (!in_array($type, ['customer', 'supplier'])) {
            return response()->json([
                'success' => false,
                'message' => 'Loại người nhận không hợp lệ'
            ], 422);
        }

        $recipients = [];

        if ($type === 'customer') {
            $recipients = Customer::where('status', 'active')
                ->select('id', 'code', 'name', 'phone', 'total_debt')
                ->orderBy('name')
                ->get();
        } else {
            $recipients = Supplier::where('status', 'active')
                ->select('id', 'code', 'name', 'phone', 'total_debt')
                ->orderBy('name')
                ->get();
        }

        Log::info('CashPaymentController@getRecipients: Loaded recipients', [
            'type' => $type,
            'count' => count($recipients)
        ]);

        return response()->json([
            'success' => true,
            'data' => $recipients
        ]);

    } catch (\Exception $e) {
        Log::error('CashPaymentController@getRecipients: Error loading recipients', [
            'error' => $e->getMessage()
        ]);

        return response()->json([
            'success' => false,
            'message' => 'Lỗi khi tải danh sách người nhận: ' . $e->getMessage()
        ], 500);
    }
}

    // ==========================================
    // PRIVATE HELPER METHODS
    // ==========================================

    /**
     * Enrich payment data with related information
     */
    private function enrichPaymentData($payment)
    {
        try {
            // Load payment type
            if (!isset($payment->payment_type)) {
                $payment->payment_type = CashPaymentType::find($payment->type_id);
                // Also set for compatibility
                $payment->paymentType = $payment->payment_type;
            }

            // Load warehouse
            if (!isset($payment->warehouse)) {
                $payment->warehouse = Warehouse::find($payment->warehouse_id);
            }

            // Load creator
            if (!isset($payment->creator)) {
                $payment->creator = User::find($payment->created_by);
            }

            // Load approver if exists
            if ($payment->approved_by && !isset($payment->approver)) {
                $payment->approver = User::find($payment->approved_by);
            }

            // Load recipient
            if ($payment->recipient_type === 'customer') {
                $customer = Customer::find($payment->recipient_id);
                $payment->recipient = $customer ? [
                    'id' => $customer->id,
                    'code' => $customer->code ?? '',
                    'name' => $customer->name,
                    'phone' => $customer->phone ?? '',
                    'total_debt' => $customer->total_debt ?? 0
                ] : null;
            } else {
                $supplier = Supplier::find($payment->recipient_id);
                $payment->recipient = $supplier ? [
                    'id' => $supplier->id,
                    'code' => $supplier->code ?? '',
                    'name' => $supplier->name,
                    'phone' => $supplier->phone ?? '',
                    'total_debt' => $supplier->total_debt ?? 0
                ] : null;
            }

        } catch (\Exception $e) {
            Log::warning('Error enriching payment data', [
                'payment_id' => $payment->id,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Validate recipient exists and is active
     */
    private function validateRecipient($type, $id)
    {
        try {
            if ($type === 'customer') {
                return Customer::where('id', $id)->where('is_active', true)->first();
            } else {
                return Supplier::where('id', $id)->where('status', 'active')->first();
            }
        } catch (\Exception $e) {
            Log::error('Error validating recipient', [
                'type' => $type,
                'id' => $id,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }
    
}