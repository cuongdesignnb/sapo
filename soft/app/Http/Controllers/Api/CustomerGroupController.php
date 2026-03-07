<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CustomerGroup;
use App\Models\Customer;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CustomerGroupController extends Controller
{
    /**
     * Display a listing of customer groups
     */
    // CustomerGroupController@index - Version đầy đủ
public function index(Request $request): JsonResponse
{
    try {
        $perPage = $request->get('per_page', 15);
        $search = $request->get('search');
        $sortBy = $request->get('sort_by', 'created_at');
        $sortOrder = $request->get('sort_order', 'desc');

        $query = CustomerGroup::withCount('customers');

        // Search
        if ($search) {
            $query->where(function($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('code', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }

        // Filter by type
        if ($request->has('type') && $request->get('type')) {
            $query->where('type', $request->get('type'));
        }

        // Filter by has_customers
        if ($request->has('has_customers')) {
            $hasCustomers = $request->get('has_customers');
            if ($hasCustomers === 'yes') {
                $query->whereHas('customers');
            } elseif ($hasCustomers === 'no') {
                $query->whereDoesntHave('customers');
            }
        }

        // Sorting
        $allowedSortFields = ['id', 'code', 'name', 'type', 'discount_percent', 'created_at'];
        if (in_array($sortBy, $allowedSortFields)) {
            if ($sortBy === 'customers_count') {
                $query->orderBy('customers_count', $sortOrder);
            } else {
                $query->orderBy($sortBy, $sortOrder);
            }
        }

        // Pagination
        $groups = $query->paginate($perPage);

        // Transform data
        $data = $groups->getCollection()->map(function($group) {
            return [
                'id' => $group->id,
                'code' => $group->code,
                'name' => $group->name,
                'type' => $group->type,
                'type_text' => $group->type_text,
                'type_color_class' => $group->type_color_class,
                'description' => $group->description,
                'discount_percent' => $group->discount_percent,
                'formatted_discount' => $group->formatted_discount,
                'payment_terms' => $group->payment_terms,
                'customers_count' => $group->customers_count,
                'created_at' => $group->created_at->format('d/m/Y H:i'),
                'updated_at' => $group->updated_at->format('d/m/Y H:i'),
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $data,
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
        Log::error('Error fetching customer groups: ' . $e->getMessage());
        return response()->json([
            'success' => false,
            'message' => 'Có lỗi xảy ra khi tải danh sách nhóm khách hàng',
            'data' => []
        ], 500);
    }
}

    /**
     * Store a newly created customer group
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'code' => [
                    'nullable',
                    'string',
                    'max:255',
                    Rule::unique('customer_groups', 'code')
                ],
                'name' => 'required|string|max:255',
                'type' => 'required|in:vip,normal,local,import',
                'description' => 'nullable|string|max:1000',
                'discount_percent' => 'nullable|numeric|min:0|max:100',
                'payment_terms' => 'nullable|integer|min:0',
            ], [
                'name.required' => 'Tên nhóm khách hàng là bắt buộc',
                'type.required' => 'Loại nhóm là bắt buộc',
                'type.in' => 'Loại nhóm không hợp lệ',
                'discount_percent.min' => 'Chiết khấu không được âm',
                'discount_percent.max' => 'Chiết khấu không được vượt quá 100%',
                'payment_terms.min' => 'Điều kiện thanh toán không được âm',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Dữ liệu không hợp lệ',
                    'errors' => $validator->errors()
                ], 422);
            }

            $groupData = $validator->validated();
            $group = CustomerGroup::create($groupData);

            return response()->json([
                'success' => true,
                'message' => 'Tạo nhóm khách hàng thành công',
                'data' => $group->fresh()->full_info
            ], 201);

        } catch (\Exception $e) {
            Log::error('Error creating customer group: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Có lỗi xảy ra khi tạo nhóm khách hàng'
            ], 500);
        }
    }

    /**
     * Display the specified customer group
     */
    public function show(CustomerGroup $customerGroup): JsonResponse
    {
        try {
            $customerGroup->load(['customers' => function ($query) {
                $query->select('id', 'code', 'name', 'group_id', 'email', 'phone', 'status', 'total_spend', 'total_orders', 'created_at')
                      ->orderBy('created_at', 'desc')
                      ->limit(20);
            }]);

            $data = $customerGroup->full_info;
            
            // Add detailed information
            $data['customers'] = $customerGroup->customers->map(function ($customer) {
                return [
                    'id' => $customer->id,
                    'code' => $customer->code,
                    'name' => $customer->name,
                    'email' => $customer->email,
                    'phone' => $customer->phone,
                    'status' => $customer->status,
                    'total_spend' => $customer->total_spend,
                    'total_orders' => $customer->total_orders,
                    'created_at' => $customer->created_at?->format('d/m/Y H:i'),
                ];
            });

            // Statistics
            $data['statistics'] = [
                'total_customers' => $customerGroup->customers()->count(),
                'active_customers' => $customerGroup->customers()->where('status', 'active')->count(),
                'total_revenue' => $customerGroup->customers()->sum('total_spend'),
                'total_orders' => $customerGroup->customers()->sum('total_orders'),
                'avg_order_value' => $customerGroup->customers()->avg('total_spend'),
            ];

            return response()->json([
                'success' => true,
                'data' => $data
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching customer group: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Có lỗi xảy ra khi tải thông tin nhóm khách hàng'
            ], 500);
        }
    }

    /**
     * Update the specified customer group
     */
    public function update(Request $request, CustomerGroup $customerGroup): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'code' => [
                    'nullable',
                    'string',
                    'max:255',
                    Rule::unique('customer_groups', 'code')->ignore($customerGroup->id)
                ],
                'name' => 'required|string|max:255',
                'type' => 'required|in:vip,normal,local,import',
                'description' => 'nullable|string|max:1000',
                'discount_percent' => 'nullable|numeric|min:0|max:100',
                'payment_terms' => 'nullable|integer|min:0',
            ], [
                'name.required' => 'Tên nhóm khách hàng là bắt buộc',
                'type.required' => 'Loại nhóm là bắt buộc',
                'type.in' => 'Loại nhóm không hợp lệ',
                'discount_percent.min' => 'Chiết khấu không được âm',
                'discount_percent.max' => 'Chiết khấu không được vượt quá 100%',
                'payment_terms.min' => 'Điều kiện thanh toán không được âm',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Dữ liệu không hợp lệ',
                    'errors' => $validator->errors()
                ], 422);
            }

            $groupData = $validator->validated();
            $customerGroup->update($groupData);

            return response()->json([
                'success' => true,
                'message' => 'Cập nhật nhóm khách hàng thành công',
                'data' => $customerGroup->fresh()->full_info
            ]);

        } catch (\Exception $e) {
            Log::error('Error updating customer group: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Có lỗi xảy ra khi cập nhật nhóm khách hàng'
            ], 500);
        }
    }

    /**
     * Remove the specified customer group
     */
    public function destroy(CustomerGroup $customerGroup): JsonResponse
    {
        try {
            // Check if group has customers
            if (!$customerGroup->canBeDeleted()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Không thể xóa nhóm này vì đã có khách hàng thuộc nhóm'
                ], 409);
            }

            $customerGroup->delete();

            return response()->json([
                'success' => true,
                'message' => 'Xóa nhóm khách hàng thành công'
            ]);

        } catch (\Exception $e) {
            Log::error('Error deleting customer group: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Có lỗi xảy ra khi xóa nhóm khách hàng'
            ], 500);
        }
    }

    /**
     * Bulk delete customer groups
     */
    public function bulkDelete(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'ids' => 'required|array|min:1',
                'ids.*' => 'required|integer|exists:customer_groups,id'
            ], [
                'ids.required' => 'Danh sách ID là bắt buộc',
                'ids.array' => 'Danh sách ID phải là mảng',
                'ids.min' => 'Phải chọn ít nhất 1 nhóm khách hàng',
                'ids.*.exists' => 'Nhóm khách hàng không tồn tại'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Dữ liệu không hợp lệ',
                    'errors' => $validator->errors()
                ], 422);
            }

            $groups = CustomerGroup::whereIn('id', $request->ids)->get();
            $cannotDelete = [];
            $deleted = 0;

            DB::beginTransaction();

            foreach ($groups as $group) {
                if (!$group->canBeDeleted()) {
                    $cannotDelete[] = $group->name;
                } else {
                    $group->delete();
                    $deleted++;
                }
            }

            DB::commit();

            $message = "Đã xóa {$deleted} nhóm khách hàng";
            if (!empty($cannotDelete)) {
                $message .= ". Không thể xóa: " . implode(', ', $cannotDelete) . " (có khách hàng)";
            }

            return response()->json([
                'success' => true,
                'message' => $message,
                'deleted_count' => $deleted,
                'cannot_delete' => $cannotDelete
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error bulk deleting customer groups: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Có lỗi xảy ra khi xóa nhóm khách hàng'
            ], 500);
        }
    }

    /**
     * Export customer groups to CSV
     */
    public function export(Request $request): StreamedResponse
    {
        $search = $request->get('search');
        $type = $request->get('type');
        
        $query = CustomerGroup::withCount('customers');
        
        if ($search) {
            $query->search($search);
        }
        
        if ($type) {
            $query->byType($type);
        }

        $fileName = 'customer_groups_' . date('Y-m-d_H-i-s') . '.csv';

        return response()->streamDownload(function () use ($query) {
            $handle = fopen('php://output', 'w');
            
            // Add BOM for UTF-8
            fputs($handle, "\xEF\xBB\xBF");
            
            // Headers
            fputcsv($handle, [
                'Mã nhóm',
                'Tên nhóm',
                'Loại',
                'Mô tả',
                'Chiết khấu (%)',
                'Điều kiện thanh toán (ngày)',
                'Số khách hàng',
                'Ngày tạo'
            ]);

            // Data
            $query->chunk(1000, function ($groups) use ($handle) {
                foreach ($groups as $group) {
                    fputcsv($handle, [
                        $group->code,
                        $group->name,
                        $group->type_text,
                        $group->description,
                        $group->discount_percent,
                        $group->payment_terms,
                        $group->customers_count,
                        $group->created_at?->format('d/m/Y H:i')
                    ]);
                }
            });

            fclose($handle);
        }, $fileName, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$fileName}\"",
        ]);
    }

    /**
     * Import customer groups from CSV
     */
    public function import(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'file' => 'required|file|mimes:csv,txt|max:10240', // 10MB max
            ], [
                'file.required' => 'File CSV là bắt buộc',
                'file.mimes' => 'File phải có định dạng CSV',
                'file.max' => 'File không được vượt quá 10MB'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'File không hợp lệ',
                    'errors' => $validator->errors()
                ], 422);
            }

            $file = $request->file('file');
            $handle = fopen($file->getPathname(), 'r');
            
            // Skip header row
            fgetcsv($handle);
            
            $imported = 0;
            $errors = [];
            $row = 1;

            DB::beginTransaction();

            while (($data = fgetcsv($handle)) !== false) {
                $row++;
                
                if (count($data) < 2) {
                    continue; // Skip empty rows
                }

                try {
                    $groupData = [
                        'code' => !empty($data[0]) ? trim($data[0]) : null,
                        'name' => !empty($data[1]) ? trim($data[1]) : null,
                        'type' => !empty($data[2]) ? trim($data[2]) : 'normal',
                        'description' => !empty($data[3]) ? trim($data[3]) : null,
                        'discount_percent' => !empty($data[4]) ? (float)$data[4] : 0,
                        'payment_terms' => !empty($data[5]) ? (int)$data[5] : 0,
                    ];

                    $validator = Validator::make($groupData, [
                        'code' => 'nullable|string|max:255|unique:customer_groups,code',
                        'name' => 'required|string|max:255',
                        'type' => 'required|in:vip,normal,local,import',
                        'description' => 'nullable|string|max:1000',
                        'discount_percent' => 'nullable|numeric|min:0|max:100',
                        'payment_terms' => 'nullable|integer|min:0',
                    ]);

                    if ($validator->fails()) {
                        $errors[] = "Dòng {$row}: " . implode(', ', $validator->errors()->all());
                        continue;
                    }

                    CustomerGroup::create($groupData);
                    $imported++;

                } catch (\Exception $e) {
                    $errors[] = "Dòng {$row}: " . $e->getMessage();
                }
            }

            fclose($handle);

            if ($imported > 0) {
                DB::commit();
            } else {
                DB::rollBack();
            }

            return response()->json([
                'success' => $imported > 0,
                'message' => "Đã import {$imported} nhóm khách hàng",
                'imported_count' => $imported,
                'errors' => $errors
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error importing customer groups: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Có lỗi xảy ra khi import dữ liệu'
            ], 500);
        }
    }

    /**
     * Get customer groups for select options
     */
    public function options(Request $request): JsonResponse
    {
        try {
            $search = $request->get('search');
            $limit = $request->get('limit', 50);

            $query = CustomerGroup::select('id', 'code', 'name', 'type');

            if ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('code', 'like', "%{$search}%")
                      ->orWhere('name', 'like', "%{$search}%");
                });
            }

            $groups = $query->orderBy('name')
                           ->limit($limit)
                           ->get()
                           ->map(function ($group) {
                               return [
                                   'value' => $group->id,
                                   'label' => "{$group->code} - {$group->name}",
                                   'code' => $group->code,
                                   'name' => $group->name,
                                   'type' => $group->type,
                               ];
                           });

            return response()->json([
                'success' => true,
                'data' => $groups
            ]);

        } catch (\Exception $e) {
            Log::error('Error fetching customer group options: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Có lỗi xảy ra khi tải danh sách nhóm khách hàng'
            ], 500);
        }
    }

    /**
     * Get customer group statistics
     */
    public function statistics(): JsonResponse
    {
        try {
            $stats = [
                'total_groups' => CustomerGroup::count(),
                'vip_groups' => CustomerGroup::byType('vip')->count(),
                'normal_groups' => CustomerGroup::byType('normal')->count(),
                'local_groups' => CustomerGroup::byType('local')->count(),
                'import_groups' => CustomerGroup::byType('import')->count(),
                'groups_with_customers' => CustomerGroup::whereHas('customers')->count(),
                'total_customers_in_groups' => Customer::whereNotNull('group_id')->count(),
                'avg_discount' => CustomerGroup::avg('discount_percent'),
                'avg_payment_terms' => CustomerGroup::avg('payment_terms'),
            ];

            // Group by type with customer counts
            $groupStats = CustomerGroup::withCount('customers')
                                     ->get()
                                     ->groupBy('type')
                                     ->map(function ($groups, $type) {
                                         return [
                                             'type' => $type,
                                             'type_text' => CustomerGroup::TYPES[$type] ?? $type,
                                             'groups_count' => $groups->count(),
                                             'customers_count' => $groups->sum('customers_count'),
                                         ];
                                     })->values();

            $stats['by_type'] = $groupStats;

            return response()->json([
                'success' => true,
                'data' => $stats
            ]);

        } catch (\Exception $e) {
            Log::error('Error fetching customer group statistics: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Có lỗi xảy ra khi tải thống kê nhóm khách hàng'
            ], 500);
        }
    }

    /**
     * Get customers in a specific group
     */
    public function customers(Request $request, CustomerGroup $customerGroup): JsonResponse
    {
        try {
            $perPage = $request->get('per_page', 15);
            $search = $request->get('search');

            $query = $customerGroup->customers();

            if ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('code', 'like', "%{$search}%")
                      ->orWhere('name', 'like', "%{$search}%")
                      ->orWhere('email', 'like', "%{$search}%")
                      ->orWhere('phone', 'like', "%{$search}%");
                });
            }

            $customers = $query->orderBy('created_at', 'desc')->paginate($perPage);

            $customers->getCollection()->transform(function ($customer) {
                return [
                    'id' => $customer->id,
                    'code' => $customer->code,
                    'name' => $customer->name,
                    'email' => $customer->email,
                    'phone' => $customer->phone,
                    'status' => $customer->status,
                    'total_spend' => $customer->total_spend,
                    'total_orders' => $customer->total_orders,
                    'created_at' => $customer->created_at?->format('d/m/Y H:i'),
                ];
            });

            return response()->json([
                'success' => true,
                'data' => $customers->items(),
                'pagination' => [
                    'current_page' => $customers->currentPage(),
                    'last_page' => $customers->lastPage(),
                    'per_page' => $customers->perPage(),
                    'total' => $customers->total(),
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Error fetching group customers: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Có lỗi xảy ra khi tải danh sách khách hàng'
            ], 500);
        }
    }
}