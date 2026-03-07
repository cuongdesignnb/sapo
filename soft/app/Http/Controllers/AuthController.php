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
        \Log::info('=== WEB LOGIN METHOD CALLED ===', [
            'email' => $request->email,
            'expects_json' => $request->expectsJson(),
            'ajax' => $request->ajax(),
            'content_type' => $request->header('Content-Type'),
            'accept' => $request->header('Accept')
        ]);
        
        try {
            $request->validate([
                'email' => 'required|email',
                'password' => 'required|string'
            ]);

            $credentials = $request->only('email', 'password');
            
            if (Auth::attempt($credentials, $request->boolean('remember'))) {
                $user = Auth::user();
                
                \Log::info('Login successful', ['user_id' => $user->id]);
            
            // Kiểm tra user có active không
            if (!$user->isActive()) {
                Auth::logout();
                return back()->withErrors(['email' => 'Tài khoản đã bị khóa']);
            }

            // ===== CHECK 2FA =====
            \Log::info('2FA Check', [
                'user_id' => $user->id,
                'email' => $user->email,
                'has_2fa' => $user->hasTwoFactorEnabled(),
                'two_factor_secret' => !empty($user->two_factor_secret),
                'two_factor_confirmed_at' => $user->two_factor_confirmed_at,
                'session_id_before' => $request->session()->getId()
            ]);
            
            if ($user->hasTwoFactorEnabled()) {
                \Log::info('User has 2FA enabled, starting 2FA flow');
                
                // Logout tạm thời và lưu thông tin cho bước 2
                Auth::logout();
                
                // Force save session data trước khi redirect
                $request->session()->put('2fa_user_id', $user->id);
                $request->session()->put('2fa_remember', $request->boolean('remember'));
                $request->session()->save(); // Force save session
                
                \Log::info('Session saved', [
                    '2fa_user_id' => $request->session()->get('2fa_user_id'),
                    'session_id_after' => $request->session()->getId(),
                    'all_session' => $request->session()->all()
                ]);
                
                if ($request->expectsJson()) {
                    return response()->json([
                        'success' => true,
                        'need_2fa' => true,
                        'message' => 'Vui lòng nhập mã xác thực 2 bước'
                    ]);
                }
                
                \Log::info('Redirecting to 2fa.challenge');
                return redirect()->route('2fa.challenge')
                    ->with('message', 'Vui lòng nhập mã xác thực 2 bước');
            }
            // ===== END 2FA CHECK =====

            // Lấy warehouse mặc định của user
            $defaultWarehouseId = $user->getDefaultWarehouseId();
            
            // Tạo token cho API access
            $newToken = $user->createToken('web-access');
            session([
                'api_token' => $newToken->plainTextToken,
                'api_token_id' => $newToken->accessToken->id,
            ]);
            
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
            
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Đăng nhập thành công',
                    'redirect' => $redirectRoute
                ]);
            }
            
            return redirect()->intended($redirectRoute);
        }

        \Log::warning('Login failed', ['email' => $request->email]);
        
        if ($request->expectsJson()) {
            return response()->json([
                'success' => false,
                'message' => 'Thông tin đăng nhập không đúng',
                'errors' => ['email' => ['Thông tin đăng nhập không đúng']]
            ], 422);
        }

        return back()->withErrors(['email' => 'Thông tin đăng nhập không đúng']);
        
        } catch (\Exception $e) {
            \Log::error('Login exception', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Có lỗi xảy ra khi đăng nhập'
                ], 500);
            }
            
            return back()->withErrors(['email' => 'Có lỗi xảy ra khi đăng nhập']);
        }
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
     * GET endpoint for forced logout.
     * Used by frontend interceptors to avoid redirect loops when API tokens are invalid.
     */
    public function forceLogout(Request $request)
    {
        $message = 'Phiên đăng nhập đã hết hạn. Vui lòng đăng nhập lại.';

        if (Auth::check()) {
            $user = Auth::user();

            UserSession::where('user_id', $user->id)
                ->where('session_token', $request->session()->getId())
                ->update([
                    'is_active' => false,
                    'logout_at' => now(),
                ]);

            Auth::logout();
        }

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->with('error', $message);
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
        \Log::info('show2FAChallenge called', [
            '2fa_user_id' => session('2fa_user_id'),
            'session_id' => request()->session()->getId(),
            'all_session_data' => session()->all()
        ]);
        
        // Check if user is in the middle of 2FA process
        if (!session('2fa_user_id')) {
            \Log::warning('No 2fa_user_id in session, redirecting to login');
            return redirect()->route('login')->with('error', 'Phiên đăng nhập đã hết hạn');
        }

        \Log::info('Showing 2FA challenge view');
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

        $secret = $user->getTwoFactorSecret();
        if (!$secret) {
            // Typically happens if APP_KEY was changed or legacy secrets were stored incorrectly.
            // Disable 2FA so user can log in and re-enable it.
            $user->disableTwoFactor();
            session()->forget(['2fa_user_id', '2fa_remember']);

            return redirect()->route('login')->with('error', '2FA cần được thiết lập lại (do thay đổi cấu hình). Vui lòng đăng nhập lại và bật 2FA.');
        }

        // Verify TOTP code
        $totp = TOTP::createFromSecret($secret);
        \Log::info('2FA Verification attempt', [
            'user_id' => $user->id,
            'verify_result' => $totp->verify($request->code, null, 1)
        ]);
        
        if (!$totp->verify($request->code, null, 1)) { // Window of 1 (±30 seconds)
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
        $newToken = $user->createToken('web-access');
        session([
            'api_token' => $newToken->plainTextToken,
            'api_token_id' => $newToken->accessToken->id,
        ]);
        
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

    /**
     * API Login endpoint - bypass CSRF issues
     */
    public function apiLogin(Request $request)
    {
        \Log::info('=== API LOGIN METHOD CALLED ===', [
            'email' => $request->email,
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent()
        ]);

        try {
            $request->validate([
                'email' => 'required|email',
                'password' => 'required|string'
            ]);

            $credentials = $request->only('email', 'password');
            
            if (Auth::attempt($credentials, $request->boolean('remember'))) {
                \Log::info('Auth::attempt successful');
                $user = Auth::user();
                
                \Log::info('API Login successful', ['user_id' => $user->id, 'email' => $user->email]);
                
                // Kiểm tra user có active không
                if (!$user->isActive()) {
                    \Log::info('User not active, logout');
                    Auth::logout();
                    return response()->json([
                        'success' => false,
                        'message' => 'Tài khoản đã bị khóa'
                    ], 422);
                }
                
                \Log::info('User is active, proceeding to 2FA check');

                // ===== CHECK 2FA =====
                \Log::info('Checking 2FA for user', [
                    'user_id' => $user->id,
                    'hasTwoFactorEnabled' => $user->hasTwoFactorEnabled(),
                    'two_factor_secret' => $user->two_factor_secret ? 'SET' : 'NULL',
                    'two_factor_confirmed_at' => $user->two_factor_confirmed_at
                ]);
                
                if ($user->hasTwoFactorEnabled()) {
                    \Log::info('User has 2FA enabled, API flow');
                    
                    // Logout tạm thời và lưu thông tin cho bước 2
                    Auth::logout();
                    $request->session()->put('2fa_user_id', $user->id);
                    $request->session()->put('2fa_remember', $request->boolean('remember'));
                    $request->session()->save();
                    
                    return response()->json([
                        'success' => true,
                        'requires_2fa' => true,
                        'message' => 'Vui lòng nhập mã xác thực 2 bước'
                    ]);
                }
                // ===== END 2FA CHECK =====

                // Normal login flow
                $defaultWarehouseId = $user->getDefaultWarehouseId();
                $newToken = $user->createToken('web-access');
                session([
                    'api_token' => $newToken->plainTextToken,
                    'api_token_id' => $newToken->accessToken->id,
                ]);
                
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

                $redirectRoute = $this->getRedirectRouteForUser($user, $defaultWarehouseId);
                
                return response()->json([
                    'success' => true,
                    'message' => 'Đăng nhập thành công',
                    'redirect' => $redirectRoute,
                    'user' => [
                        'id' => $user->id,
                        'name' => $user->name,
                        'email' => $user->email,
                        'role' => $user->role
                    ]
                ]);
            }

            \Log::warning('API Auth::attempt failed', ['credentials' => $credentials]);

            \Log::warning('API Login failed', ['email' => $request->email]);
            
            return response()->json([
                'success' => false,
                'message' => 'Thông tin đăng nhập không đúng'
            ], 422);
            
        } catch (\Exception $e) {
            \Log::error('API Login exception', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Có lỗi xảy ra khi đăng nhập'
            ], 500);
        }
    }
}