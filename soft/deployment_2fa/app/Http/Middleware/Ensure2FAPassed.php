<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class Ensure2FAPassed
{
    /**
     * Handle an incoming request.
     * 
     * Middleware này kiểm tra: nếu user đã bật 2FA nhưng chưa pass bước 2FA trong session hiện tại
     * thì block request và yêu cầu xác thực 2FA.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::user();
        
        // Nếu chưa đăng nhập hoặc chưa bật 2FA thì cho qua
        if (!$user || !$user->two_factor_enabled_at) {
            return $next($request);
        }
        
        // Nếu đã pass 2FA trong session này thì cho qua
        if ($request->session()->get('2fa_passed') === true) {
            return $next($request);
        }
        
        // Nếu đang ở route 2FA challenge thì cho qua (tránh redirect loop)
        if ($request->routeIs('2fa.*') || $request->routeIs('logout')) {
            return $next($request);
        }
        
        // Chặn và yêu cầu 2FA
        if ($request->expectsJson()) {
            return response()->json([
                'success' => false,
                'message' => 'Yêu cầu xác thực 2FA',
                'need_2fa' => true
            ], 403);
        }
        
        // Web request: redirect đến trang 2FA challenge
        return redirect()->route('2fa.challenge')
            ->with('message', 'Vui lòng nhập mã 2FA để tiếp tục');
    }
}