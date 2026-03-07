<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ShippingProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ShippingProviderController extends Controller
{
    /**
     * Danh sách tất cả providers
     */
    public function index(Request $request)
    {
        $query = ShippingProvider::query();

        // Filter by status
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        // Filter by type
        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }

        // Search
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('name', 'LIKE', "%{$search}%")
                  ->orWhere('code', 'LIKE', "%{$search}%");
            });
        }

        $providers = $query->orderBy('created_at', 'desc')
            ->paginate($request->per_page ?? 20);

        return response()->json([
            'success' => true,
            'data' => $providers->items(),
            'pagination' => [
                'current_page' => $providers->currentPage(),
                'last_page' => $providers->lastPage(),
                'per_page' => $providers->perPage(),
                'total' => $providers->total(),
                'from' => $providers->firstItem(),
                'to' => $providers->lastItem(),
            ]
        ]);
    }

    /**
     * Chi tiết provider
     */
    public function show($id)
    {
        $provider = ShippingProvider::with(['orderShippings' => function($query) {
            $query->latest()->limit(10);
        }])->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $provider
        ]);
    }

    /**
     * Tạo provider mới
     */
    public function store(Request $request)
    {
        $request->validate([
            'code' => 'required|string|unique:shipping_providers,code|max:50',
            'name' => 'required|string|max:255',
            'type' => 'required|in:internal,ghtk,ghn,viettelpost,custom',
            'api_config' => 'nullable|array',
            'pricing_config' => 'nullable|array',
            'status' => 'required|in:active,inactive'
        ]);

        DB::beginTransaction();
        try {
            $provider = ShippingProvider::create([
                'code' => strtoupper($request->code),
                'name' => $request->name,
                'type' => $request->type,
                'api_config' => $request->api_config,
                'pricing_config' => $request->pricing_config,
                'status' => $request->status
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Tạo đơn vị vận chuyển thành công',
                'data' => $provider
            ]);

        } catch (\Exception $e) {
            DB::rollback();
            return response()->json([
                'success' => false,
                'message' => 'Lỗi tạo đơn vị vận chuyển: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Cập nhật provider
     */
    public function update(Request $request, $id)
    {
        $provider = ShippingProvider::findOrFail($id);

        $request->validate([
            'code' => 'required|string|max:50|unique:shipping_providers,code,' . $id,
            'name' => 'required|string|max:255',
            'type' => 'required|in:internal,ghtk,ghn,viettelpost,custom',
            'api_config' => 'nullable|array',
            'pricing_config' => 'nullable|array',
            'status' => 'required|in:active,inactive'
        ]);

        DB::beginTransaction();
        try {
            $provider->update([
                'code' => strtoupper($request->code),
                'name' => $request->name,
                'type' => $request->type,
                'api_config' => $request->api_config,
                'pricing_config' => $request->pricing_config,
                'status' => $request->status
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Cập nhật đơn vị vận chuyển thành công',
                'data' => $provider
            ]);

        } catch (\Exception $e) {
            DB::rollback();
            return response()->json([
                'success' => false,
                'message' => 'Lỗi cập nhật: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Xóa provider
     */
    public function destroy($id)
    {
        $provider = ShippingProvider::findOrFail($id);

        // Kiểm tra có đơn vận chuyển đang sử dụng không
        if ($provider->orderShippings()->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Không thể xóa đơn vị vận chuyển đang được sử dụng'
            ], 422);
        }

        DB::beginTransaction();
        try {
            $provider->delete();
            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Xóa đơn vị vận chuyển thành công'
            ]);

        } catch (\Exception $e) {
            DB::rollback();
            return response()->json([
                'success' => false,
                'message' => 'Lỗi xóa: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Bulk delete providers
     */
    public function bulkDelete(Request $request)
    {
        $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'exists:shipping_providers,id'
        ]);

        DB::beginTransaction();
        try {
            // Kiểm tra providers có đang được sử dụng không
            $usedProviders = ShippingProvider::whereIn('id', $request->ids)
                ->whereHas('orderShippings')
                ->pluck('name');

            if ($usedProviders->isNotEmpty()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Không thể xóa các đơn vị vận chuyển đang được sử dụng: ' . $usedProviders->implode(', ')
                ], 422);
            }

            ShippingProvider::whereIn('id', $request->ids)->delete();
            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Xóa thành công ' . count($request->ids) . ' đơn vị vận chuyển'
            ]);

        } catch (\Exception $e) {
            DB::rollback();
            return response()->json([
                'success' => false,
                'message' => 'Lỗi xóa: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Toggle status provider
     */
    public function toggleStatus($id)
    {
        $provider = ShippingProvider::findOrFail($id);

        DB::beginTransaction();
        try {
            $newStatus = $provider->status === 'active' ? 'inactive' : 'active';
            $provider->update(['status' => $newStatus]);
            
            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Cập nhật trạng thái thành công',
                'data' => $provider
            ]);

        } catch (\Exception $e) {
            DB::rollback();
            return response()->json([
                'success' => false,
                'message' => 'Lỗi cập nhật trạng thái: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Thống kê providers
     */
    public function getStats()
    {
        $stats = [
            'total' => ShippingProvider::count(),
            'active' => ShippingProvider::where('status', 'active')->count(),
            'inactive' => ShippingProvider::where('status', 'inactive')->count(),
            'by_type' => ShippingProvider::select('type', DB::raw('count(*) as count'))
                ->groupBy('type')
                ->pluck('count', 'type')
        ];

        return response()->json([
            'success' => true,
            'data' => $stats
        ]);
    }
}