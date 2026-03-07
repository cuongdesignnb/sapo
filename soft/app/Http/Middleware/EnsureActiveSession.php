<?php

namespace App\Http\Middleware;

use App\Models\UserSession;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureActiveSession
{
    public function handle(Request $request, Closure $next): Response
    {
        if (!Auth::check()) {
            return $next($request);
        }

        $user = Auth::user();
        $sessionId = $request->session()->getId();

        $session = UserSession::where('user_id', $user->id)
            ->where('session_token', $sessionId)
            ->first();

        // ===== ALLOW MULTIPLE CONCURRENT SESSIONS =====
        // Removed the check that kicked out other sessions
        // Multiple users can now login with the same account simultaneously
        // ===============================================

        // If there is no tracking record yet (legacy / edge cases),
        // create a record for this session.
        if (!$session) {
            UserSession::create([
                'user_id' => $user->id,
                'session_token' => $sessionId,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'warehouse_id' => $user->getDefaultWarehouseId(),
                'login_at' => now(),
                'last_activity' => now(),
                'is_active' => true,
            ]);

            return $next($request);
        }

        if (!$session->is_active) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Phiên đăng nhập đã hết hạn. Vui lòng đăng nhập lại.',
                    'reason' => 'session_expired',
                ], 401);
            }

            return redirect()->route('login')->with('error', 'Phiên đăng nhập đã hết hạn. Vui lòng đăng nhập lại.');
        }

        return $next($request);
    }
}
