<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class AutoWarehouseRedirect
{
    /**
     * Middleware tự động redirect user vào warehouse phù hợp và inject warehouse_id
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (!Auth::check()) {
            return $next($request);
        }

        $user = Auth::user();
        
        // Chỉ áp dụng cho các role warehouse-specific (không phải super_admin hoặc admin)
        // Thêm các role name có thể có
        if ($user->hasAnyRole(['super_admin', 'admin', 'warehouse_manager'])) {
            return $next($request);
        }

        // Lấy warehouse mặc định của user
        $defaultWarehouseId = $user->getDefaultWarehouseId();
        
        if (!$defaultWarehouseId) {
            // Nếu user không có quyền truy cập warehouse nào
            Auth::logout();
            $request->session()->invalidate();
            return redirect()->route('login')->with('error', 'Tài khoản chưa được phân quyền truy cập kho nào');
        }

        // Inject warehouse_id vào request nếu chưa có
        if (!$request->has('warehouse_id') && !$request->route('warehouse_id')) {
            $request->merge(['warehouse_id' => $defaultWarehouseId]);
        }

        // Các route cần redirect tự động với warehouse_id
        $currentRoute = $request->route()->getName();
        $warehouseSpecificRoutes = [
            'dashboard',
            'products.index',
            'customers.index', 
            'orders.index',
            'pos.index',
            'categories.index',
            'units.index',
            'customer-groups.index',
            'customer-debts.index'
        ];

        // Nếu đang ở route cần warehouse mà chưa có warehouse_id trong URL
        if (in_array($currentRoute, $warehouseSpecificRoutes) && !$request->has('warehouse_id')) {
            // Redirect về route tương ứng với warehouse_id
            $currentUrl = $request->url();
            $queryParams = $request->query();
            $queryParams['warehouse_id'] = $defaultWarehouseId;
            
            $newUrl = $currentUrl . '?' . http_build_query($queryParams);
            return redirect($newUrl);
        }

        return $next($request);
    }
}