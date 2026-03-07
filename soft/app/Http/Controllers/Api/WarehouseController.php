<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Warehouse;
use App\Services\WarehouseService;
use App\Http\Resources\WarehouseResource;
use App\Http\Resources\WarehouseProductResource;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class WarehouseController extends Controller
{
    protected $warehouseService;

    public function __construct(WarehouseService $warehouseService)
    {
        $this->warehouseService = $warehouseService;
    }

    /**
     * Display a listing of warehouses
     */
    public function index(Request $request): JsonResponse
    {
        $query = Warehouse::query();

        // Search filter
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('code', 'like', "%{$search}%")
                  ->orWhere('address', 'like', "%{$search}%")
                  ->orWhere('manager_name', 'like', "%{$search}%");
            });
        }

        // Status filter
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        // Sort
        $sortBy = $request->get('sort_by', 'created_at');
        $sortDir = $request->get('sort_dir', 'desc');
        $query->orderBy($sortBy, $sortDir);

        $warehouses = $query->paginate($request->get('per_page', 10));

        return response()->json([
            'success' => true,
            'data' => [
                'data' => WarehouseResource::collection($warehouses->items()),
                'pagination' => [
                    'current_page' => $warehouses->currentPage(),
                    'last_page' => $warehouses->lastPage(),
                    'per_page' => $warehouses->perPage(),
                    'total' => $warehouses->total(),
                ]
            ],
            'message' => 'Danh sách kho hàng'
        ]);
    }

    /**
     * Store a newly created warehouse
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'code' => 'required|string|max:255|unique:warehouses,code',
            'name' => 'required|string|max:255',
            'address' => 'nullable|string|max:255',
            'manager_name' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:255',
            'capacity' => 'nullable|numeric|min:0',
            'status' => 'required|in:active,inactive,maintenance',
            'note' => 'nullable|string'
        ]);

        $warehouse = Warehouse::create($validated);

        return response()->json([
            'success' => true,
            'data' => new WarehouseResource($warehouse),
            'message' => 'Tạo kho hàng thành công'
        ], 201);
    }

    /**
     * Display the specified warehouse
     */
    public function show($id): JsonResponse
    {
        $warehouse = Warehouse::with(['warehouseProducts.product'])->find($id);

        if (!$warehouse) {
            return response()->json([
                'success' => false,
                'message' => 'Không tìm thấy kho hàng'
            ], 404);
        }

        // Get capacity analysis
        $analysis = $this->warehouseService->getCapacityAnalysis($id);

        return response()->json([
            'success' => true,
            'data' => [
                'warehouse' => new WarehouseResource($warehouse),
                'analysis' => $analysis
            ],
            'message' => 'Chi tiết kho hàng'
        ]);
    }

    /**
     * Update the specified warehouse
     */
    public function update(Request $request, $id): JsonResponse
    {
        $warehouse = Warehouse::find($id);

        if (!$warehouse) {
            return response()->json([
                'success' => false,
                'message' => 'Không tìm thấy kho hàng'
            ], 404);
        }

        $validated = $request->validate([
            'code' => 'required|string|max:255|unique:warehouses,code,' . $id,
            'name' => 'required|string|max:255',
            'address' => 'nullable|string|max:255',
            'manager_name' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:255',
            'capacity' => 'nullable|numeric|min:0',
            'status' => 'required|in:active,inactive,maintenance',
            'note' => 'nullable|string'
        ]);

        $warehouse->update($validated);

        return response()->json([
            'success' => true,
            'data' => new WarehouseResource($warehouse),
            'message' => 'Cập nhật kho hàng thành công'
        ]);
    }

    /**
     * Remove the specified warehouse
     */
    public function destroy($id): JsonResponse
    {
        $warehouse = Warehouse::find($id);

        if (!$warehouse) {
            return response()->json([
                'success' => false,
                'message' => 'Không tìm thấy kho hàng'
            ], 404);
        }

        // Check if warehouse has products
        if ($warehouse->warehouseProducts()->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Không thể xóa kho hàng đang có sản phẩm'
            ], 422);
        }

        $warehouse->delete();

        return response()->json([
            'success' => true,
            'message' => 'Xóa kho hàng thành công'
        ]);
    }

    /**
     * Get warehouse products stock
     */
    public function products($id, Request $request): JsonResponse
    {
        $warehouse = Warehouse::find($id);

        if (!$warehouse) {
            return response()->json([
                'success' => false,
                'message' => 'Không tìm thấy kho hàng'
            ], 404);
        }

        $query = $warehouse->warehouseProducts()->with('product');

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

        $products = $query->paginate($request->get('per_page', 15));

        return response()->json([
            'success' => true,
            'data' => [
                'data' => WarehouseProductResource::collection($products->items()),
                'pagination' => [
                    'current_page' => $products->currentPage(),
                    'last_page' => $products->lastPage(),
                    'per_page' => $products->perPage(),
                    'total' => $products->total(),
                ]
            ],
            'message' => 'Danh sách sản phẩm trong kho'
        ]);
    }
}