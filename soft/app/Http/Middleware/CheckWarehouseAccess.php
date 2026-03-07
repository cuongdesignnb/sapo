<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class CheckWarehouseAccess
{
    public function handle(Request $request, Closure $next, string $accessLevel = 'read'): Response
    {
        if (!Auth::check()) {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized'
                ], 401);
            }
            return redirect()->route('login');
        }

        $user = Auth::user();
        $warehouseId = $request->route('warehouse_id') ?? $request->input('warehouse_id');

        if (!$warehouseId) {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Warehouse ID is required'
                ], 400);
            }
            return redirect()->back()->with('error', 'Không xác định được kho');
        }

        if (!$user->hasWarehouseAccess($warehouseId)) {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Không có quyền truy cập kho này'
                ], 403);
            }
            return redirect()->back()->with('error', 'Không có quyền truy cập kho này');
        }

        $userAccessLevel = $user->getWarehouseAccessLevel($warehouseId);
        
        $allowedLevels = [
            'read' => ['read', 'write', 'manage'],
            'write' => ['write', 'manage'],
            'manage' => ['manage']
        ];

        if (!in_array($userAccessLevel, $allowedLevels[$accessLevel] ?? [])) {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Không đủ quyền để thực hiện hành động này'
                ], 403);
            }
            return redirect()->back()->with('error', 'Không đủ quyền để thực hiện hành động này');
        }

        return $next($request);
    }
}