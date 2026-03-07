<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SupplierGroup;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

class SupplierGroupController extends Controller
{
    /**
     * Display a listing of supplier groups
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $perPage = $request->get('per_page', 15);
            $search = $request->get('search');
            $sortBy = $request->get('sort_by', 'name');
            $sortOrder = $request->get('sort_order', 'asc');

            $query = SupplierGroup::withCount('suppliers');

            // Search functionality
            if ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('code', 'like', "%{$search}%")
                      ->orWhere('name', 'like', "%{$search}%")
                      ->orWhere('type', 'like', "%{$search}%");
                });
            }

            // Filter by type
            if ($request->has('type')) {
                $query->where('type', $request->get('type'));
            }

            // Sorting
            $allowedSortFields = ['id', 'code', 'name', 'type', 'discount_percent', 'payment_terms', 'created_at'];
            if (in_array($sortBy, $allowedSortFields)) {
                $query->orderBy($sortBy, $sortOrder);
            }

            $groups = $query->paginate($perPage);

            // Transform data
            $groups->getCollection()->transform(function ($group) {
                return [
                    'id' => $group->id,
                    'code' => $group->code,
                    'name' => $group->name,
                    'type' => $group->type,
                    'description' => $group->description,
                    'discount_percent' => $group->discount_percent,
                    'payment_terms' => $group->payment_terms,
                    'suppliers_count' => $group->suppliers_count,
                    'created_at' => $group->created_at?->format('d/m/Y H:i'),
                    'updated_at' => $group->updated_at?->format('d/m/Y H:i'),
                ];
            });

            return response()->json([
                'success' => true,
                'data' => $groups->items(),
                'pagination' => [
                    'current_page' => $groups->currentPage(),
                    'last_page' => $groups->lastPage(),
                    'per_page' => $groups->perPage(),
                    'total' => $groups->total(),
                    'from' => $groups->firstItem(),
                    'to' => $groups->lastItem(),
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Error fetching supplier groups: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Có lỗi xảy ra khi tải danh sách nhóm nhà cung cấp'
            ], 500);
        }
    }

    /**
     * Store a newly created supplier group
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'code' => [
                    'nullable',
                    'string',
                    'max:255',
                    Rule::unique('supplier_groups', 'code')
                ],
                'name' => 'required|string|max:255',
                'type' => 'nullable|string|max:50',
                'description' => 'nullable|string|max:1000',
                'discount_percent' => 'nullable|numeric|min:0|max:100',
                'payment_terms' => 'nullable|integer|min:0',
            ], [
                'name.required' => 'Tên nhóm là bắt buộc',
                'name.max' => 'Tên nhóm không được vượt quá 255 ký tự',
                'code.unique' => 'Mã nhóm đã tồn tại',
                'discount_percent.min' => 'Phần trăm chiết khấu phải lớn hơn hoặc bằng 0',
                'discount_percent.max' => 'Phần trăm chiết khấu phải nhỏ hơn hoặc bằng 100',
                'payment_terms.min' => 'Số ngày thanh toán phải lớn hơn hoặc bằng 0',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Dữ liệu không hợp lệ',
                    'errors' => $validator->errors()
                ], 422);
            }

            $group = SupplierGroup::create($validator->validated());

            return response()->json([
                'success' => true,
                'message' => 'Tạo nhóm nhà cung cấp thành công',
                'data' => [
                    'id' => $group->id,
                    'code' => $group->code,
                    'name' => $group->name,
                    'type' => $group->type,
                    'description' => $group->description,
                    'discount_percent' => $group->discount_percent,
                    'payment_terms' => $group->payment_terms,
                    'suppliers_count' => 0,
                    'created_at' => $group->created_at?->format('d/m/Y H:i'),
                ]
            ], 201);

        } catch (\Exception $e) {
            Log::error('Error creating supplier group: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Có lỗi xảy ra khi tạo nhóm nhà cung cấp'
            ], 500);
        }
    }

    /**
     * Display the specified supplier group
     */
    public function show(SupplierGroup $group): JsonResponse
    {
        try {
            $group->loadCount('suppliers');

            return response()->json([
                'success' => true,
                'data' => [
                    'id' => $group->id,
                    'code' => $group->code,
                    'name' => $group->name,
                    'type' => $group->type,
                    'description' => $group->description,
                    'discount_percent' => $group->discount_percent,
                    'payment_terms' => $group->payment_terms,
                    'suppliers_count' => $group->suppliers_count,
                    'created_at' => $group->created_at?->format('d/m/Y H:i'),
                    'updated_at' => $group->updated_at?->format('d/m/Y H:i'),
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching supplier group: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Có lỗi xảy ra khi tải thông tin nhóm nhà cung cấp'
            ], 500);
        }
    }

    /**
     * Update the specified supplier group
     */
    public function update(Request $request, SupplierGroup $group): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'code' => [
                    'nullable',
                    'string',
                    'max:255',
                    Rule::unique('supplier_groups', 'code')->ignore($group->id)
                ],
                'name' => 'required|string|max:255',
                'type' => 'nullable|string|max:50',
                'description' => 'nullable|string|max:1000',
                'discount_percent' => 'nullable|numeric|min:0|max:100',
                'payment_terms' => 'nullable|integer|min:0',
            ], [
                'name.required' => 'Tên nhóm là bắt buộc',
                'name.max' => 'Tên nhóm không được vượt quá 255 ký tự',
                'code.unique' => 'Mã nhóm đã tồn tại',
                'discount_percent.min' => 'Phần trăm chiết khấu phải lớn hơn hoặc bằng 0',
                'discount_percent.max' => 'Phần trăm chiết khấu phải nhỏ hơn hoặc bằng 100',
                'payment_terms.min' => 'Số ngày thanh toán phải lớn hơn hoặc bằng 0',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Dữ liệu không hợp lệ',
                    'errors' => $validator->errors()
                ], 422);
            }

            $group->update($validator->validated());

            return response()->json([
                'success' => true,
                'message' => 'Cập nhật nhóm nhà cung cấp thành công',
                'data' => [
                    'id' => $group->id,
                    'code' => $group->code,
                    'name' => $group->name,
                    'type' => $group->type,
                    'description' => $group->description,
                    'discount_percent' => $group->discount_percent,
                    'payment_terms' => $group->payment_terms,
                    'updated_at' => $group->updated_at?->format('d/m/Y H:i'),
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Error updating supplier group: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Có lỗi xảy ra khi cập nhật nhóm nhà cung cấp'
            ], 500);
        }
    }

    /**
     * Remove the specified supplier group
     */
    public function destroy(SupplierGroup $group): JsonResponse
    {
        try {
            // Check if group has suppliers
            if ($group->suppliers()->exists()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Không thể xóa nhóm này vì đã có nhà cung cấp thuộc nhóm'
                ], 409);
            }

            $group->delete();

            return response()->json([
                'success' => true,
                'message' => 'Xóa nhóm nhà cung cấp thành công'
            ]);

        } catch (\Exception $e) {
            Log::error('Error deleting supplier group: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Có lỗi xảy ra khi xóa nhóm nhà cung cấp'
            ], 500);
        }
    }
}