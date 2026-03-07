<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductSerial;
use App\Models\ProductSerialHistory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class ProductSerialController extends Controller
{
    /**
     * Danh sách serial/IMEI với filter.
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $query = ProductSerial::with(['product:id,sku,name', 'warehouse:id,name,code']);

            if ($request->filled('product_id')) {
                $query->where('product_id', $request->product_id);
            }
            if ($request->filled('warehouse_id')) {
                $query->where('warehouse_id', $request->warehouse_id);
            }
            if ($request->filled('status')) {
                $query->where('status', $request->status);
            }
            if ($request->filled('search')) {
                $query->where('serial_number', 'like', '%' . $request->search . '%');
            }

            $serials = $query->orderByDesc('created_at')
                ->paginate($request->get('per_page', 20));

            return response()->json([
                'success' => true,
                'data' => $serials->items(),
                'pagination' => [
                    'current_page' => $serials->currentPage(),
                    'last_page' => $serials->lastPage(),
                    'per_page' => $serials->perPage(),
                    'total' => $serials->total(),
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Lỗi khi tải danh sách serial: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Chi tiết một serial.
     */
    public function show($id): JsonResponse
    {
        try {
            $serial = ProductSerial::with([
                'product:id,sku,name',
                'warehouse:id,name,code',
                'purchaseReceiptItem.purchaseReceipt:id,code',
                'orderItem.order:id,code',
                'histories' => function ($q) {
                    $q->with(['user:id,name', 'fromWarehouse:id,name', 'toWarehouse:id,name'])
                      ->orderByDesc('created_at')
                      ->limit(50);
                },
            ])->findOrFail($id);

            return response()->json([
                'success' => true,
                'data' => $serial,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Không tìm thấy serial: ' . $e->getMessage(),
            ], 404);
        }
    }

    /**
     * Lịch sử của một serial.
     */
    public function history($id, Request $request): JsonResponse
    {
        try {
            $serial = ProductSerial::findOrFail($id);

            $query = $serial->histories()
                ->with(['user:id,name', 'fromWarehouse:id,name', 'toWarehouse:id,name']);

            if ($request->filled('action')) {
                $query->where('action', $request->action);
            }

            $histories = $query->paginate($request->get('per_page', 20));

            return response()->json([
                'success' => true,
                'data' => $histories->items(),
                'pagination' => [
                    'current_page' => $histories->currentPage(),
                    'last_page' => $histories->lastPage(),
                    'per_page' => $histories->perPage(),
                    'total' => $histories->total(),
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Lỗi khi tải lịch sử serial: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Nhập serial hàng loạt (dùng khi nhập kho hoặc khởi tạo).
     */
    public function bulkImport(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'product_id' => 'required|exists:products,id',
            'warehouse_id' => 'required|exists:warehouses,id',
            'serial_numbers' => 'required|array|min:1',
            'serial_numbers.*' => 'required|string|max:255',
            'cost_price' => 'nullable|numeric|min:0',
            'purchase_receipt_item_id' => 'nullable|exists:purchase_receipt_items,id',
            'note' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Dữ liệu không hợp lệ',
                'errors' => $validator->errors(),
            ], 422);
        }

        // Verify product has track_serial enabled
        $product = Product::findOrFail($request->product_id);
        if (!$product->track_serial) {
            return response()->json([
                'success' => false,
                'message' => 'Sản phẩm này không bật quản lý theo Serial/IMEI',
            ], 422);
        }

        // Check duplicates within request
        $uniqueSerials = array_unique(array_map('trim', $request->serial_numbers));
        if (count($uniqueSerials) !== count($request->serial_numbers)) {
            return response()->json([
                'success' => false,
                'message' => 'Có serial trùng lặp trong danh sách nhập',
            ], 422);
        }

        // Check duplicates in database
        $existingSerials = ProductSerial::where('product_id', $request->product_id)
            ->whereIn('serial_number', $uniqueSerials)
            ->pluck('serial_number')
            ->toArray();

        if (!empty($existingSerials)) {
            return response()->json([
                'success' => false,
                'message' => 'Các serial sau đã tồn tại: ' . implode(', ', $existingSerials),
                'duplicates' => $existingSerials,
            ], 422);
        }

        DB::beginTransaction();
        try {
            $created = ProductSerial::bulkImport(
                $uniqueSerials,
                $request->product_id,
                $request->warehouse_id,
                $request->cost_price ?? 0,
                $request->purchase_receipt_item_id
            );

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Nhập ' . count($created) . ' serial thành công',
                'data' => $created,
                'count' => count($created),
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Lỗi khi nhập serial: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Cập nhật thông tin serial (note, status).
     */
    public function update(Request $request, $id): JsonResponse
    {
        try {
            $serial = ProductSerial::findOrFail($id);

            $validator = Validator::make($request->all(), [
                'status' => 'nullable|in:in_stock,sold,returned,defective,transferred',
                'note' => 'nullable|string',
                'warehouse_id' => 'nullable|exists:warehouses,id',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Dữ liệu không hợp lệ',
                    'errors' => $validator->errors(),
                ], 422);
            }

            $oldStatus = $serial->status;
            $updates = [];

            if ($request->filled('note')) {
                $updates['note'] = $request->note;
            }

            if ($request->filled('status') && $request->status !== $oldStatus) {
                $updates['status'] = $request->status;

                if ($request->status === 'defective') {
                    $serial->markAsDefective(auth()->id(), $request->note);
                } else {
                    $serial->update($updates);
                    $serial->recordHistory('adjusted', $serial->warehouse_id, $serial->warehouse_id, null, null, auth()->id(), "Đổi trạng thái: {$oldStatus} → {$request->status}");
                }
            } else {
                $serial->update($updates);
            }

            return response()->json([
                'success' => true,
                'message' => 'Cập nhật serial thành công',
                'data' => $serial->fresh(),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Lỗi khi cập nhật serial: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Xóa serial (chỉ cho phép xóa serial in_stock).
     */
    public function destroy($id): JsonResponse
    {
        try {
            $serial = ProductSerial::findOrFail($id);

            if ($serial->status !== ProductSerial::STATUS_IN_STOCK) {
                return response()->json([
                    'success' => false,
                    'message' => 'Chỉ có thể xóa serial đang trong kho (in_stock)',
                ], 422);
            }

            $serial->delete();

            return response()->json([
                'success' => true,
                'message' => 'Xóa serial thành công',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Lỗi khi xóa serial: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Lấy danh sách serial có sẵn trong kho (dùng cho chọn khi bán hàng).
     */
    public function available(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'product_id' => 'required|exists:products,id',
                'warehouse_id' => 'required|exists:warehouses,id',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors(),
                ], 422);
            }

            $serials = ProductSerial::where('product_id', $request->product_id)
                ->where('warehouse_id', $request->warehouse_id)
                ->where('status', ProductSerial::STATUS_IN_STOCK)
                ->orderBy('serial_number')
                ->get(['id', 'serial_number', 'cost_price', 'created_at']);

            return response()->json([
                'success' => true,
                'data' => $serials,
                'count' => $serials->count(),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Lỗi khi tải danh sách serial: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Tra cứu serial nhanh (tìm theo serial number).
     */
    public function lookup(Request $request): JsonResponse
    {
        try {
            $request->validate(['serial_number' => 'required|string']);

            $serial = ProductSerial::with(['product:id,sku,name', 'warehouse:id,name,code'])
                ->where('serial_number', $request->serial_number)
                ->first();

            if (!$serial) {
                return response()->json([
                    'success' => false,
                    'message' => 'Không tìm thấy serial: ' . $request->serial_number,
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => $serial,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Lỗi tra cứu: ' . $e->getMessage(),
            ], 500);
        }
    }
}
