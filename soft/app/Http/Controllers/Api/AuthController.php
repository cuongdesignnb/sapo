<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\UserSession;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
            'warehouse_id' => 'nullable|exists:warehouses,id'
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['Email hoặc mật khẩu không đúng'],
            ]);
        }

        if (!$user->isActive()) {
            return response()->json([
                'success' => false,
                'message' => 'Tài khoản đã bị khóa hoặc ngưng hoạt động'
            ], 403);
        }

        $warehouseId = $request->warehouse_id ?? $user->warehouse_id;
        if ($warehouseId && !$user->hasWarehouseAccess($warehouseId)) {
            return response()->json([
                'success' => false,
                'message' => 'Không có quyền truy cập kho này'
            ], 403);
        }

        $token = $user->createToken('api-access')->plainTextToken;

        $user->update(['last_login_at' => now()]);

        $session = UserSession::createSession(
            $user->id,
            hash('sha256', $token),
            $request->ip(),
            $request->userAgent(),
            $warehouseId
        );

        $user->load(['role', 'warehouse']);

        $accessibleWarehouses = $user->getAccessibleWarehouseIds();
        $warehouses = \App\Models\Warehouse::whereIn('id', $accessibleWarehouses)
            ->where('status', 'active')
            ->get(['id', 'code', 'name']);

        $defaultWarehouse = null;
        if ($warehouseId) {
            $defaultWarehouse = $warehouses->firstWhere('id', $warehouseId);
        } elseif ($user->warehouse_id) {
            $defaultWarehouse = $warehouses->firstWhere('id', $user->warehouse_id);
        } else {
            $defaultWarehouse = $warehouses->first();
        }

        return response()->json([
            'success' => true,
            'message' => 'Đăng nhập thành công',
            'data' => [
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'employee_code' => $user->employee_code,
                    'phone' => $user->phone,
                    'avatar' => $user->avatar,
                    'status' => $user->status,
                    'role' => [
                        'id' => $user->role->id,
                        'name' => $user->role->name,
                        'display_name' => $user->role->display_name,
                        'permissions' => $user->role->permissions
                    ],
                    'warehouse' => $user->warehouse ? [
                        'id' => $user->warehouse->id,
                        'code' => $user->warehouse->code,
                        'name' => $user->warehouse->name
                    ] : null
                ],
                'token' => $token,
                'token_type' => 'Bearer',
                'permissions' => $user->role->permissions,
                'accessible_warehouses' => $warehouses,
                'default_warehouse' => $defaultWarehouse,
                'session_id' => $session->id,
                'redirect_path' => $this->getRedirectPath($user, $defaultWarehouse)
            ]
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $user = Auth::user();
        
        if ($user) {
            $token = $request->bearerToken();
            if ($token) {
                $hashedToken = hash('sha256', $token);
                UserSession::where('session_token', $hashedToken)
                    ->where('user_id', $user->id)
                    ->update([
                        'logout_at' => now(),
                        'is_active' => false
                    ]);
            }

            $user->tokens()->delete();
        }

        return response()->json([
            'success' => true,
            'message' => 'Đăng xuất thành công'
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        $user = Auth::user();
        $user->load(['role', 'warehouse']);

        $accessibleWarehouses = $user->getAccessibleWarehouseIds();
        $warehouses = \App\Models\Warehouse::whereIn('id', $accessibleWarehouses)
            ->where('status', 'active')
            ->get(['id', 'code', 'name']);

        return response()->json([
            'success' => true,
            'data' => [
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'employee_code' => $user->employee_code,
                    'phone' => $user->phone,
                    'avatar' => $user->avatar,
                    'status' => $user->status,
                    'role' => [
                        'id' => $user->role->id,
                        'name' => $user->role->name,
                        'display_name' => $user->role->display_name,
                        'permissions' => $user->role->permissions
                    ],
                    'warehouse' => $user->warehouse ? [
                        'id' => $user->warehouse->id,
                        'code' => $user->warehouse->code,
                        'name' => $user->warehouse->name
                    ] : null
                ],
                'permissions' => $user->role->permissions,
                'accessible_warehouses' => $warehouses
            ]
        ]);
    }

    public function switchWarehouse(Request $request): JsonResponse
    {
        $request->validate([
            'warehouse_id' => 'required|exists:warehouses,id'
        ]);

        $user = Auth::user();
        $warehouseId = $request->warehouse_id;

        if (!$user->hasWarehouseAccess($warehouseId)) {
            return response()->json([
                'success' => false,
                'message' => 'Không có quyền truy cập kho này'
            ], 403);
        }

        $token = $request->bearerToken();
        if ($token) {
            $hashedToken = hash('sha256', $token);
            UserSession::where('session_token', $hashedToken)
                ->where('user_id', $user->id)
                ->update(['warehouse_id' => $warehouseId]);
        }

        $warehouse = \App\Models\Warehouse::find($warehouseId);

        return response()->json([
            'success' => true,
            'message' => 'Chuyển kho thành công',
            'data' => [
                'current_warehouse' => [
                    'id' => $warehouse->id,
                    'code' => $warehouse->code,
                    'name' => $warehouse->name
                ],
                'redirect_path' => $this->getRedirectPath($user, $warehouse)
            ]
        ]);
    }

    private function getRedirectPath($user, $warehouse = null): string
    {
        $warehouseId = $warehouse ? $warehouse->id : ($user->warehouse_id ?? '');
        
        switch ($user->role->name) {
            case 'super_admin':
            case 'admin':
                return '/dashboard';
            case 'warehouse_manager':
                return $warehouseId ? "/warehouses" : '/dashboard';
            case 'warehouse_staff':
                return $warehouseId ? "/products" : '/dashboard';
            case 'cashier':
                return '/dashboard';
            case 'viewer':
                return '/dashboard';
            default:
                return '/dashboard';
        }
    }

    /**
     * API Login endpoint without 2FA requirement for testing
     */
    public function apiLogin(Request $request): JsonResponse
    {
        \Log::info('API Login attempt', [
            'email' => $request->email,
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent()
        ]);

        $request->validate([
            'email' => 'required|email',
            'password' => 'required|string'
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            \Log::warning('API Login failed - Invalid credentials', [
                'email' => $request->email,
                'user_exists' => !!$user
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Email hoặc mật khẩu không đúng'
            ], 401);
        }

        if (!$user->isActive()) {
            \Log::warning('API Login failed - Inactive user', ['user_id' => $user->id]);
            
            return response()->json([
                'success' => false,
                'message' => 'Tài khoản đã bị khóa hoặc ngưng hoạt động'
            ], 403);
        }

        // Check if 2FA is enabled for this user
        if ($user->hasTwoFactorEnabled()) {
            \Log::info('API Login requires 2FA', ['user_id' => $user->id]);
            
            // Store user info in session for 2FA verification
            session(['2fa_user_id' => $user->id, '2fa_remember' => $request->boolean('remember')]);
            
            return response()->json([
                'success' => true,
                'requires_2fa' => true,
                'message' => 'Vui lòng nhập mã 2FA',
                'user_id' => $user->id
            ], 200);
        }

        // Login successful without 2FA
        $user->update(['last_login_at' => now()]);
        
        Auth::login($user);
        
        \Log::info('API Login successful', [
            'user_id' => $user->id,
            'email' => $user->email
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Đăng nhập thành công',
            'redirect' => $this->getRedirectPath($user),
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role->name ?? 'user'
            ]
        ]);
    }
}