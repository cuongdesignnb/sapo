<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CashPaymentType;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;

class CashPaymentTypeController extends Controller
{
    /**
     * Get list of payment types
     */
    public function index(Request $request)
    {
        try {
            $query = CashPaymentType::query();
            
            // Filter by active status
            if ($request->has('active_only') && $request->active_only) {
                $query->active();
            }
            
            // Filter by recipient type
            if ($request->has('recipient_type')) {
                $query->byRecipientType($request->recipient_type);
            }
            
            // Search by name or code
            if ($request->has('search')) {
                $search = $request->search;
                $query->where(function($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                      ->orWhere('code', 'like', "%{$search}%")
                      ->orWhere('description', 'like', "%{$search}%");
                });
            }
            
            $paymentTypes = $query->ordered()->get();

            return response()->json([
                'success' => true,
                'data' => $paymentTypes
            ]);

        } catch (\Exception $e) {
            Log::error('Error getting payment types: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Lỗi khi lấy danh sách loại phiếu chi: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get payment type details
     */
    public function show($id)
    {
        try {
            $paymentType = CashPaymentType::findOrFail($id);

            return response()->json([
                'success' => true,
                'data' => $paymentType
            ]);

        } catch (\Exception $e) {
            Log::error('Error getting payment type: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Lỗi khi lấy thông tin loại phiếu chi: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Create new payment type
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'code' => 'required|string|max:50|unique:cash_payment_types,code',
            'name' => 'required|string|max:255',
            'recipient_type' => 'required|in:customer,supplier',
            'impact_type' => 'required|in:debt,expense,advance',
            'impact_action' => 'required|in:increase,decrease',
            'description' => 'nullable|string',
            'is_active' => 'boolean',
            'sort_order' => 'integer|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Dữ liệu không hợp lệ',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $paymentType = CashPaymentType::create([
                'code' => $request->code,
                'name' => $request->name,
                'recipient_type' => $request->recipient_type,
                'impact_type' => $request->impact_type,
                'impact_action' => $request->impact_action,
                'description' => $request->description,
                'is_active' => $request->get('is_active', true),
                'sort_order' => $request->get('sort_order', 0),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Tạo loại phiếu chi thành công',
                'data' => $paymentType
            ], 201);

        } catch (\Exception $e) {
            Log::error('Error creating payment type: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Lỗi khi tạo loại phiếu chi: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update payment type
     */
    public function update(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'code' => 'required|string|max:50|unique:cash_payment_types,code,' . $id,
            'name' => 'required|string|max:255',
            'recipient_type' => 'required|in:customer,supplier',
            'impact_type' => 'required|in:debt,expense,advance',
            'impact_action' => 'required|in:increase,decrease',
            'description' => 'nullable|string',
            'is_active' => 'boolean',
            'sort_order' => 'integer|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Dữ liệu không hợp lệ',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $paymentType = CashPaymentType::findOrFail($id);

            $paymentType->update([
                'code' => $request->code,
                'name' => $request->name,
                'recipient_type' => $request->recipient_type,
                'impact_type' => $request->impact_type,
                'impact_action' => $request->impact_action,
                'description' => $request->description,
                'is_active' => $request->get('is_active', true),
                'sort_order' => $request->get('sort_order', 0),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Cập nhật loại phiếu chi thành công',
                'data' => $paymentType
            ]);

        } catch (\Exception $e) {
            Log::error('Error updating payment type: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Lỗi khi cập nhật loại phiếu chi: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete payment type
     */
    public function destroy($id)
    {
        try {
            $paymentType = CashPaymentType::findOrFail($id);

            // Check if payment type is being used
            if ($paymentType->payments()->exists()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Không thể xóa loại phiếu chi đang được sử dụng'
                ], 422);
            }

            $paymentType->delete();

            return response()->json([
                'success' => true,
                'message' => 'Xóa loại phiếu chi thành công'
            ]);

        } catch (\Exception $e) {
            Log::error('Error deleting payment type: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Lỗi khi xóa loại phiếu chi: ' . $e->getMessage()
            ], 500);
        }
    }
}