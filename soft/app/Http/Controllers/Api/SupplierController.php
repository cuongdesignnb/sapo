<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Supplier;
use App\Models\SupplierGroup;
use App\Models\SupplierDebt;
use App\Models\SupplierContact;
use App\Models\PurchaseReceipt;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SupplierController extends Controller
{
    /**
     * Display a listing of suppliers
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $perPage = $request->get('per_page', 15);
            $search = $request->get('search');
            $sortBy = $request->get('sort_by', 'created_at');
            $sortOrder = $request->get('sort_order', 'desc');

            $query = Supplier::with(['group', 'primaryContact']);

            // Search functionality
            if ($search) {
                $query->search($search);
            }

            // Filter by status
            if ($request->has('status')) {
                $query->status($request->get('status'));
            }

            // Filter by group
            if ($request->has('group_id')) {
                $query->group($request->get('group_id'));
            }

            // Individual field filters
            $filterFields = ['code', 'name', 'email', 'phone', 'person_in_charge'];
            foreach ($filterFields as $field) {
                if ($request->has($field)) {
                    $query->where($field, 'like', '%' . $request->get($field) . '%');
                }
            }

            // Filter by debt status
            if ($request->has('debt_status')) {
                $debtStatus = $request->get('debt_status');
                switch ($debtStatus) {
                    case 'has_debt':
                        $query->where('total_debt', '>', 0);
                        break;
                    case 'no_debt':
                        $query->where('total_debt', '<=', 0);
                        break;
                    case 'over_limit':
                        $query->whereColumn('total_debt', '>', 'credit_limit');
                        break;
                }
            }

            // Sorting
            $allowedSortFields = ['id', 'code', 'name', 'email', 'phone', 'total_debt', 'created_at', 'updated_at'];
            if (in_array($sortBy, $allowedSortFields)) {
                $query->orderBy($sortBy, $sortOrder);
            }

            $suppliers = $query->paginate($perPage);

            // Transform data
            $suppliers->getCollection()->transform(function ($supplier) {
                return $supplier->full_info;
            });

            return response()->json([
                'success' => true,
                'data' => $suppliers->items(),
                'pagination' => [
                    'current_page' => $suppliers->currentPage(),
                    'last_page' => $suppliers->lastPage(),
                    'per_page' => $suppliers->perPage(),
                    'total' => $suppliers->total(),
                    'from' => $suppliers->firstItem(),
                    'to' => $suppliers->lastItem(),
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Error fetching suppliers: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Có lỗi xảy ra khi tải danh sách nhà cung cấp'
            ], 500);
        }
    }

    /**
     * Store a newly created supplier
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'code' => [
                    'nullable',
                    'string',
                    'max:255',
                    Rule::unique('suppliers', 'code')
                ],
                'name' => 'required|string|max:255',
                'group_id' => 'nullable|exists:supplier_groups,id',
                'email' => 'nullable|email|max:255',
                'phone' => 'nullable|string|max:255',
                'address' => 'nullable|string|max:500',
                'tax_code' => 'nullable|string|max:255',
                'website' => 'nullable|url|max:255',
                'person_in_charge' => 'nullable|string|max:255',
                'bank_account' => 'nullable|string|max:255',
                'bank_name' => 'nullable|string|max:255',
                'status' => 'in:active,inactive,suspended',
                'credit_limit' => 'nullable|numeric|min:0',
                'payment_terms' => 'nullable|integer|min:0',
                'tags' => 'nullable|string',
                'note' => 'nullable|string|max:1000',
                // Contacts
                'contacts' => 'nullable|array',
                'contacts.*.name' => 'required|string|max:255',
                'contacts.*.position' => 'nullable|string|max:255',
                'contacts.*.phone' => 'nullable|string|max:255',
                'contacts.*.email' => 'nullable|email|max:255',
                'contacts.*.department' => 'nullable|string|max:255',
                'contacts.*.is_primary' => 'boolean',
            ], [
                'name.required' => 'Tên nhà cung cấp là bắt buộc',
                'group_id.exists' => 'Nhóm nhà cung cấp không tồn tại',
                'email.email' => 'Email không đúng định dạng',
                'website.url' => 'Website không đúng định dạng',
                'credit_limit.min' => 'Hạn mức tín dụng phải lớn hơn 0',
                'payment_terms.min' => 'Số ngày thanh toán phải lớn hơn 0',
                'contacts.*.name.required' => 'Tên liên hệ là bắt buộc',
                'contacts.*.email.email' => 'Email liên hệ không đúng định dạng',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Dữ liệu không hợp lệ',
                    'errors' => $validator->errors()
                ], 422);
            }

            DB::beginTransaction();

            $supplierData = $validator->validated();
            $contacts = $supplierData['contacts'] ?? [];
            unset($supplierData['contacts']);

            $supplier = Supplier::create($supplierData);

            // Create contacts if provided
            if (!empty($contacts)) {
                foreach ($contacts as $contactData) {
                    $contactData['supplier_id'] = $supplier->id;
                    SupplierContact::create($contactData);
                }
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Tạo nhà cung cấp thành công',
                'data' => $supplier->fresh()->load(['group', 'primaryContact'])->full_info
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error creating supplier: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Có lỗi xảy ra khi tạo nhà cung cấp'
            ], 500);
        }
    }

    /**
     * Display the specified supplier
     */
    public function show(Supplier $supplier): JsonResponse
    {
        try {
            $supplier->load(['group', 'contacts', 'debts.creator', 'purchaseOrders']);

            $data = $supplier->full_info;
            
            // Add detailed information
            $data['contacts'] = $supplier->contacts->map(function ($contact) {
                return [
                    'id' => $contact->id,
                    'name' => $contact->name,
                    'position' => $contact->position,
                    'phone' => $contact->phone,
                    'email' => $contact->email,
                    'department' => $contact->department,
                    'is_primary' => $contact->is_primary,
                    'note' => $contact->note,
                ];
            });

            // Recent debt transactions
            $data['recent_debts'] = $supplier->debts()
                ->orderBy('recorded_at', 'desc')
                ->limit(10)
                ->get()
                ->map(function ($debt) {
                    return [
                        'id' => $debt->id,
                        'ref_code' => $debt->ref_code,
                        'amount' => $debt->amount,
                        'debt_total' => $debt->debt_total,
                        'type' => $debt->type,
                        'note' => $debt->note,
                        'recorded_at' => $debt->recorded_at?->format('d/m/Y H:i'),
                        'created_by' => $debt->creator?->name,
                    ];
                });

            // Recent purchase orders
            $data['recent_purchase_orders'] = $supplier->purchaseOrders()
                ->orderBy('created_at', 'desc')
                ->limit(5)
                ->get()
                ->map(function ($order) {
                    return [
                        'id' => $order->id,
                        'code' => $order->code,
                        'status' => $order->status,
                        'total' => $order->total,
                        'need_pay' => $order->need_pay,
                        'paid' => $order->paid,
                        'expected_at' => $order->expected_at?->format('d/m/Y'),
                        'created_at' => $order->created_at?->format('d/m/Y H:i'),
                    ];
                });

            return response()->json([
                'success' => true,
                'data' => $data
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching supplier: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Có lỗi xảy ra khi tải thông tin nhà cung cấp'
            ], 500);
        }
    }

    /**
     * Update the specified supplier
     */
    public function update(Request $request, Supplier $supplier): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'code' => [
                    'nullable',
                    'string',
                    'max:255',
                    Rule::unique('suppliers', 'code')->ignore($supplier->id)
                ],
                'name' => 'required|string|max:255',
                'group_id' => 'nullable|exists:supplier_groups,id',
                'email' => 'nullable|email|max:255',
                'phone' => 'nullable|string|max:255',
                'address' => 'nullable|string|max:500',
                'tax_code' => 'nullable|string|max:255',
                'website' => 'nullable|url|max:255',
                'person_in_charge' => 'nullable|string|max:255',
                'bank_account' => 'nullable|string|max:255',
                'bank_name' => 'nullable|string|max:255',
                'status' => 'in:active,inactive,suspended',
                'credit_limit' => 'nullable|numeric|min:0',
                'payment_terms' => 'nullable|integer|min:0',
                'tags' => 'nullable|string',
                'note' => 'nullable|string|max:1000',
                // Contacts
                'contacts' => 'nullable|array',
                'contacts.*.id' => 'nullable|exists:supplier_contacts,id',
                'contacts.*.name' => 'required|string|max:255',
                'contacts.*.position' => 'nullable|string|max:255',
                'contacts.*.phone' => 'nullable|string|max:255',
                'contacts.*.email' => 'nullable|email|max:255',
                'contacts.*.department' => 'nullable|string|max:255',
                'contacts.*.is_primary' => 'boolean',
            ], [
                'name.required' => 'Tên nhà cung cấp là bắt buộc',
                'group_id.exists' => 'Nhóm nhà cung cấp không tồn tại',
                'email.email' => 'Email không đúng định dạng',
                'website.url' => 'Website không đúng định dạng',
                'credit_limit.min' => 'Hạn mức tín dụng phải lớn hơn 0',
                'payment_terms.min' => 'Số ngày thanh toán phải lớn hơn 0',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Dữ liệu không hợp lệ',
                    'errors' => $validator->errors()
                ], 422);
            }

            DB::beginTransaction();

            $supplierData = $validator->validated();
            $contacts = $supplierData['contacts'] ?? [];
            unset($supplierData['contacts']);

            $supplier->update($supplierData);

            // Update contacts
            if (isset($contacts)) {
                $contactIds = [];
                
                foreach ($contacts as $contactData) {
                    if (isset($contactData['id'])) {
                        // Update existing contact
                        $contact = SupplierContact::find($contactData['id']);
                        if ($contact && $contact->supplier_id == $supplier->id) {
                            $contact->update($contactData);
                            $contactIds[] = $contact->id;
                        }
                    } else {
                        // Create new contact
                        $contactData['supplier_id'] = $supplier->id;
                        $contact = SupplierContact::create($contactData);
                        $contactIds[] = $contact->id;
                    }
                }

                // Delete contacts not in the list
                $supplier->contacts()->whereNotIn('id', $contactIds)->delete();
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Cập nhật nhà cung cấp thành công',
                'data' => $supplier->fresh()->load(['group', 'primaryContact'])->full_info
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error updating supplier: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Có lỗi xảy ra khi cập nhật nhà cung cấp'
            ], 500);
        }
    }

    /**
     * Remove the specified supplier
     */
    public function destroy(Supplier $supplier): JsonResponse
    {
        try {
            // Check if supplier has related data
            if ($supplier->hasRelatedData()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Không thể xóa nhà cung cấp này vì đã có dữ liệu liên quan'
                ], 409);
            }

            $supplier->delete();

            return response()->json([
                'success' => true,
                'message' => 'Xóa nhà cung cấp thành công'
            ]);

        } catch (\Exception $e) {
            Log::error('Error deleting supplier: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Có lỗi xảy ra khi xóa nhà cung cấp'
            ], 500);
        }
    }

    /**
     * Bulk delete suppliers
     */
    public function bulkDelete(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'ids' => 'required|array|min:1',
                'ids.*' => 'required|integer|exists:suppliers,id'
            ], [
                'ids.required' => 'Danh sách ID là bắt buộc',
                'ids.array' => 'Danh sách ID phải là mảng',
                'ids.min' => 'Phải chọn ít nhất 1 nhà cung cấp',
                'ids.*.exists' => 'Nhà cung cấp không tồn tại'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Dữ liệu không hợp lệ',
                    'errors' => $validator->errors()
                ], 422);
            }

            $suppliers = Supplier::whereIn('id', $request->ids)->get();
            $cannotDelete = [];
            $deleted = 0;

            DB::beginTransaction();

            foreach ($suppliers as $supplier) {
                if ($supplier->hasRelatedData()) {
                    $cannotDelete[] = $supplier->name;
                } else {
                    $supplier->delete();
                    $deleted++;
                }
            }

            DB::commit();

            $message = "Đã xóa {$deleted} nhà cung cấp";
            if (!empty($cannotDelete)) {
                $message .= ". Không thể xóa: " . implode(', ', $cannotDelete) . " (có dữ liệu liên quan)";
            }

            return response()->json([
                'success' => true,
                'message' => $message,
                'deleted_count' => $deleted,
                'cannot_delete' => $cannotDelete
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error bulk deleting suppliers: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Có lỗi xảy ra khi xóa nhà cung cấp'
            ], 500);
        }
    }
    
    /**
     * Get supplier purchase history (only approved/completed records)
     */
    public function purchaseHistory(Request $request, Supplier $supplier): JsonResponse
    {
        try {
            $perPage = $request->get('per_page', 15);
            $type = $request->get('type'); // 'orders', 'receipts', 'returns', 'all'
            $fromDate = $request->get('from_date');
            $toDate = $request->get('to_date');

            $history = collect();

            // 1. Purchase Orders (chỉ lấy đã duyệt/hoàn thành)
            if (!$type || $type === 'orders' || $type === 'all') {
                $ordersQuery = $supplier->purchaseOrders()
                    ->with(['warehouse', 'items.product'])
                    ->whereIn('status', ['approved', 'received', 'completed']);

                if ($fromDate) {
                    $ordersQuery->where('expected_at', '>=', $fromDate);
                }
                if ($toDate) {
                    $ordersQuery->where('expected_at', '<=', $toDate);
                }

                $orders = $ordersQuery->get()->map(function ($order) {
                    return [
                        'id' => $order->id,
                        'type' => 'purchase_order',
                        'type_text' => 'Đơn đặt hàng',
                        'code' => $order->code,
                        'status' => $order->status,
                        'status_text' => $this->getPurchaseOrderStatusText($order->status),
                        'status_color' => $this->getPurchaseOrderStatusColor($order->status),
                        'total' => $order->total,
                        'paid' => $order->paid,
                        'need_pay' => $order->need_pay,
                        'warehouse' => $order->warehouse ? [
                            'id' => $order->warehouse->id,
                            'name' => $order->warehouse->name,
                        ] : null,
                        'items_count' => $order->items->count(),
                        'products_summary' => $order->items->take(3)->map(function ($item) {
                            return $item->product->name . ' (SL: ' . $item->quantity . ')';
                        })->implode(', '),
                        'date' => $order->expected_at,
                        'formatted_date' => $order->expected_at?->format('d/m/Y'),
                        'note' => $order->note,
                        'created_at' => $order->created_at,
                        'sort_date' => $order->expected_at ?: $order->created_at,
                    ];
                });

                $history = $history->merge($orders);
            }

            // 2. Purchase Receipts (chỉ lấy đã hoàn thành)
            if (!$type || $type === 'receipts' || $type === 'all') {
                $receiptsQuery = \App\Models\PurchaseReceipt::with(['purchaseOrder', 'warehouse', 'items.product'])
                    ->whereHas('purchaseOrder', function ($q) use ($supplier) {
                        $q->where('supplier_id', $supplier->id);
                    })
                    ->where('status', 'completed');

                if ($fromDate) {
                    $receiptsQuery->where('received_at', '>=', $fromDate);
                }
                if ($toDate) {
                    $receiptsQuery->where('received_at', '<=', $toDate);
                }

                $receipts = $receiptsQuery->get()->map(function ($receipt) {
                    return [
                        'id' => $receipt->id,
                        'type' => 'purchase_receipt',
                        'type_text' => 'Phiếu nhập kho',
                        'code' => $receipt->code,
                        'status' => $receipt->status,
                        'status_text' => 'Đã nhập kho',
                        'status_color' => 'success',
                        'total' => $receipt->total_amount,
                        'warehouse' => $receipt->warehouse ? [
                            'id' => $receipt->warehouse->id,
                            'name' => $receipt->warehouse->name,
                        ] : null,
                        'received_by' => $receipt->received_by ? [
                            'id' => $receipt->received_by,
                            'name' => 'User #' . $receipt->received_by, // tạm thời
                        ] : null,
                        'purchase_order_code' => $receipt->purchaseOrder?->code,
                        'items_count' => $receipt->items->count(),
                        'products_summary' => $receipt->items->take(3)->map(function ($item) {
                            return $item->product->name . ' (SL: ' . $item->quantity_received . ')';
                        })->implode(', '),
                        'date' => $receipt->received_at,
                        'formatted_date' => $receipt->received_at?->format('d/m/Y H:i'),
                        'note' => $receipt->note,
                        'created_at' => $receipt->created_at,
                        'sort_date' => $receipt->received_at ?: $receipt->created_at,
                    ];
                });

                $history = $history->merge($receipts);
            }

            // 3. Purchase Return Orders (chỉ lấy đã duyệt/hoàn thành)
            if (!$type || $type === 'returns' || $type === 'all') {
                $returnsQuery = $supplier->purchaseReturnOrders()
                    ->with(['warehouse', 'items.product'])
                    ->whereIn('status', ['approved', 'returned', 'completed']);

                if ($fromDate) {
                    $returnsQuery->where('returned_at', '>=', $fromDate);
                }
                if ($toDate) {
                    $returnsQuery->where('returned_at', '<=', $toDate);
                }

                $returns = $returnsQuery->get()->map(function ($return) {
                    return [
                        'id' => $return->id,
                        'type' => 'purchase_return',
                        'type_text' => 'Đơn trả hàng',
                        'code' => $return->code,
                        'status' => $return->status,
                        'status_text' => $this->getPurchaseReturnStatusText($return->status),
                        'status_color' => $this->getPurchaseReturnStatusColor($return->status),
                        'total' => $return->total,
                        'refunded' => $return->refunded,
                        'need_refund' => $return->need_refund,
                        'warehouse' => $return->warehouse ? [
                            'id' => $return->warehouse->id,
                            'name' => $return->warehouse->name,
                        ] : null,
                        'return_reason' => $return->return_reason,
                        'items_count' => $return->items->count(),
                        'products_summary' => $return->items->take(3)->map(function ($item) {
                            return $item->product->name . ' (SL: ' . $item->quantity . ')';
                        })->implode(', '),
                        'date' => $return->returned_at,
                        'formatted_date' => $return->returned_at?->format('d/m/Y'),
                        'note' => $return->note,
                        'created_at' => $return->created_at,
                        'sort_date' => $return->returned_at ?: $return->created_at,
                    ];
                });

                $history = $history->merge($returns);
            }

            // Sort by date (newest first)
            $history = $history->sortByDesc('sort_date')->values();

            // Manual pagination
            $total = $history->count();
            $currentPage = $request->get('page', 1);
            $offset = ($currentPage - 1) * $perPage;
            $items = $history->slice($offset, $perPage)->values();

            return response()->json([
                'success' => true,
                'data' => $items,
                'pagination' => [
                    'current_page' => $currentPage,
                    'last_page' => ceil($total / $perPage),
                    'per_page' => $perPage,
                    'total' => $total,
                    'from' => $offset + 1,
                    'to' => min($offset + $perPage, $total),
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Error fetching supplier purchase history: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Có lỗi xảy ra khi tải lịch sử nhập hàng'
            ], 500);
        }
    }

    /**
     * Helper methods for status text and colors
     */
    private function getPurchaseOrderStatusText($status): string
    {
        return match($status) {
            'pending' => 'Chờ duyệt',
            'approved' => 'Đã duyệt',
            'ordered' => 'Đã đặt hàng',
            'partial' => 'Nhận một phần',
            'received' => 'Đã nhận hàng',
            'completed' => 'Hoàn thành',
            'cancelled' => 'Đã hủy',
            default => 'Không xác định'
        };
    }

    private function getPurchaseOrderStatusColor($status): string
    {
        return match($status) {
            'pending' => 'warning',
            'approved' => 'info',
            'ordered' => 'primary',
            'partial' => 'warning',
            'received' => 'success',
            'completed' => 'success',
            'cancelled' => 'danger',
            default => 'secondary'
        };
    }

    private function getPurchaseReturnStatusText($status): string
    {
        return match($status) {
            'pending' => 'Chờ duyệt',
            'approved' => 'Đã duyệt',
            'returned' => 'Đã trả hàng',
            'completed' => 'Hoàn thành',
            'cancelled' => 'Đã hủy',
            default => 'Không xác định'
        };
    }

    private function getPurchaseReturnStatusColor($status): string
    {
        return match($status) {
            'pending' => 'warning',
            'approved' => 'info',
            'returned' => 'primary',
            'completed' => 'success',
            'cancelled' => 'danger',
            default => 'secondary'
        };
    }

    /**
     * Add debt record for supplier
     */
    public function addDebt(Request $request, Supplier $supplier): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'amount' => 'required|numeric',
                'type' => 'required|in:purchase,payment,adjustment',
                'ref_code' => 'nullable|string|max:255',
                'purchase_order_id' => 'nullable|exists:purchase_orders,id',
                'note' => 'nullable|string|max:1000',
            ], [
                'amount.required' => 'Số tiền là bắt buộc',
                'amount.numeric' => 'Số tiền phải là số',
                'type.required' => 'Loại giao dịch là bắt buộc',
                'type.in' => 'Loại giao dịch không hợp lệ',
                'purchase_order_id.exists' => 'Đơn hàng không tồn tại',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Dữ liệu không hợp lệ',
                    'errors' => $validator->errors()
                ], 422);
            }

            DB::beginTransaction();

            $debt = $supplier->addDebt(
                $request->amount,
                $request->type,
                $request->ref_code,
                $request->purchase_order_id,
                $request->note
            );

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Thêm giao dịch công nợ thành công',
                'data' => [
                    'debt' => [
                        'id' => $debt->id,
                        'amount' => $debt->amount,
                        'debt_total' => $debt->debt_total,
                        'type' => $debt->type,
                        'recorded_at' => $debt->recorded_at?->format('d/m/Y H:i'),
                    ],
                    'supplier_debt_total' => $supplier->fresh()->total_debt
                ]
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error adding supplier debt: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Có lỗi xảy ra khi thêm giao dịch công nợ'
            ], 500);
        }
    }

    public function debtHistory(Request $request, Supplier $supplier): JsonResponse
{
    try {
        $perPage = $request->get('per_page', 15);
        $type = $request->get('type');
        $fromDate = $request->get('from_date');
        $toDate = $request->get('to_date');

        $query = $supplier->debts()->with('creator');

        if ($type) {
            $query->where('type', $type);
        }

        if ($fromDate) {
            $query->where('recorded_at', '>=', $fromDate);
        }

        if ($toDate) {
            $query->where('recorded_at', '<=', $toDate);
        }

        // Sắp xếp theo thời gian giảm dần (mới nhất trước)
        $debts = $query->orderBy('recorded_at', 'desc')
                      ->orderBy('id', 'desc')
                      ->paginate($perPage);

        $debts->getCollection()->transform(function ($debt) {
            return [
                'id' => $debt->id,
                'ref_code' => $debt->ref_code,
                'amount' => $debt->amount,
                'debt_total' => $debt->debt_total,
                'type' => $debt->type,
                'note' => $debt->note,
                'recorded_at' => $debt->recorded_at ? 
                    \Carbon\Carbon::parse($debt->recorded_at)->format('d/m/Y H:i') : 
                    null,                'created_by' => $debt->creator?->name,
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $debts->items(),
            'pagination' => [
                'current_page' => $debts->currentPage(),
                'last_page' => $debts->lastPage(),
                'per_page' => $debts->perPage(),
                'total' => $debts->total(),
                'from' => $debts->firstItem(),
                'to' => $debts->lastItem(),
            ]
        ]);

    } catch (\Exception $e) {
        Log::error('Error fetching supplier debt history: ' . $e->getMessage());
        return response()->json([
            'success' => false,
            'message' => 'Có lỗi xảy ra khi tải lịch sử công nợ'
        ], 500);
    }
}
    
    /**
     * Export supplier purchase history to Excel
     */
    public function exportPurchaseHistory(Request $request, Supplier $supplier): StreamedResponse
    {
        try {
            $type = $request->get('type', 'all');
            $fromDate = $request->get('from_date');
            $toDate = $request->get('to_date');

            $history = collect();

            // 1. Purchase Orders (chỉ lấy đã duyệt/hoàn thành)
            if (!$type || $type === 'orders' || $type === 'all') {
                $ordersQuery = $supplier->purchaseOrders()
                    ->with(['warehouse', 'items.product'])
                    ->whereIn('status', ['approved', 'received', 'completed']);

                if ($fromDate) {
                    $ordersQuery->where('expected_at', '>=', $fromDate);
                }
                if ($toDate) {
                    $ordersQuery->where('expected_at', '<=', $toDate);
                }

                $orders = $ordersQuery->get()->map(function ($order) {
                    return [
                        'type' => 'purchase_order',
                        'type_text' => 'Đơn đặt hàng',
                        'code' => $order->code,
                        'status' => $order->status,
                        'status_text' => $this->getPurchaseOrderStatusText($order->status),
                        'total' => $order->total,
                        'paid' => $order->paid,
                        'need_pay' => $order->need_pay,
                        'warehouse_name' => $order->warehouse?->name,
                        'items_count' => $order->items->count(),
                        'products_summary' => $order->items->take(5)->map(function ($item) {
                            return $item->product->name . ' (SL: ' . $item->quantity . ')';
                        })->implode('; '),
                        'date' => $order->expected_at,
                        'formatted_date' => $order->expected_at?->format('d/m/Y'),
                        'note' => $order->note,
                        'sort_date' => $order->expected_at ?: $order->created_at,
                    ];
                });

                $history = $history->merge($orders);
            }

            // 2. Purchase Receipts (chỉ lấy đã hoàn thành)
            if (!$type || $type === 'receipts' || $type === 'all') {
                $receiptsQuery = \App\Models\PurchaseReceipt::with(['purchaseOrder', 'warehouse', 'items.product'])
                    ->whereHas('purchaseOrder', function ($q) use ($supplier) {
                        $q->where('supplier_id', $supplier->id);
                    })
                    ->where('status', 'completed');

                if ($fromDate) {
                    $receiptsQuery->where('received_at', '>=', $fromDate);
                }
                if ($toDate) {
                    $receiptsQuery->where('received_at', '<=', $toDate);
                }

                $receipts = $receiptsQuery->get()->map(function ($receipt) {
                    return [
                        'type' => 'purchase_receipt',
                        'type_text' => 'Phiếu nhập kho',
                        'code' => $receipt->code,
                        'status' => $receipt->status,
                        'status_text' => 'Đã nhập kho',
                        'total' => $receipt->total_amount,
                        'paid' => null,
                        'need_pay' => null,
                        'warehouse_name' => $receipt->warehouse?->name,
                        'items_count' => $receipt->items->count(),
                        'products_summary' => $receipt->items->take(5)->map(function ($item) {
                            return $item->product->name . ' (SL: ' . $item->quantity_received . ')';
                        })->implode('; '),
                        'date' => $receipt->received_at,
                        'formatted_date' => $receipt->received_at?->format('d/m/Y H:i'),
                        'note' => $receipt->note,
                        'sort_date' => $receipt->received_at ?: $receipt->created_at,
                    ];
                });

                $history = $history->merge($receipts);
            }

            // 3. Purchase Return Orders (chỉ lấy đã duyệt/hoàn thành)
            if (!$type || $type === 'returns' || $type === 'all') {
                $returnsQuery = $supplier->purchaseReturnOrders()
                    ->with(['warehouse', 'items.product'])
                    ->whereIn('status', ['approved', 'returned', 'completed']);

                if ($fromDate) {
                    $returnsQuery->where('returned_at', '>=', $fromDate);
                }
                if ($toDate) {
                    $returnsQuery->where('returned_at', '<=', $toDate);
                }

                $returns = $returnsQuery->get()->map(function ($return) {
                    return [
                        'type' => 'purchase_return',
                        'type_text' => 'Đơn trả hàng',
                        'code' => $return->code,
                        'status' => $return->status,
                        'status_text' => $this->getPurchaseReturnStatusText($return->status),
                        'total' => $return->total,
                        'paid' => null,
                        'need_pay' => $return->need_refund,
                        'warehouse_name' => $return->warehouse?->name,
                        'items_count' => $return->items->count(),
                        'products_summary' => $return->items->take(5)->map(function ($item) {
                            return $item->product->name . ' (SL: ' . $item->quantity . ')';
                        })->implode('; '),
                        'date' => $return->returned_at,
                        'formatted_date' => $return->returned_at?->format('d/m/Y'),
                        'note' => $return->note,
                        'sort_date' => $return->returned_at ?: $return->created_at,
                    ];
                });

                $history = $history->merge($returns);
            }

            // Sort by date (newest first)
            $history = $history->sortByDesc('sort_date')->values();

            $fileName = 'lich-su-nhap-hang-' . $supplier->code . '-' . date('Y-m-d_H-i-s') . '.csv';

            return response()->streamDownload(function () use ($history, $supplier) {
                $handle = fopen('php://output', 'w');
                
                // Add BOM for UTF-8
                fputs($handle, "\xEF\xBB\xBF");
                
                // Headers
                fputcsv($handle, [
                    'Nhà cung cấp',
                    'Mã phiếu',
                    'Loại giao dịch',
                    'Trạng thái',
                    'Ngày',
                    'Kho',
                    'Số sản phẩm',
                    'Sản phẩm',
                    'Tổng tiền',
                    'Đã thanh toán',
                    'Cần thanh toán',
                    'Ghi chú'
                ]);

                // Data
                foreach ($history as $item) {
                    fputcsv($handle, [
                        $supplier->name,
                        $item['code'],
                        $item['type_text'],
                        $item['status_text'],
                        $item['formatted_date'],
                        $item['warehouse_name'] ?: '',
                        $item['items_count'],
                        $item['products_summary'],
                        $item['total'] ?: 0,
                        $item['paid'] ?: 0,
                        $item['need_pay'] ?: 0,
                        $item['note'] ?: ''
                    ]);
                }

                fclose($handle);
            }, $fileName, [
                'Content-Type' => 'text/csv; charset=UTF-8',
                'Content-Disposition' => "attachment; filename=\"{$fileName}\"",
            ]);

        } catch (\Exception $e) {
            Log::error('Error exporting supplier purchase history: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Có lỗi xảy ra khi xuất dữ liệu'
            ], 500);
        }
    }

    /**
     * Export suppliers to CSV
     */
    public function export(Request $request): StreamedResponse
    {
        $search = $request->get('search');
        $groupId = $request->get('group_id');
        $status = $request->get('status');
        
        $query = Supplier::with('group');
        
        if ($search) {
            $query->search($search);
        }
        
        if ($groupId) {
            $query->group($groupId);
        }
        
        if ($status) {
            $query->status($status);
        }

        $fileName = 'suppliers_' . date('Y-m-d_H-i-s') . '.csv';

        return response()->streamDownload(function () use ($query) {
            $handle = fopen('php://output', 'w');
            
            // Add BOM for UTF-8
            fputs($handle, "\xEF\xBB\xBF");
            
            // Headers
            fputcsv($handle, [
                'Mã nhà cung cấp',
                'Tên nhà cung cấp',
                'Nhóm',
                'Email',
                'Số điện thoại',
                'Địa chỉ',
                'Mã số thuế',
                'Website',
                'Người phụ trách',
                'Tài khoản ngân hàng',
                'Ngân hàng',
                'Trạng thái',
                'Tổng nợ',
                'Hạn mức tín dụng',
                'Số ngày thanh toán',
                'Ghi chú',
                'Ngày tạo'
            ]);

            // Data
            $query->chunk(1000, function ($suppliers) use ($handle) {
                foreach ($suppliers as $supplier) {
                    fputcsv($handle, [
                        $supplier->code,
                        $supplier->name,
                        $supplier->group?->name,
                        $supplier->email,
                        $supplier->phone,
                        $supplier->address,
                        $supplier->tax_code,
                        $supplier->website,
                        $supplier->person_in_charge,
                        $supplier->bank_account,
                        $supplier->bank_name,
                        $supplier->status_text,
                        $supplier->total_debt,
                        $supplier->credit_limit,
                        $supplier->payment_terms,
                        $supplier->note,
                        $supplier->created_at?->format('d/m/Y H:i')
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
     * Import suppliers from CSV
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
                    $supplierData = [
                        'code' => !empty($data[0]) ? trim($data[0]) : null,
                        'name' => !empty($data[1]) ? trim($data[1]) : null,
                        'email' => !empty($data[3]) ? trim($data[3]) : null,
                        'phone' => !empty($data[4]) ? trim($data[4]) : null,
                        'address' => !empty($data[5]) ? trim($data[5]) : null,
                        'tax_code' => !empty($data[6]) ? trim($data[6]) : null,
                        'website' => !empty($data[7]) ? trim($data[7]) : null,
                        'person_in_charge' => !empty($data[8]) ? trim($data[8]) : null,
                        'bank_account' => !empty($data[9]) ? trim($data[9]) : null,
                        'bank_name' => !empty($data[10]) ? trim($data[10]) : null,
                        'status' => !empty($data[11]) ? trim($data[11]) : 'active',
                        'credit_limit' => !empty($data[13]) ? (float)$data[13] : 0,
                        'payment_terms' => !empty($data[14]) ? (int)$data[14] : 0,
                        'note' => !empty($data[15]) ? trim($data[15]) : null,
                    ];

                    $validator = Validator::make($supplierData, [
                        'code' => 'nullable|string|max:255|unique:suppliers,code',
                        'name' => 'required|string|max:255',
                        'email' => 'nullable|email|max:255',
                        'phone' => 'nullable|string|max:255',
                        'address' => 'nullable|string|max:500',
                        'tax_code' => 'nullable|string|max:255',
                        'website' => 'nullable|url|max:255',
                        'person_in_charge' => 'nullable|string|max:255',
                        'bank_account' => 'nullable|string|max:255',
                        'bank_name' => 'nullable|string|max:255',
                        'status' => 'in:active,inactive,suspended',
                        'credit_limit' => 'nullable|numeric|min:0',
                        'payment_terms' => 'nullable|integer|min:0',
                        'note' => 'nullable|string|max:1000',
                    ]);

                    if ($validator->fails()) {
                        $errors[] = "Dòng {$row}: " . implode(', ', $validator->errors()->all());
                        continue;
                    }

                    Supplier::create($supplierData);
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
                'message' => "Đã import {$imported} nhà cung cấp",
                'imported_count' => $imported,
                'errors' => $errors
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error importing suppliers: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Có lỗi xảy ra khi import dữ liệu'
            ], 500);
        }
    }

    /**
     * Get suppliers for select options
     */
    public function options(Request $request): JsonResponse
    {
        try {
            $search = $request->get('search');
            $limit = $request->get('limit', 50);

            $query = Supplier::select('id', 'code', 'name', 'status')
                           ->where('status', 'active');

            if ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('code', 'like', "%{$search}%")
                      ->orWhere('name', 'like', "%{$search}%");
                });
            }

            $suppliers = $query->orderBy('name')
                             ->limit($limit)
                             ->get()
                             ->map(function ($supplier) {
                                 return [
                                     'value' => $supplier->id,
                                     'label' => "{$supplier->code} - {$supplier->name}",
                                     'code' => $supplier->code,
                                     'name' => $supplier->name,
                                 ];
                             });

            return response()->json([
                'success' => true,
                'data' => $suppliers
            ]);

        } catch (\Exception $e) {
            Log::error('Error fetching supplier options: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Có lỗi xảy ra khi tải danh sách nhà cung cấp'
            ], 500);
        }
    }

    /**
     * Get supplier groups for select options
     */
    public function groups(Request $request): JsonResponse
    {
        try {
            $search = $request->get('search');

            $query = SupplierGroup::select('id', 'code', 'name', 'type');

            if ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('code', 'like', "%{$search}%")
                      ->orWhere('name', 'like', "%{$search}%");
                });
            }

            $groups = $query->orderBy('name')
                          ->get()
                          ->map(function ($group) {
                              return [
                                  'value' => $group->id,
                                  'label' => $group->name,
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
            Log::error('Error fetching supplier groups: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Có lỗi xảy ra khi tải danh sách nhóm nhà cung cấp'
            ], 500);
        }
    }

    /**
     * Get supplier statistics
     */
    public function statistics(): JsonResponse
    {
        try {
            $stats = [
                'total_suppliers' => Supplier::count(),
                'active_suppliers' => Supplier::where('status', 'active')->count(),
                'inactive_suppliers' => Supplier::where('status', 'inactive')->count(),
                'suspended_suppliers' => Supplier::where('status', 'suspended')->count(),
                'total_debt' => Supplier::sum('total_debt'),
                'suppliers_with_debt' => Supplier::where('total_debt', '>', 0)->count(),
                'over_credit_limit' => Supplier::whereColumn('total_debt', '>', 'credit_limit')->count(),
                'recent_suppliers' => Supplier::where('created_at', '>=', now()->subDays(30))->count(),
            ];

            // Group statistics
            $groupStats = SupplierGroup::withCount('suppliers')->get()->map(function ($group) {
                return [
                    'group_name' => $group->name,
                    'count' => $group->suppliers_count,
                ];
            });

            $stats['by_group'] = $groupStats;

            return response()->json([
                'success' => true,
                'data' => $stats
            ]);

        } catch (\Exception $e) {
            Log::error('Error fetching supplier statistics: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Có lỗi xảy ra khi tải thống kê nhà cung cấp'
            ], 500);
        }
    }
}