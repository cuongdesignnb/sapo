<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\UserSession;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use OTPHP\TOTP;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;

class TwoFactorController extends Controller
{
    /**
     * Setup 2FA - tạo secret và QR code
     */
    public function setup(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            
            if ($user->hasTwoFactorEnabled()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Bạn đã bật xác thực 2 bước'
                ], 400);
            }
            
            // Tạo TOTP secret mới
            $totp = TOTP::create();
            $totp->setLabel($user->email);
            $totp->setIssuer(config('app.name', 'ViteSoft'));
            
            $secret = $totp->getSecret();
            $otpauthUrl = $totp->getProvisioningUri();
            
            return response()->json([
                'success' => true,
                'data' => [
                    'secret' => $secret,
                    'otpauth_url' => $otpauthUrl,
                    'manual_entry_key' => $secret,
                    'app_name' => config('app.name', 'ViteSoft'),
                    'user_email' => $user->email
                ]
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Có lỗi xảy ra: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Xác nhận và kích hoạt 2FA
     */
    public function confirm(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'code' => 'required|string|size:6',
                'secret' => 'required|string'
            ]);
            
            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Mã xác thực phải có 6 số và secret là bắt buộc',
                    'errors' => $validator->errors()
                ], 422);
            }
            
            $user = Auth::user();
            $secret = $request->secret;
            
            if ($user->hasTwoFactorEnabled()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Bạn đã bật xác thực 2 bước'
                ], 400);
            }
            
            // Verify TOTP code với secret
            $totp = TOTP::create($secret);
            if (!$totp->verify($request->code)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Mã xác thực không đúng'
                ], 400);
            }
            
            // Kích hoạt 2FA cho user
            $user->enableTwoFactor($secret);

            // Return plain recovery codes (hashed versions are stored).
            $recoveryCodes = $user->regenerateRecoveryCodes();
            
            return response()->json([
                'success' => true,
                'message' => 'Xác thực 2 bước đã được kích hoạt thành công',
                'data' => [
                    'recovery_codes' => $recoveryCodes
                ]
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Có lỗi xảy ra: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Handle 2FA challenge during login
     */
    public function challenge(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'code' => 'required|string|size:6'
            ]);
            
            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Mã xác thực phải có 6 số',
                    'errors' => $validator->errors()
                ], 422);
            }
            
            // Get user from 2FA session
            $userId = session('2fa_user_id');
            if (!$userId) {
                return response()->json([
                    'success' => false,
                    'message' => 'Phiên đăng nhập đã hết hạn'
                ], 401);
            }
            
            $user = \App\Models\User::find($userId);
            if (!$user || !$user->hasTwoFactorEnabled()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Người dùng không hợp lệ'
                ], 400);
            }
            
            $secret = $user->getTwoFactorSecret();
            if (!$secret) {
                $user->disableTwoFactor();
                session()->forget(['2fa_user_id', '2fa_remember']);

                return response()->json([
                    'success' => false,
                    'message' => '2FA cần được thiết lập lại (do thay đổi cấu hình)'
                ], 400);
            }

            // Verify TOTP code
            $totp = TOTP::createFromSecret($secret);
            if (!$totp->verify($request->code)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Mã xác thực không đúng'
                ], 400);
            }
            
            // Complete login
            Auth::login($user);
            session()->forget(['2fa_user_id', '2fa_remember']);
            session(['2fa_passed' => true]);
            
            // Create token
            $token = $user->createToken('api-access')->plainTextToken;
            
            return response()->json([
                'success' => true,
                'message' => 'Đăng nhập thành công',
                'data' => [
                    'user' => $user->load('roles'),
                    'token' => $token,
                    'token_type' => 'Bearer'
                ]
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Có lỗi xảy ra: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Disable 2FA
     */
    public function disable(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            
            if (!$user->hasTwoFactorEnabled()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Bạn chưa bật xác thực 2 bước'
                ], 400);
            }
            
            $user->disableTwoFactor();
            
            return response()->json([
                'success' => true,
                'message' => 'Xác thực 2 bước đã được tắt'
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Có lỗi xảy ra: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Regenerate recovery codes
     */
    public function regenerateRecoveryCodes(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            
            if (!$user->hasTwoFactorEnabled()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Bạn chưa bật xác thực 2 bước'
                ], 400);
            }
            
            $recoveryCodes = $user->regenerateRecoveryCodes();
            
            return response()->json([
                'success' => true,
                'message' => 'Mã khôi phục đã được tạo mới',
                'data' => [
                    'recovery_codes' => $recoveryCodes
                ]
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Có lỗi xảy ra: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get 2FA status
     */
    public function status(): JsonResponse
    {
        try {
            $user = Auth::user();
            
            return response()->json([
                'success' => true,
                'data' => [
                    'enabled' => $user->hasTwoFactorEnabled(),
                    'enabled_at' => $user->two_factor_enabled_at,
                    'recovery_codes_count' => $user->getRecoveryCodes() ? count($user->getRecoveryCodes()) : 0
                ]
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Có lỗi xảy ra: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Verify 2FA code during login challenge
     */
    public function verify(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'code' => 'required|string|size:6'
            ]);

            $userId = session('2fa_user_id');
            if (!$userId) {
                return response()->json([
                    'success' => false,
                    'message' => 'Phiên đăng nhập đã hết hạn'
                ], 400);
            }

            $user = User::find($userId);
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Không tìm thấy người dùng'
                ], 400);
            }

            $secret = $user->getTwoFactorSecret();
            if (!$secret) {
                $user->disableTwoFactor();
                session()->forget(['2fa_user_id', '2fa_remember']);

                return response()->json([
                    'success' => false,
                    'message' => '2FA cần được thiết lập lại (do thay đổi cấu hình)'
                ], 400);
            }

            // Verify TOTP code
            $totp = TOTP::createFromSecret($secret);
            if (!$totp->verify($request->code, null, 1)) { // Window of 1 (±30 seconds)
                return response()->json([
                    'success' => false,
                    'message' => 'Mã xác thực không đúng'
                ], 400);
            }

            // Complete login
            Auth::login($user);
            session()->forget(['2fa_user_id', '2fa_remember']);
            session(['2fa_passed' => true]);

            // Create token
            $token = $user->createToken('api-access')->plainTextToken;
            
            // Get user's default warehouse  
            $defaultWarehouseId = $user->getDefaultWarehouseId();
            
            // Create user session record
            $sessionToken = session()->getId();
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
                'message' => 'Xác thực thành công',
                'redirect' => $redirectRoute,
                'token' => $token,
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $user->role
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Có lỗi xảy ra: ' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Get redirect route for user based on role and warehouse
     */
    private function getRedirectRouteForUser($user, $defaultWarehouseId)
    {
        if ($user->role === 'super_admin') {
            return '/dashboard';
        }
        
        if ($user->role === 'admin' || $user->role === 'warehouse_manager') {
            return $defaultWarehouseId ? "/warehouses/{$defaultWarehouseId}/dashboard" : '/dashboard';
        }
        
        return '/dashboard';
    }
}