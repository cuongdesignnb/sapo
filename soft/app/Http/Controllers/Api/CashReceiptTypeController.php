<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CashReceiptType;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class CashReceiptTypeController extends Controller
{
    public function index(Request $request)
    {
        try {
            $query = CashReceiptType::query();

            if ($request->has('recipient_type')) {
                $query->byRecipientType($request->recipient_type);
            }

            if ($request->has('active_only') && $request->active_only) {
                $query->active();
            }

            $types = $query->ordered()->get();

            return response()->json([
                'success' => true,
                'data' => $types
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Lỗi khi lấy danh sách loại phiếu thu: ' . $e->getMessage()
            ], 500);
        }
    }

    public function store(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'code' => 'required|string|max:50|unique:cash_receipt_types',
                'name' => 'required|string|max:255',
                'recipient_type' => 'required|in:customer,supplier',
                'impact_type' => 'required|in:debt,revenue,advance',
                'impact_action' => 'required|in:increase,decrease',
                'description' => 'nullable|string',
                'sort_order' => 'nullable|integer',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Dữ liệu không hợp lệ',
                    'errors' => $validator->errors()
                ], 422);
            }

            $type = CashReceiptType::create($request->all());

            return response()->json([
                'success' => true,
                'message' => 'Tạo loại phiếu thu thành công',
                'data' => $type
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Lỗi khi tạo loại phiếu thu: ' . $e->getMessage()
            ], 500);
        }
    }

    public function show($id)
    {
        try {
            $type = CashReceiptType::findOrFail($id);

            return response()->json([
                'success' => true,
                'data' => $type
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Không tìm thấy loại phiếu thu'
            ], 404);
        }
    }

    public function update(Request $request, $id)
    {
        try {
            $type = CashReceiptType::findOrFail($id);

            $validator = Validator::make($request->all(), [
                'code' => 'required|string|max:50|unique:cash_receipt_types,code,' . $id,
                'name' => 'required|string|max:255',
                'recipient_type' => 'required|in:customer,supplier',
                'impact_type' => 'required|in:debt,revenue,advance',
                'impact_action' => 'required|in:increase,decrease',
                'description' => 'nullable|string',
                'sort_order' => 'nullable|integer',
                'is_active' => 'boolean',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Dữ liệu không hợp lệ',
                    'errors' => $validator->errors()
                ], 422);
            }

            $type->update($request->all());

            return response()->json([
                'success' => true,
                'message' => 'Cập nhật loại phiếu thu thành công',
                'data' => $type
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Lỗi khi cập nhật loại phiếu thu: ' . $e->getMessage()
            ], 500);
        }
    }

    public function destroy($id)
    {
        try {
            $type = CashReceiptType::findOrFail($id);

            if ($type->receipts()->count() > 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'Không thể xóa loại phiếu đã được sử dụng'
                ], 400);
            }

            $type->delete();

            return response()->json([
                'success' => true,
                'message' => 'Xóa loại phiếu thu thành công'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Lỗi khi xóa loại phiếu thu: ' . $e->getMessage()
            ], 500);
        }
    }
}