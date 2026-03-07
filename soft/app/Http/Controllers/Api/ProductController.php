<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\WarehouseProduct;
use App\Models\PurchaseReceiptItem;
use App\Models\OrderItem; 
use App\Models\PurchaseReturnOrderItem;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;


class ProductController extends Controller
{
    /**
     * Display a listing of products
     */
    public function index(Request $request)
{
    $user = Auth::user();
    
    // Lấy current warehouse từ session
    $currentWarehouseId = $request->get('warehouse_id') ?: session('current_warehouse_id');

    \Log::info('Product Controller Debug:', [
        'session_warehouse' => session('current_warehouse_id'),
        'request_warehouse' => $request->get('warehouse_id'),
        'current_warehouse' => $currentWarehouseId
    ]);
    
    // Check view all mode cho super admin
    $viewAllMode = $request->get('view_all') === 'true' && $user->role->name === 'super_admin';
    
    $query = Product::query();

    // Super admin View All Mode - hiển thị tất cả với breakdown
    if ($user->role->name === 'super_admin' && $viewAllMode) {
        $query->with(['warehouseProducts.warehouse']);
        
        // Search functionality
        if ($request->has('search') && !empty($request->search)) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('sku', 'like', "%{$search}%")
                  ->orWhere('barcode', 'like', "%{$search}%");
            });
        }
        
        $products = $query->paginate($request->get('per_page', 20));
        
        // Transform for global view
        $transformedProducts = $products->getCollection()->map(function($product) {
            $warehouseData = $product->warehouseProducts->map(function($wp) {
                return [
                    'warehouse_id' => $wp->warehouse_id,
                    'warehouse_name' => $wp->warehouse?->name,
                    'stock' => $wp->quantity,
                    'cost' => $wp->cost,
                ];
            });
            
            return array_merge($product->toArray(), [
                'total_stock' => $warehouseData->sum('stock'),
                'warehouses' => $warehouseData,
                'warehouse_count' => $warehouseData->count(),
                'global_view' => true
            ]);
        });
        
        return response()->json([
            'success' => true,
            'data' => $transformedProducts,
            'pagination' => [
                'current_page' => $products->currentPage(),
                'last_page' => $products->lastPage(),
                'per_page' => $products->perPage(),
                'total' => $products->total(),
                'from' => $products->firstItem(),
                'to' => $products->lastItem()
            ],
            'context' => [
                'mode' => 'global_view',
                'view_all' => true
            ]
        ]);
    }
    
    // Warehouse Mode - chỉ hiển thị theo kho cụ thể
    if ($currentWarehouseId) {
        $query->whereHas('warehouseProducts', function($q) use ($currentWarehouseId) {
            $q->where('warehouse_id', $currentWarehouseId);
        });
        
        $query->with(['warehouseProducts' => function($q) use ($currentWarehouseId) {
            $q->where('warehouse_id', $currentWarehouseId);
        }]);
    }

    // Search functionality
    if ($request->has('search') && !empty($request->search)) {
        $search = $request->search;
        $query->where(function($q) use ($search) {
            $q->where('name', 'like', "%{$search}%")
              ->orWhere('sku', 'like', "%{$search}%")
              ->orWhere('barcode', 'like', "%{$search}%");
        });
    }

    // Other filters
    if ($request->has('category_name') && !empty($request->category_name)) {
        $query->where('category_name', $request->category_name);
    }
    
    if ($request->has('status') && !empty($request->status)) {
        $query->where('status', $request->status);
    }

    // Sort
    $sortField = $request->get('sort_field', 'created_at');
    $sortDirection = $request->get('sort_direction', 'desc');
    $query->orderBy($sortField, $sortDirection);

    $products = $query->paginate($request->get('per_page', 20));

    // Transform for warehouse mode
    if ($currentWarehouseId) {
        $transformedProducts = $products->getCollection()->map(function($product) {
            $warehouseStock = $product->warehouseProducts->first();
            
            return array_merge($product->toArray(), [
                'stock' => $warehouseStock?->quantity ?? 0,
                'cost' => $warehouseStock?->cost ?? 0,
                'min_stock' => $warehouseStock?->min_stock ?? 0,
                'max_stock' => $warehouseStock?->max_stock ?? 0,
                'warehouse_mode' => true
            ]);
        });
    } else {
        $transformedProducts = $products->getCollection();
    }

    return response()->json([
        'success' => true,
        'data' => $transformedProducts,
        'pagination' => [
            'current_page' => $products->currentPage(),
            'last_page' => $products->lastPage(),
            'per_page' => $products->perPage(),
            'total' => $products->total(),
            'from' => $products->firstItem(),
            'to' => $products->lastItem()
        ],
        'context' => [
            'mode' => $currentWarehouseId ? 'warehouse_mode' : 'no_warehouse',
            'warehouse_id' => $currentWarehouseId,
            'warehouse_name' => $currentWarehouseId ? \App\Models\Warehouse::find($currentWarehouseId)?->name : null
        ]
    ]);
}
    /**
     * Show a specific product
     */
    public function show($id)
{
    // Debug 1: Kiểm tra product tồn tại
    $product = Product::find($id);
    \Log::info("Step 1 - Product found:", ['id' => $id, 'exists' => $product ? true : false]);

    if (!$product) {
        return response()->json([
            'success' => false,
            'message' => 'Sản phẩm không tồn tại'
        ], 404);
    }

    // Debug 2: Kiểm tra data warehouse_products trực tiếp từ DB
    $warehouseData = \DB::table('warehouse_products')->where('product_id', $id)->get();
    \Log::info("Step 2 - Direct DB query:", ['warehouse_data' => $warehouseData->toArray()]);

    // Debug 3: Load relationship
    $product->load(['warehouseProducts.warehouse']);
    \Log::info("Step 3 - After loading relationship:", ['warehouse_products' => $product->warehouseProducts->toArray()]);

    // Debug 4: Final response
    $response = $product->toArray();
    \Log::info("Step 4 - Final response:", $response);

    return response()->json([
        'success' => true,
        'data' => $product
    ]);
}

    /**
     * Store a new product
     */
    public function store(Request $request)
{
    $validated = $request->validate([
        'sku' => 'required|unique:products,sku',
        'name' => 'required|string|max:255',
        'quantity' => 'integer|min:0',
        'cost_price' => 'numeric|min:0',
        'wholesale_price' => 'numeric|min:0',
        'retail_price' => 'numeric|min:0',
        'supplier_id' => 'nullable|exists:suppliers,id',
        'category_name' => 'nullable|string|max:255',
        'brand_name' => 'nullable|string|max:255',
        'barcode' => 'nullable|string|max:255',
        'weight' => 'nullable|string|max:50',
        'status' => 'in:active,inactive',
        'track_serial' => 'nullable|boolean',
        'note' => 'nullable|string'
    ]);

    // ✅ VALIDATE warehouse_stocks nếu có
    $warehouseStocks = [];
    if ($request->has('warehouse_stocks')) {
        $request->validate([
            'warehouse_stocks' => 'array',
            'warehouse_stocks.*.warehouse_id' => 'required|exists:warehouses,id',
            'warehouse_stocks.*.quantity' => 'required|integer|min:1',
            'warehouse_stocks.*.cost' => 'required|numeric|min:0'
        ]);
        $warehouseStocks = $request->warehouse_stocks;
        
        // Tính lại quantity = tổng warehouse quantities
        $validated['quantity'] = collect($warehouseStocks)->sum('quantity');
    }

    // ✅ CHỈ VALIDATE quantity vs stock cho sản phẩm edit (không phải tạo mới)
    // Khi tạo mới với warehouse_stocks thì skip validation này
    if (isset($validated['quantity']) && $validated['quantity'] > 0 && empty($warehouseStocks)) {
        // Tạo product tạm để dùng accessor
        $tempProduct = new Product();
        $tempProduct->fill($validated);
        
        // Kiểm tra nếu có warehouse stock
        $totalWarehouseStock = $tempProduct->total_warehouse_stock;
        
        if ($totalWarehouseStock > 0 && $validated['quantity'] > $totalWarehouseStock) {
            return response()->json([
                'success' => false,
                'message' => "Số lượng bán ({$validated['quantity']}) vượt quá tồn kho thực tế ({$totalWarehouseStock})",
                'data' => [
                    'max_allowed' => $totalWarehouseStock,
                    'requested' => $validated['quantity'],
                    'warehouse_stock' => $totalWarehouseStock
                ]
            ], 422);
        }
    }

    $product = Product::create($validated);

    // ✅ TẠO WAREHOUSE PRODUCT ENTRIES
    if (!empty($warehouseStocks)) {
        foreach ($warehouseStocks as $stock) {
            \App\Models\WarehouseProduct::create([
                'warehouse_id' => $stock['warehouse_id'],
                'product_id' => $product->id,
                'quantity' => $stock['quantity'],
                'cost' => $stock['cost'],
                'min_stock' => 5, // default
                'max_stock' => 1000, // default
                'reserved_quantity' => 0,
                'last_import_date' => now()
            ]);
        }
        
        \Log::info("Created warehouse entries for product {$product->sku}", [
            'product_id' => $product->id,
            'warehouse_stocks' => $warehouseStocks,
            'total_quantity' => $validated['quantity']
        ]);
    }

    return response()->json([
        'success' => true,
        'message' => 'Tạo sản phẩm thành công',
        'data' => $product
    ], 201);
}

    /**
     * Update a product
     */
    public function update(Request $request, $id)
    {
        $product = Product::find($id);

        if (!$product) {
            return response()->json([
                'success' => false,
                'message' => 'Sản phẩm không tồn tại'
            ], 404);
        }

        $validated = $request->validate([
            'sku' => 'required|unique:products,sku,' . $id,
            'name' => 'required|string|max:255',
            'quantity' => 'integer|min:0',
            'cost_price' => 'numeric|min:0',
            'wholesale_price' => 'numeric|min:0',
            'retail_price' => 'numeric|min:0',
            'supplier_id' => 'nullable|exists:suppliers,id',
            'category_name' => 'nullable|string|max:255',
            'brand_name' => 'nullable|string|max:255',
            'barcode' => 'nullable|string|max:255',
            'weight' => 'nullable|string|max:50',
            'status' => 'in:active,inactive',
            'track_serial' => 'nullable|boolean',
            'note' => 'nullable|string'
        ]);

        if (isset($validated['quantity']) && $validated['quantity'] > 0) {
    $validationResult = $product->validateQuantityAgainstStock($validated['quantity']);
    
    if (!$validationResult['valid']) {
        return response()->json([
            'success' => false,
            'message' => $validationResult['message'],
            'data' => [
                'max_allowed' => $validationResult['max_allowed'],
                'requested' => $validated['quantity'],
                'current_stock' => $validationResult['current_stock']
            ]
        ], 422);
    }
}

$product->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Cập nhật sản phẩm thành công',
            'data' => $product
        ]);
    }

    /**
     * Delete a product
     */
    public function destroy($id)
    {
        $product = Product::find($id);

        if (!$product) {
            return response()->json([
                'success' => false,
                'message' => 'Sản phẩm không tồn tại'
            ], 404);
        }

        $product->delete();

        return response()->json([
            'success' => true,
            'message' => 'Xóa sản phẩm thành công'
        ]);
    }

    /**
     * Bulk delete products
     */
    public function bulkDelete(Request $request)
    {
        try {
            $request->validate([
                'ids' => 'required|array|min:1',
                'ids.*' => 'integer',
            ]);

            // Chỉ lấy IDs thực sự tồn tại
            $ids = Product::whereIn('id', $request->ids)->pluck('id')->toArray();

            if (empty($ids)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Không tìm thấy sản phẩm nào để xóa',
                ], 404);
            }

            // Kiểm tra sản phẩm nào đang được sử dụng trong đơn hàng, phiếu mua...
            $usedInOrders = OrderItem::whereIn('product_id', $ids)->distinct()->pluck('product_id')->toArray();
            $usedInPurchase = PurchaseReceiptItem::whereIn('product_id', $ids)->distinct()->pluck('product_id')->toArray();
            $usedInReturn = PurchaseReturnOrderItem::whereIn('product_id', $ids)->distinct()->pluck('product_id')->toArray();

            $usedIds = array_values(array_unique(array_merge($usedInOrders, $usedInPurchase, $usedInReturn)));
            $deletableIds = array_values(array_diff($ids, $usedIds));

            if (empty($deletableIds) && !empty($usedIds)) {
                // Chi tiết từng sản phẩm bị chặn và lý do
                $details = [];
                $usedProducts = Product::whereIn('id', $usedIds)->pluck('name', 'id')->toArray();

                foreach ($usedIds as $pid) {
                    $reasons = [];
                    if (in_array($pid, $usedInOrders)) {
                        $orderCount = OrderItem::where('product_id', $pid)->distinct()->count('order_id');
                        $reasons[] = "{$orderCount} đơn hàng";
                    }
                    if (in_array($pid, $usedInPurchase)) {
                        $reasons[] = 'phiếu nhập kho';
                    }
                    if (in_array($pid, $usedInReturn)) {
                        $reasons[] = 'phiếu trả hàng';
                    }
                    $details[] = [
                        'id' => $pid,
                        'name' => $usedProducts[$pid] ?? "SP #{$pid}",
                        'reasons' => $reasons,
                    ];
                }

                $blockedCount = count($usedIds);

                return response()->json([
                    'success' => false,
                    'message' => "Không thể xóa {$blockedCount} sản phẩm vì đang được sử dụng trong đơn hàng hoặc phiếu mua. Bạn cần xóa đơn hàng/phiếu mua liên quan trước.",
                    'data' => [
                        'blocked_count' => $blockedCount,
                        'blocked_products' => $details,
                    ],
                ], 422);
            }

            \DB::transaction(function () use ($deletableIds) {
                // Xóa warehouse_products trước (đã có cascade nhưng an toàn hơn)
                WarehouseProduct::whereIn('product_id', $deletableIds)->delete();
                Product::whereIn('id', $deletableIds)->delete();
            });

            $skippedCount = count($usedIds);
            $deletedCount = count($deletableIds);
            $message = "Đã xóa {$deletedCount} sản phẩm thành công";
            if ($skippedCount > 0) {
                $message .= ". Bỏ qua {$skippedCount} sản phẩm đang được sử dụng";
            }

            return response()->json([
                'success' => true,
                'message' => $message,
                'data' => [
                    'deleted' => $deletedCount,
                    'skipped' => $skippedCount,
                    'skipped_ids' => $usedIds,
                ],
            ]);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Dữ liệu không hợp lệ',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            \Log::error('Bulk delete error: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Lỗi khi xóa sản phẩm: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Export products to CSV
     */
    public function export(Request $request)
    {
        try {
            $query = Product::query();
            
            // Apply same filters as index
            if ($request->has('search') && !empty($request->search)) {
                $search = $request->search;
                $query->where(function($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                      ->orWhere('sku', 'like', "%{$search}%")
                      ->orWhere('barcode', 'like', "%{$search}%");
                });
            }
            
            if ($request->has('category_name') && !empty($request->category_name)) {
                $query->where('category_name', $request->category_name);
            }
            
            if ($request->has('brand_name') && !empty($request->brand_name)) {
                $query->where('brand_name', $request->brand_name);
            }
            
            if ($request->has('selected_ids') && !empty($request->selected_ids)) {
                $ids = is_array($request->selected_ids) ? $request->selected_ids : explode(',', $request->selected_ids);
                $query->whereIn('id', $ids);
            }
            
            $products = $query->get();
            
            // Create CSV content
            $csvData = [];
            $csvData[] = ['STT', 'SKU', 'name', 'quantity', 'cost_price', 'wholesale_price', 'retail_price', 'category_name', 'brand_name', 'barcode', 'weight', 'status', 'note'];
            
            foreach ($products as $index => $product) {
                $csvData[] = [
                    $index + 1,
                    $product->sku ?? '',
                    $product->name ?? '',
                    $product->quantity ?? 0,
                    $product->cost_price ?? 0,
                    $product->wholesale_price ?? 0,
                    $product->retail_price ?? 0,
                    $product->category_name ?? '',
                    $product->brand_name ?? '',
                    $product->barcode ?? '',
                    $product->weight ?? '',
                    $product->status ?? 'active',
                    $product->note ?? ''
                ];
            }
            
            $filename = 'products_export_' . date('Y_m_d_H_i_s') . '.csv';
            
            return response()->streamDownload(function() use ($csvData) {
                $file = fopen('php://output', 'w');
                
                // UTF-8 BOM for Excel
                fwrite($file, "\xEF\xBB\xBF");
                
                foreach ($csvData as $row) {
                    fputcsv($file, $row);
                }
                fclose($file);
            }, $filename, [
                'Content-Type' => 'text/csv; charset=UTF-8',
            ]);
            
        } catch (\Exception $e) {
            \Log::error('Export error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Lỗi khi xuất file: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Import products from CSV
     */
    public function import(Request $request)
    {
        try {
            // Validate file upload
            $request->validate([
                'file' => 'required|file|mimes:csv,txt|max:10240', // CSV only, max 10MB
            ], [
                'file.required' => 'Vui lòng chọn file để nhập',
                'file.mimes' => 'File phải có định dạng CSV',
                'file.max' => 'File không được vượt quá 10MB'
            ]);

            $file = $request->file('file');
            $path = $file->getRealPath();
            
            // Debug logs
            \Log::info('Import file path: ' . $path);
            \Log::info('File exists: ' . (file_exists($path) ? 'Yes' : 'No'));
            
            // Read CSV file
            $csvData = array_map('str_getcsv', file($path));
            \Log::info('CSV rows count: ' . count($csvData));
            
            // Remove BOM if exists
            if (!empty($csvData[0])) {
                $csvData[0][0] = preg_replace('/^\xEF\xBB\xBF/', '', $csvData[0][0]);
            }
            
            // Get headers (first row)
            $headers = array_shift($csvData);
            \Log::info('Headers: ', $headers);
            
            // Convert to lowercase and remove spaces for easier matching
            $normalizedHeaders = array_map(function($header) {
              return strtolower(trim($header));
            }, $headers);
            \Log::info('Normalized headers: ', $normalizedHeaders);
            
            $importedCount = 0;
            $updatedCount = 0;
            $errors = [];
            
            foreach ($csvData as $rowIndex => $row) {
                $actualRowNumber = $rowIndex + 2; // +2 because we removed header and array is 0-based
                \Log::info("Processing row {$actualRowNumber}: ", $row);
                
                try {
                    // Skip empty rows
                    if (empty(array_filter($row))) {
                        \Log::info("Skipping empty row {$actualRowNumber}");
                        continue;
                    }
                    
                    // Create associative array from row data
                    $data = array_combine($normalizedHeaders, $row);
                    \Log::info("Row data: ", $data);
                    
                    // Map CSV columns to database fields (simplified mapping)
                    $productData = [
                        'sku' => $this->getFieldValue($data, ['sku']),
                        'name' => $this->getFieldValue($data, ['name', 'tensanpham', 'ten']),
                        'quantity' => (int)$this->getFieldValue($data, ['quantity', 'soluong'], 0),
                        'cost_price' => (float)$this->getFieldValue($data, ['cost_price'], 0), // ← Bỏ parsePrice
                        'wholesale_price' => (float)$this->getFieldValue($data, ['wholesale_price'], 0), // ← Bỏ parsePrice
                        'retail_price' => (float)$this->getFieldValue($data, ['retail_price'], 0),
                        'category_name' => $this->getFieldValue($data, ['category_name']), // ← Match với header gốc
                        'brand_name' => $this->getFieldValue($data, ['brand_name']),
                        'barcode' => $this->getFieldValue($data, ['barcode']),
                        'weight' => $this->getFieldValue($data, ['weight', 'khoiluong']),
                        'status' => $this->getFieldValue($data, ['status', 'trangthai'], 'active'),
                        'note' => $this->getFieldValue($data, ['note', 'ghichu']),
                    ];
                    \Log::info("Product data: ", $productData);
                    
                    // Validate required fields
                    if (empty($productData['sku'])) {
                        $errors[] = "Dòng {$actualRowNumber}: SKU không được để trống";
                        \Log::warning("Empty SKU at row {$actualRowNumber}");
                        continue;
                    }
                    
                    if (empty($productData['name'])) {
                        $errors[] = "Dòng {$actualRowNumber}: Tên sản phẩm không được để trống";
                        \Log::warning("Empty name at row {$actualRowNumber}");
                        continue;
                    }
                    
                    // Check if product exists
                    $existingProduct = Product::where('sku', $productData['sku'])->first();
                    
                    if ($existingProduct) {
                        // Update existing product
                        $existingProduct->update($productData);
                        $updatedCount++;
                        \Log::info("Updated product: " . $productData['sku']);
                    } else {
                        // Create new product
                        Product::create($productData);
                        $importedCount++;
                        \Log::info("Created product: " . $productData['sku']);
                    }
                    
                } catch (\Exception $e) {
                    \Log::error("Error processing row {$actualRowNumber}: " . $e->getMessage());
                    $errors[] = "Dòng {$actualRowNumber}: " . $e->getMessage();
                }
            }
            
            \Log::info("Import completed. Created: {$importedCount}, Updated: {$updatedCount}");
            
            $totalProcessed = $importedCount + $updatedCount;
            $message = "Import thành công! Tạo mới: {$importedCount}, Cập nhật: {$updatedCount}";
            
            return response()->json([
                'success' => true,
                'message' => $message,
                'data' => [
                    'imported_count' => $importedCount,
                    'updated_count' => $updatedCount,
                    'total_processed' => $totalProcessed,
                    'errors' => $errors
                ]
            ]);
            
        } catch (\Exception $e) {
            \Log::error('Import error: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Lỗi khi import file: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Download import template
     */
    public function downloadTemplate()
    {
        $headers = [
            'STT',
            'SKU',
            'name',
            'quantity',
            'cost_price',
            'wholesale_price',
            'retail_price',
            'category_name',
            'brand_name',
            'barcode',
            'weight',
            'status',
            'note'
        ];

        // Sample data
        $sampleData = [
            [
                '1',
                'SP001',
                'San pham mau 1',
                '100',
                '50000',
                '60000',
                '70000',
                'Dien tu',
                'Samsung',
                '1234567890',
                '0.5kg',
                'active',
                'Ghi chu mau'
            ],
            [
                '2',
                'SP002', 
                'San pham mau 2',
                '50',
                '30000',
                '35000',
                '40000',
                'Thoi trang',
                'Nike',
                '0987654321',
                '0.3kg',
                'active',
                ''
            ]
        ];

        $filename = 'products_import_template.csv';

        return response()->streamDownload(function() use ($headers, $sampleData) {
            $file = fopen('php://output', 'w');
            
            // UTF-8 BOM
            fwrite($file, "\xEF\xBB\xBF");
            
            // Headers
            fputcsv($file, $headers);
            
            // Sample data
            foreach ($sampleData as $row) {
                fputcsv($file, $row);
            }
            
            fclose($file);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /**
     * Helper method to get field value from CSV data
     */
    private function getFieldValue($data, $possibleKeys, $default = null)
    {
        foreach ($possibleKeys as $key) {
            if (isset($data[$key]) && !empty(trim($data[$key]))) {
                return trim($data[$key]);
            }
        }
        return $default;
    }

    /**
     * Parse price from string (remove commas, spaces)
     */
    private function parsePrice($price)
    {
        if (is_numeric($price)) {
            return (float)$price;
        }
        
        // Remove commas, spaces and convert to float
        $cleaned = preg_replace('/[^\d.]/', '', $price);
        return (float)$cleaned;
    }
    /**
 * Get stock history for a specific product - FIXED VERSION
 */
public function getStockHistory($id, Request $request)
{
    $product = Product::find($id);
    
    if (!$product) {
        return response()->json([
            'success' => false,
            'message' => 'Sản phẩm không tồn tại'
        ], 404);
    }

    // Get current total stock from all warehouses
    $currentTotalStock = $product->warehouseProducts()->sum('quantity');
    
    // 1. Lịch sử NHẬP KHO từ Purchase Receipt Items
    $imports = PurchaseReceiptItem::with([
        'purchaseReceipt.warehouse',
        'purchaseReceipt.purchaseOrder.supplier',
        'purchaseReceipt.receiver'
    ])
    ->where('product_id', $id)
    ->get()
    ->map(function ($item) {
        return [
            'id' => 'import_' . $item->id,
            'type' => 'import',
            'type_text' => 'Nhập kho',
            'date' => $item->purchaseReceipt->received_at,
            'quantity' => $item->quantity_received,
            'price' => $item->unit_cost,
            'total' => $item->total_cost,
            'warehouse' => $item->purchaseReceipt->warehouse->name ?? 'N/A',
            'reference_code' => $item->purchaseReceipt->code,
            'partner' => $item->purchaseReceipt->purchaseOrder->supplier->name ?? 'N/A',
            'user' => $item->purchaseReceipt->receiver->name ?? 'N/A',
            'note' => $item->note,
            'lot_number' => $item->lot_number,
            'condition' => $item->condition_status,
        ];
    });

    // 2. Lịch sử XUẤT BÁN từ Order Items  
    $exports = OrderItem::with([
        'order.warehouse',
        'order.customer',
        'order.cashier'
    ])
    ->where('product_id', $id)
    ->get()
    ->map(function ($item) {
        return [
            'id' => 'export_' . $item->id,
            'type' => 'export',
            'type_text' => 'Xuất bán',
            'date' => $item->order->created_at,
            'quantity' => -$item->quantity, // Âm vì là xuất
            'price' => $item->price,
            'total' => $item->total,
            'warehouse' => $item->order->warehouse->name ?? 'N/A',
            'reference_code' => $item->order->code,
            'partner' => $item->order->customer->name ?? 'Khách lẻ',
            'user' => $item->order->cashier->name ?? 'N/A',
            'note' => $item->note,
            'lot_number' => null,
            'condition' => null,
        ];
    });

    // 3. Lịch sử TRẢ HÀNG từ Purchase Return Order Items
    $returns = PurchaseReturnOrderItem::with([
        'purchaseReturnOrder.warehouse',
        'purchaseReturnOrder.supplier',
        'purchaseReturnOrder.creator'
    ])
    ->where('product_id', $id)
    ->get()
    ->map(function ($item) {
        return [
            'id' => 'return_' . $item->id,
            'type' => 'return',
            'type_text' => 'Trả hàng',
            'date' => $item->purchaseReturnOrder->returned_at ?? $item->purchaseReturnOrder->created_at,
            'quantity' => -$item->quantity, // Âm vì là trả
            'price' => $item->price,
            'total' => $item->total,
            'warehouse' => $item->purchaseReturnOrder->warehouse->name ?? 'N/A',
            'reference_code' => $item->purchaseReturnOrder->code,
            'partner' => $item->purchaseReturnOrder->supplier->name ?? 'N/A',
            'user' => $item->purchaseReturnOrder->creator->name ?? 'N/A',
            'note' => $item->note,
            'lot_number' => $item->lot_number,
            'condition' => $item->condition_status,
            'return_reason' => $item->return_reason,
        ];
    });

    // Gộp tất cả lịch sử và sắp xếp theo thời gian (cũ nhất trước)
    $allHistory = collect()
        ->merge($imports)
        ->merge($exports)
        ->merge($returns)
        ->sortBy('date')
        ->values();

    // ✅ TÍNH RUNNING BALANCE ĐÚNG CÁCH
    // Bắt đầu từ tồn kho hiện tại và tính ngược về quá khứ
    $totalMovement = $allHistory->sum('quantity'); // Tổng biến động
    $startingBalance = $currentTotalStock - $totalMovement; // Tồn kho đầu kỳ
    
    $runningBalance = $startingBalance;
    $historyWithBalance = $allHistory->map(function ($item) use (&$runningBalance) {
        $runningBalance += $item['quantity'];
        $item['running_balance'] = $runningBalance;
        return $item;
    });

    // Sắp xếp lại theo thời gian mới nhất trước (để hiển thị)
    $historyWithBalance = $historyWithBalance->sortByDesc('date')->values();

    // Filter theo warehouse nếu có
    $warehouseId = $request->get('warehouse_id');
    if ($warehouseId) {
        $historyWithBalance = $historyWithBalance->filter(function ($item) use ($warehouseId) {
            // TODO: implement warehouse filter by ID
            return true;
        });
    }

    // Filter theo type nếu có
    $type = $request->get('type');
    if ($type && $type !== 'all') {
        $historyWithBalance = $historyWithBalance->filter(function ($item) use ($type) {
            return $item['type'] === $type;
        });
    }

    // Pagination
    $perPage = $request->get('per_page', 20);
    $page = $request->get('page', 1);
    $total = $historyWithBalance->count();
    
    $paginatedHistory = $historyWithBalance->forPage($page, $perPage)->values();

    return response()->json([
        'success' => true,
        'data' => [
            'history' => $paginatedHistory,
            'summary' => [
                'total_imports' => $imports->count(),
                'total_exports' => $exports->count(),  
                'total_returns' => $returns->count(),
                'total_quantity_in' => $imports->sum('quantity'),
                'total_quantity_out' => abs($exports->sum('quantity')),
                'total_returned' => abs($returns->sum('quantity')),
                'current_balance' => $currentTotalStock,
                'starting_balance' => $startingBalance,
            ]
        ],
        'pagination' => [
            'current_page' => $page,
            'per_page' => $perPage,
            'total' => $total,
            'last_page' => ceil($total / $perPage),
        ]
    ]);
}
/**
 * Get stock information for a product (for frontend validation)
 */
public function getStockInfo($id)
{
    $product = Product::find($id);
    
    if (!$product) {
        return response()->json([
            'success' => false,
            'message' => 'Sản phẩm không tồn tại'
        ], 404);
    }

    return response()->json([
        'success' => true,
        'data' => [
            'product_id' => $product->id,
            'sku' => $product->sku,
            'current_quantity' => $product->quantity,
            'total_warehouse_stock' => $product->total_warehouse_stock,
            'available_stock' => $product->available_stock,
            'max_sellable' => $product->can_sell_quantity,
            'has_mismatch' => $product->hasQuantityMismatch(),
            'warehouses' => $product->warehouseProducts()->with('warehouse')->get()->map(function($wp) {
                return [
                    'warehouse_id' => $wp->warehouse_id,
                    'warehouse_name' => $wp->warehouse->name,
                    'stock' => $wp->quantity,
                    'available' => $wp->available_stock,
                    'reserved' => $wp->reserved_quantity
                ];
            })
        ]
    ]);
}
}