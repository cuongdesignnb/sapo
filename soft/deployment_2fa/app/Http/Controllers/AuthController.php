<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\User;
use App\Models\UserSession;
use App\Models\Warehouse;
use OTPHP\TOTP;

class AuthController extends Controller
{
    public function showLogin()
    {
        if (Auth::check()) {
            return redirect('/dashboard');
        }
        return view('auth.login');
    }

    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required|string'
        ]);

        $credentials = $request->only('email', 'password');
        
        if (Auth::attempt($credentials, $request->boolean('remember'))) {
            $user = Auth::user();
            
            // ===== FORCE LOGOUT TẤT CẢ SESSION CŨ =====
            // Xóa tất cả tokens cũ
            $user->tokens()->delete();
            
            // Đánh dấu tất cả UserSession cũ không active
            UserSession::where('user_id', $user->id)
                ->where('is_active', true)
                ->update([
                    'is_active' => false,
                    'logout_at' => now()
                ]);
                
            // Force regenerate session để invalidate tất cả session cũ
            $request->session()->invalidate();
            $request->session()->regenerate(true);
            
            // Login lại sau khi invalidate
            Auth::loginUsingId($user->id, $request->boolean('remember'));
            // ========================================
            
            // Kiểm tra user có active không
            if (!$user->isActive()) {
                Auth::logout();
                return back()->withErrors(['email' => 'Tài khoản đã bị khóa']);
            }

            // ===== CHECK 2FA =====
            if ($user->hasTwoFactorEnabled()) {
                // Logout tạm thời và lưu thông tin cho bước 2
                Auth::logout();
                $request->session()->put('2fa_user_id', $user->id);
                $request->session()->put('2fa_remember', $request->boolean('remember'));
                
                if ($request->expectsJson()) {
                    return response()->json([
                        'success' => true,
                        'need_2fa' => true,
                        'message' => 'Vui lòng nhập mã xác thực 2 bước'
                    ]);
                }
                
                return redirect()->route('2fa.challenge')
                    ->with('message', 'Vui lòng nhập mã xác thực 2 bước');
            }
            // ===== END 2FA CHECK =====

            // Lấy warehouse mặc định của user
            $defaultWarehouseId = $user->getDefaultWarehouseId();
            
            // Tạo token cho API access
            $token = $user->createToken('web-access')->plainTextToken;
            session(['api_token' => $token]);
            
            // Lưu session cho tracking
            $sessionToken = $request->session()->getId();
            UserSession::create([
                'user_id' => $user->id,
                'session_token' => $sessionToken,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'warehouse_id' => $defaultWarehouseId,
                'is_active' => true
            ]);

            // Xác định route redirect dựa trên role và warehouse
            $redirectRoute = $this->getRedirectRouteForUser($user, $defaultWarehouseId);
            
            return redirect()->intended($redirectRoute);
        }

        return back()->withErrors(['email' => 'Thông tin đăng nhập không đúng']);
    }

    public function logout(Request $request)
    {
        $user = Auth::user();
        if ($user) {
            // Xóa tất cả tokens
            $user->tokens()->delete();
            
            // Đánh dấu session không active
            UserSession::where('user_id', $user->id)
                ->where('session_token', $request->session()->getId())
                ->update([
                    'is_active' => false,
                    'logout_at' => now()
                ]);
        }
        
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/login');
    }

    /**
     * Xác định route redirect phù hợp cho user
     */
    private function getRedirectRouteForUser($user, $warehouseId)
    {
        // Super admin và admin vào dashboard chung
        if ($user->hasAnyRole(['super_admin', 'admin'])) {
            return '/dashboard';
        }
        
        // Các role khác cần warehouse
        if (!$warehouseId) {
            // Nếu không có warehouse, logout và redirect về login
            Auth::logout();
            session()->flash('error', 'Tài khoản chưa được phân quyền truy cập kho nào');
            return '/login';
        }
        
        // Cashier vào POS trực tiếp
        if ($user->hasRole('cashier')) {
            return "/pos?warehouse_id={$warehouseId}";
        }
        
        // Warehouse staff, manager vào dashboard warehouse
        if ($user->hasAnyRole(['warehouse_manager', 'warehouse_staff'])) {
            return "/dashboard?warehouse_id={$warehouseId}";
        }
        
        // Fallback cho các role khác
        return "/dashboard?warehouse_id={$warehouseId}";
    }

    /**
     * Show the 2FA challenge form
     */
    public function show2FAChallenge()
    {
        // Check if user is in the middle of 2FA process
        if (!session('2fa_user_id')) {
            return redirect()->route('login')->with('error', 'Phiên đăng nhập đã hết hạn');
        }

        return view('auth.2fa-challenge');
    }

    /**
     * Verify 2FA code and complete login
     */
    public function verify2FA(Request $request)
    {
        $request->validate([
            'code' => 'required|string|size:6'
        ]);

        $userId = session('2fa_user_id');
        if (!$userId) {
            return redirect()->route('login')->with('error', 'Phiên đăng nhập đã hết hạn');
        }

        $user = User::find($userId);
        if (!$user) {
            return redirect()->route('login')->with('error', 'Không tìm thấy người dùng');
        }

        // Verify TOTP code
        $totp = TOTP::create($user->two_factor_secret);
        if (!$totp->verify($request->code)) {
            return back()->withErrors(['code' => 'Mã xác thực không đúng']);
        }

        // Complete login process
        Auth::login($user);
        
        // Clear 2FA session data
        session()->forget(['2fa_user_id', '2fa_remember']);
        session(['2fa_passed' => true]);

        // Get user's default warehouse
        $defaultWarehouseId = $user->getDefaultWarehouseId();
        
        // Create API token
        $token = $user->createToken('web-access')->plainTextToken;
        session(['api_token' => $token]);
        
        // Create user session record
        $sessionToken = $request->session()->getId();
        UserSession::create([
            'user_id' => $user->id,
            'session_token' => $sessionToken,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'warehouse_id' => $defaultWarehouseId,
            'is_active' => true
        ]);

        // Redirect to intended page
        $redirectRoute = $this->getRedirectRouteForUser($user, $defaultWarehouseId);
        return redirect()->intended($redirectRoute);
    }
}