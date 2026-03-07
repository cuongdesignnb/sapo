<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\WarehouseProduct;
use App\Services\WarehouseService;
use App\Http\Resources\WarehouseProductResource;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class WarehouseProductController extends Controller
{
    protected $warehouseService;

    public function __construct(WarehouseService $warehouseService)
    {
        $this->warehouseService = $warehouseService;
    }

    /**
     * Display stock levels across all warehouses
     */
    public function index(Request $request): JsonResponse
    {
        $query = WarehouseProduct::with(['warehouse', 'product']);

        // Warehouse filter
        if ($request->filled('warehouse_id')) {
            $query->where('warehouse_id', $request->warehouse_id);
        }

        // Product filter
        if ($request->filled('product_id')) {
            $query->where('product_id', $request->product_id);
        }

        // Stock status filter
        if ($request->filled('stock_status')) {
            $status = $request->stock_status;
            switch ($status) {
                case 'low_stock':
                    $query->lowStock();
                    break;
                case 'out_of_stock':
                    $query->outOfStock();
                    break;
                case 'over_stock':
                    $query->overStock();
                    break;
                case 'in_stock':
                    $query->inStock();
                    break;
            }
        }

        // Search filter
        if ($request->filled('search')) {
            $search = $request->search;
            $query->whereHas('product', function($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('sku', 'like', "%{$search}%")
                  ->orWhere('barcode', 'like', "%{$search}%");
            });
        }

        // Sort
        $sortBy = $request->get('sort_by', 'created_at');
        $sortDir = $request->get('sort_dir', 'desc');
        $query->orderBy($sortBy, $sortDir);

        $warehouseProducts = $query->paginate($request->get('per_page', 15));

        return response()->json([
            'success' => true,
            'data' => [
                'data' => WarehouseProductResource::collection($warehouseProducts->items()),
                'pagination' => [
                    'current_page' => $warehouseProducts->currentPage(),
                    'last_page' => $warehouseProducts->lastPage(),
                    'per_page' => $warehouseProducts->perPage(),
                    'total' => $warehouseProducts->total(),
                ]
            ],
            'message' => 'Danh sách tồn kho'
        ]);
    }

    /**
     * Update warehouse product stock
     */
    public function update(Request $request, $id): JsonResponse
    {
        $warehouseProduct = WarehouseProduct::find($id);

        if (!$warehouseProduct) {
            return response()->json([
                'success' => false,
                'message' => 'Không tìm thấy sản phẩm trong kho'
            ], 404);
        }

        $validated = $request->validate([
            'quantity' => 'required|integer|min:0',
            'cost' => 'nullable|numeric|min:0',
            'min_stock' => 'nullable|integer|min:0',
            'max_stock' => 'nullable|integer|min:0',
            'reserved_quantity' => 'nullable|integer|min:0',
            'reason' => 'nullable|string|max:255'
        ]);

        // Validate max_stock >= min_stock
        if (isset($validated['max_stock']) && isset($validated['min_stock'])) {
            if ($validated['max_stock'] < $validated['min_stock']) {
                return response()->json([
                    'success' => false,
                    'message' => 'Tồn kho tối đa phải lớn hơn hoặc bằng tồn kho tối thiểu'
                ], 422);
            }
        }

        // Validate reserved_quantity <= quantity
        if (isset($validated['reserved_quantity']) && $validated['reserved_quantity'] > $validated['quantity']) {
            return response()->json([
                'success' => false,
                'message' => 'Số lượng dự trữ không thể lớn hơn tồn kho'
            ], 422);
        }

        $oldQuantity = $warehouseProduct->quantity;
        $warehouseProduct->update($validated);

        // Update last import/export date based on quantity change
        if ($validated['quantity'] > $oldQuantity) {
            $warehouseProduct->update(['last_import_date' => now()]);
        } elseif ($validated['quantity'] < $oldQuantity) {
            $warehouseProduct->update(['last_export_date' => now()]);
        }

        return response()->json([
            'success' => true,
            'data' => new WarehouseProductResource($warehouseProduct->load(['warehouse', 'product'])),
            'message' => 'Cập nhật tồn kho thành công'
        ]);
    }

    /**
     * Adjust stock quantity
     */
    public function adjustStock(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'warehouse_id' => 'required|exists:warehouses,id',
            'product_id' => 'required|exists:products,id',
            'new_quantity' => 'required|integer|min:0',
            'reason' => 'nullable|string|max:255'
        ]);

        try {
            $result = $this->warehouseService->adjustStock(
                $validated['warehouse_id'],
                $validated['product_id'],
                $validated['new_quantity'],
                $validated['reason'] ?? null
            );

            return response()->json([
                'success' => true,
                'data' => $result,
                'message' => 'Điều chỉnh tồn kho thành công'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 422);
        }
    }

    /**
     * Transfer product between warehouses
     */
    public function transfer(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'from_warehouse_id' => 'required|exists:warehouses,id',
            'to_warehouse_id' => 'required|exists:warehouses,id|different:from_warehouse_id',
            'product_id' => 'required|exists:products,id',
            'quantity' => 'required|integer|min:1',
            'serial_ids' => 'nullable|array',
            'serial_ids.*' => 'nullable|integer|exists:product_serials,id',
            'note' => 'nullable|string|max:255'
        ]);

        try {
            $result = $this->warehouseService->transferProduct(
                $validated['from_warehouse_id'],
                $validated['to_warehouse_id'],
                $validated['product_id'],
                $validated['quantity'],
                $validated['note'] ?? null,
                null,
                $validated['serial_ids'] ?? []
            );

            return response()->json([
                'success' => true,
                'data' => $result,
                'message' => 'Chuyển kho thành công'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 422);
        }
    }

    /**
     * Get low stock alerts
     */
    public function lowStockAlerts(Request $request): JsonResponse
    {
        $warehouseId = $request->get('warehouse_id');
        $alerts = $this->warehouseService->getLowStockAlerts($warehouseId);

        return response()->json([
            'success' => true,
            'data' => WarehouseProductResource::collection($alerts),
            'total' => $alerts->count(),
            'message' => 'Danh sách cảnh báo tồn kho thấp'
        ]);
    }

    /**
     * Get out of stock products
     */
    public function outOfStock(Request $request): JsonResponse
    {
        $warehouseId = $request->get('warehouse_id');
        $outOfStock = $this->warehouseService->getOutOfStockProducts($warehouseId);

        return response()->json([
            'success' => true,
            'data' => WarehouseProductResource::collection($outOfStock),
            'total' => $outOfStock->count(),
            'message' => 'Danh sách sản phẩm hết hàng'
        ]);
    }

    /**
     * Get stock summary
     */
    public function stockSummary(Request $request): JsonResponse
    {
        $warehouseId = $request->get('warehouse_id');
        $summary = $this->warehouseService->getStockSummary($warehouseId);

        return response()->json([
            'success' => true,
            'data' => $summary,
            'message' => 'Tổng quan tồn kho'
        ]);
    }

    /**
     * Get warehouse capacity analysis
     */
    public function capacityAnalysis($warehouseId): JsonResponse
    {
        try {
            $analysis = $this->warehouseService->getCapacityAnalysis($warehouseId);

            return response()->json([
                'success' => true,
                'data' => $analysis,
                'message' => 'Phân tích công suất kho'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 422);
        }
    }
    /**
 * Bulk transfer products between warehouses
 */
public function bulkTransfer(Request $request): JsonResponse
{
    $validated = $request->validate([
        'from_warehouse_id' => 'required|exists:warehouses,id',
        'to_warehouse_id' => 'required|exists:warehouses,id|different:from_warehouse_id',
        'note' => 'nullable|string|max:255',
        'items' => 'required|array|min:1',
        'items.*.product_id' => 'required|exists:products,id',
        'items.*.quantity' => 'required|integer|min:1',
        'items.*.note' => 'nullable|string|max:255'
    ]);

    try {
        $result = $this->warehouseService->bulkTransferProducts(
            $validated['from_warehouse_id'],
            $validated['to_warehouse_id'],
            $validated['items'],
            $validated['note'] ?? null
        );

        return response()->json([
            'success' => true,
            'data' => $result,
            'message' => 'Chuyển kho hàng loạt thành công'
        ]);
    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => $e->getMessage()
        ], 422);
    }
}
}