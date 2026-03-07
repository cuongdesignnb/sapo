<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\UserSession;
use Symfony\Component\HttpFoundation\Response;

class UpdateLastActivity
{
    public function handle(Request $request, Closure $next): Response
    {
        if (Auth::check()) {
            $user = Auth::user();
            
            // Update user's last login
            $user->update(['last_login_at' => now()]);
            
            // Update session activity
            $token = $request->bearerToken();
            if ($token) {
                $hashedToken = hash('sha256', $token);
                UserSession::where('session_token', $hashedToken)
                    ->where('user_id', $user->id)
                    ->where('is_active', true)
                    ->update(['last_activity' => now()]);
            } else {
                // For web sessions
                UserSession::where('session_token', session()->getId())
                    ->where('user_id', $user->id)
                    ->where('is_active', true)
                    ->update(['last_activity' => now()]);
            }
        }

        return $next($request);
    }
}