<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Warehouse;
use App\Models\UserSession;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;

class WarehouseSwitchingController extends Controller
{
    public function getAvailableWarehouses(): JsonResponse
    {
        $user = Auth::user();
        
        if ($user->role->name === 'super_admin') {
            $warehouses = Warehouse::where('status', 'active')
                ->select('id', 'code', 'name', 'address', 'manager_name')
                ->orderBy('name')
                ->get();
        } else {
            $warehouses = $user->accessibleWarehouses()
                ->where('warehouses.status', 'active')
                ->select('warehouses.id', 'warehouses.code', 'warehouses.name', 'warehouses.address', 'warehouses.manager_name')
                ->orderBy('warehouses.name')
                ->get();
        }

        return response()->json([
            'success' => true,
            'data' => $warehouses,
            'message' => 'Danh sách kho có quyền truy cập'
        ]);
    }

    public function getCurrentWarehouse(): JsonResponse
    {
        $user = Auth::user();
        $currentWarehouseId = Session::get('current_warehouse_id');
        
        if (!$currentWarehouseId) {
            $userSession = UserSession::where('user_id', $user->id)
                ->where('is_active', true)
                ->latest('last_activity')
                ->first();
                
            $currentWarehouseId = $userSession?->warehouse_id;
        }

        if ($currentWarehouseId) {
            $warehouse = Warehouse::find($currentWarehouseId);
            
            if ($warehouse && $warehouse->status === 'active') {
                return response()->json([
                    'success' => true,
                    'data' => [
                        'id' => $warehouse->id,
                        'code' => $warehouse->code,
                        'name' => $warehouse->name,
                        'address' => $warehouse->address,
                        'manager_name' => $warehouse->manager_name,
                    ],
                    'message' => 'Kho đang hoạt động'
                ]);
            }
        }

        return response()->json([
            'success' => false,
            'data' => null,
            'message' => 'Không có kho nào đang hoạt động'
        ]);
    }

    public function switchWarehouse(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'warehouse_id' => 'required|exists:warehouses,id'
        ]);

        $user = Auth::user();
        $warehouseId = $validated['warehouse_id'];
        
        $warehouse = Warehouse::find($warehouseId);
        
        if (!$warehouse || $warehouse->status !== 'active') {
            return response()->json([
                'success' => false,
                'message' => 'Kho không tồn tại hoặc không hoạt động'
            ], 404);
        }

        if ($user->role->name !== 'super_admin') {
            $hasAccess = $user->warehouseAccess()
                ->where('warehouse_id', $warehouseId)
                ->where('is_active', true)
                ->exists();
                
            if (!$hasAccess) {
                return response()->json([
                    'success' => false,
                    'message' => 'Bạn không có quyền truy cập kho này'
                ], 403);
            }
        }

        try {
    Session::put('current_warehouse_id', $warehouseId);
    
    UserSession::where('user_id', $user->id)
        ->where('is_active', true)
        ->update(['warehouse_id' => $warehouseId]);

    // ADD THIS: Update session for immediate use
    session(['current_warehouse_id' => $warehouseId]);

    return response()->json([
        'success' => true,
        'data' => [
            'id' => $warehouse->id,
            'code' => $warehouse->code,
            'name' => $warehouse->name,
            'address' => $warehouse->address,
            'manager_name' => $warehouse->manager_name,
        ],
        'message' => "Đã chuyển sang kho: {$warehouse->name}",
        'warehouse_id' => $warehouseId  // ADD THIS for debugging
    ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Lỗi khi chuyển kho: ' . $e->getMessage()
            ], 500);
        }
    }

    public function clearWarehouse(): JsonResponse
    {
        $user = Auth::user();
        
        try {
            Session::forget('current_warehouse_id');
            
            UserSession::where('user_id', $user->id)
                ->where('is_active', true)
                ->update(['warehouse_id' => null]);

            return response()->json([
                'success' => true,
                'message' => 'Đã thoát khỏi ngữ cảnh kho'
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Lỗi khi thoát kho: ' . $e->getMessage()
            ], 500);
        }
    }
}