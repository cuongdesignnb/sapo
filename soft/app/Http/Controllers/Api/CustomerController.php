<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\CustomerGroup;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class CustomerController extends Controller
{
    /**
     * Display a listing of customers with search, filter, pagination
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $query = Customer::with(['group', 'orders' => function($q) {
                $q->latest()->take(1);
            }]);

            // Search functionality
            if ($request->filled('search')) {
                $query->search($request->search);
            }

            // Filter by group
            if ($request->filled('group_id')) {
                $query->byGroup($request->group_id);
            }

            // Filter by status
            if ($request->filled('status')) {
                $query->where('status', $request->status);
            }

            // Filter by customer type
            if ($request->filled('customer_type')) {
                $query->where('customer_type', $request->customer_type);
            }

            // Sort functionality
            $sortField = $request->get('sort_field', 'created_at');
            $sortOrder = $request->get('sort_order', 'desc');
            
            $allowedSorts = ['id', 'code', 'name', 'total_spend', 'total_orders', 'created_at'];
            if (in_array($sortField, $allowedSorts)) {
                $query->orderBy($sortField, $sortOrder);
            }

            // Pagination
            $perPage = $request->get('per_page', 20);
            $customers = $query->paginate($perPage);

            // Transform data for frontend
            $customers->getCollection()->transform(function ($customer) {
                return [
                    'id' => $customer->id,
                    'code' => $customer->code,
                    'name' => $customer->name,
                    'group_name' => $customer->group?->name ?? 'Bán lẻ',
                    'email' => $customer->email,
                    'phone' => $customer->phone,
                    'total_debt' => $customer->total_debt,
                    'formatted_total_spend' => $customer->formatted_total_spend,
                    'total_orders' => $customer->total_orders,
                    'status' => $customer->status,
                    'status_text' => $customer->status_text,
                    'status_color' => $customer->status_color,
                    'customer_type' => $customer->customer_type,
                    'person_in_charge' => $customer->person_in_charge,
                    'tax_code' => $customer->tax_code,
                    'last_order_date' => $customer->getLastOrderDate(),
                    'created_at' => $customer->created_at->format('d/m/Y'),
                    'updated_at' => $customer->updated_at->format('d/m/Y H:i')
                ];
            });

            return response()->json([
                'success' => true,
                'data' => $customers,
                'message' => 'Danh sách khách hàng'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Lỗi khi tải danh sách khách hàng: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Show the form for creating a new customer
     */
    public function create(): JsonResponse
    {
        try {
            $customerGroups = CustomerGroup::all();
            
            return response()->json([
                'success' => true,
                'data' => [
                    'customer_groups' => $customerGroups,
                    'generated_code' => Customer::generateCode()
                ],
                'message' => 'Dữ liệu tạo khách hàng mới'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Lỗi khi tải dữ liệu: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Store a newly created customer
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'name' => 'required|string|max:255',
                'code' => 'nullable|string|max:255|unique:customers,code',
                'group_id' => 'nullable|exists:customer_groups,id',
                'email' => 'nullable|email|max:255|unique:customers,email',
                'phone' => 'nullable|string|max:255',
                'birthday' => 'nullable|date',
                'gender' => 'nullable|in:male,female,other',
                'tax_code' => 'nullable|string|max:255',
                'website' => 'nullable|url|max:255',
                'customer_type' => 'nullable|string|max:255',
                'person_in_charge' => 'nullable|string|max:255',
                'tags' => 'nullable|string',
                'note' => 'nullable|string'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Dữ liệu không hợp lệ',
                    'errors' => $validator->errors()
                ], 422);
            }

            DB::beginTransaction();

            $customer = Customer::create($request->all());

            DB::commit();

            return response()->json([
                'success' => true,
                'data' => $customer,
                'message' => 'Thêm khách hàng thành công'
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            
            return response()->json([
                'success' => false,
                'message' => 'Lỗi khi thêm khách hàng: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified customer
     */
    public function show(Customer $customer): JsonResponse
    {
        try {
            $customer->load([
                'group', 
                'addresses',
                'orders' => function($q) {
                    $q->latest()->take(10);
                },
                'debts' => function($q) {
                    $q->latest()->take(10);
                }
            ]);

            $customerData = [
                'id' => $customer->id,
                'code' => $customer->code,
                'name' => $customer->name,
                'group_id' => $customer->group_id,
                'group_name' => $customer->group?->name ?? 'Bán lẻ',
                'email' => $customer->email,
                'phone' => $customer->phone,
                'birthday' => $customer->birthday?->format('Y-m-d'),
                'gender' => $customer->gender,
                'tax_code' => $customer->tax_code,
                'website' => $customer->website,
                'status' => $customer->status,
                'status_text' => $customer->status_text,
                'total_spend' => $customer->total_spend,
                'formatted_total_spend' => $customer->formatted_total_spend,
                'total_orders' => $customer->total_orders,
                'customer_type' => $customer->customer_type,
                'person_in_charge' => $customer->person_in_charge,
                'tags' => $customer->tags,
                'note' => $customer->note,
                'current_debt' => $customer->getCurrentDebt(),
                'last_order_date' => $customer->getLastOrderDate(),
                'created_at' => $customer->created_at->format('d/m/Y'),
                'updated_at' => $customer->updated_at->format('d/m/Y H:i'),
                
                // Related data
                'addresses' => $customer->addresses,
                'recent_orders' => $customer->orders,
                'recent_debts' => $customer->debts
            ];

            return response()->json([
                'success' => true,
                'data' => $customerData,
                'message' => 'Chi tiết khách hàng'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Lỗi khi tải chi tiết khách hàng: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Show the form for editing the specified customer
     */
    public function edit(Customer $customer): JsonResponse
    {
        try {
            $customerGroups = CustomerGroup::all();
            
            return response()->json([
                'success' => true,
                'data' => [
                    'customer' => $customer,
                    'customer_groups' => $customerGroups
                ],
                'message' => 'Dữ liệu chỉnh sửa khách hàng'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Lỗi khi tải dữ liệu: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update the specified customer
     */
    public function update(Request $request, Customer $customer): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'name' => 'required|string|max:255',
                'code' => ['nullable', 'string', 'max:255', Rule::unique('customers')->ignore($customer->id)],
                'group_id' => 'nullable|exists:customer_groups,id',
                'email' => ['nullable', 'email', 'max:255', Rule::unique('customers')->ignore($customer->id)],
                'phone' => 'nullable|string|max:255',
                'birthday' => 'nullable|date',
                'gender' => 'nullable|in:male,female,other',
                'tax_code' => 'nullable|string|max:255',
                'website' => 'nullable|url|max:255',
                'status' => 'required|in:active,inactive',
                'customer_type' => 'nullable|string|max:255',
                'person_in_charge' => 'nullable|string|max:255',
                'tags' => 'nullable|string',
                'note' => 'nullable|string'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Dữ liệu không hợp lệ',
                    'errors' => $validator->errors()
                ], 422);
            }

            DB::beginTransaction();

            $customer->update($request->all());

            DB::commit();

            return response()->json([
                'success' => true,
                'data' => $customer,
                'message' => 'Cập nhật khách hàng thành công'
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            
            return response()->json([
                'success' => false,
                'message' => 'Lỗi khi cập nhật khách hàng: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove the specified customer
     */
    public function destroy(Customer $customer): JsonResponse
    {
        try {
            // Check if customer has orders
            if ($customer->orders()->exists()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Không thể xóa khách hàng đã có đơn hàng'
                ], 400);
            }

            DB::beginTransaction();

            $customer->delete();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Xóa khách hàng thành công'
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            
            return response()->json([
                'success' => false,
                'message' => 'Lỗi khi xóa khách hàng: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Bulk delete customers
     */
    public function bulkDelete(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'ids' => 'required|array|min:1',
                'ids.*' => 'exists:customers,id'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Dữ liệu không hợp lệ',
                    'errors' => $validator->errors()
                ], 422);
            }

            DB::beginTransaction();

            // Check if any customer has orders
            $customersWithOrders = Customer::whereIn('id', $request->ids)
                ->whereHas('orders')
                ->count();

            if ($customersWithOrders > 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'Không thể xóa khách hàng đã có đơn hàng'
                ], 400);
            }

            $deletedCount = Customer::whereIn('id', $request->ids)->delete();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => "Đã xóa {$deletedCount} khách hàng"
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            
            return response()->json([
                'success' => false,
                'message' => 'Lỗi khi xóa khách hàng: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Export customers to CSV
     */
    public function export(Request $request): JsonResponse
    {
        try {
            $query = Customer::with('group');

            // Apply same filters as index
            if ($request->filled('search')) {
                $query->search($request->search);
            }

            if ($request->filled('group_id')) {
                $query->byGroup($request->group_id);
            }

            if ($request->filled('status')) {
                $query->where('status', $request->status);
            }

            $customers = $query->get();

            $csvData = [];
            $csvData[] = [
                'Mã khách hàng',
                'Tên khách hàng', 
                'Nhóm khách hàng',
                'Email',
                'Số điện thoại',
                'Ngày sinh',
                'Giới tính',
                'Mã số thuế',
                'Website',
                'Trạng thái',
                'Tổng chi tiêu',
                'Tổng đơn hàng',
                'Loại khách hàng',
                'Người phụ trách',
                'Ghi chú',
                'Ngày tạo'
            ];

            foreach ($customers as $customer) {
                $csvData[] = [
                    $customer->code,
                    $customer->name,
                    $customer->group?->name ?? 'Bán lẻ',
                    $customer->email,
                    $customer->phone,
                    $customer->birthday?->format('d/m/Y'),
                    $customer->gender,
                    $customer->tax_code,
                    $customer->website,
                    $customer->status_text,
                    $customer->formatted_total_spend,
                    $customer->total_orders,
                    $customer->customer_type,
                    $customer->person_in_charge,
                    $customer->note,
                    $customer->created_at->format('d/m/Y')
                ];
            }

            $filename = 'customers_' . date('Y-m-d_H-i-s') . '.csv';
            $filePath = storage_path('app/exports/' . $filename);

            // Create directory if not exists
            if (!is_dir(dirname($filePath))) {
                mkdir(dirname($filePath), 0755, true);
            }

            // Write CSV file
            $file = fopen($filePath, 'w');
            fputcsv($file, ["\xEF\xBB\xBF"]); // UTF-8 BOM
            
            foreach ($csvData as $row) {
                fputcsv($file, $row);
            }
            fclose($file);

            return response()->json([
                'success' => true,
                'data' => [
                    'filename' => $filename,
                    'path' => $filePath,
                    'download_url' => url('storage/exports/' . $filename)
                ],
                'message' => 'Xuất file thành công'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Lỗi khi xuất file: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Import customers from CSV
     */
    public function import(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'file' => 'required|file|mimes:csv,txt|max:10240' // 10MB max
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'File không hợp lệ',
                    'errors' => $validator->errors()
                ], 422);
            }

            $file = $request->file('file');
            $path = $file->getRealPath();
            $data = array_map('str_getcsv', file($path));

            if (empty($data)) {
                return response()->json([
                    'success' => false,
                    'message' => 'File trống'
                ], 400);
            }

            // Remove header row
            $header = array_shift($data);
            
            DB::beginTransaction();

            $imported = 0;
            $errors = [];

            foreach ($data as $index => $row) {
                try {
                    $customerData = [
                        'code' => $row[0] ?? Customer::generateCode(),
                        'name' => $row[1] ?? '',
                        'email' => $row[2] ?? null,
                        'phone' => $row[3] ?? null,
                        'birthday' => !empty($row[4]) ? date('Y-m-d', strtotime($row[4])) : null,
                        'gender' => $row[5] ?? null,
                        'tax_code' => $row[6] ?? null,
                        'website' => $row[7] ?? null,
                        'customer_type' => $row[8] ?? 'Bán lẻ',
                        'person_in_charge' => $row[9] ?? 'Cao Đức Bình',
                        'note' => $row[10] ?? null
                    ];

                    if (empty($customerData['name'])) {
                        $errors[] = "Dòng " . ($index + 2) . ": Tên khách hàng không được để trống";
                        continue;
                    }

                    Customer::create($customerData);
                    $imported++;

                } catch (\Exception $e) {
                    $errors[] = "Dòng " . ($index + 2) . ": " . $e->getMessage();
                }
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'data' => [
                    'imported' => $imported,
                    'errors' => $errors,
                    'total_rows' => count($data)
                ],
                'message' => "Nhập thành công {$imported} khách hàng"
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            
            return response()->json([
                'success' => false,
                'message' => 'Lỗi khi nhập file: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get customer statistics
     */
    public function statistics(): JsonResponse
    {
        try {
            $stats = [
                'total_customers' => Customer::count(),
                'active_customers' => Customer::active()->count(),
                'inactive_customers' => Customer::inactive()->count(),
                'total_spend' => Customer::sum('total_spend'),
                'total_orders' => Customer::sum('total_orders'),
                'customers_by_type' => Customer::select('customer_type', DB::raw('count(*) as count'))
                    ->groupBy('customer_type')
                    ->get(),
                'customers_by_group' => Customer::with('group')
                    ->select('group_id', DB::raw('count(*) as count'))
                    ->groupBy('group_id')
                    ->get()
            ];

            return response()->json([
                'success' => true,
                'data' => $stats,
                'message' => 'Thống kê khách hàng'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Lỗi khi tải thống kê: ' . $e->getMessage()
            ], 500);
        }
    }
}