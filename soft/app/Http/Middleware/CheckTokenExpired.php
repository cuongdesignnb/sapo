<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class CheckTokenExpired
{
    public function handle(Request $request, Closure $next): Response
    {
        if (Auth::check()) {
            // TẠM THỜI BỎ CƠ CHẾ AUTO LOGOUT SAU 24H
            // Lý do: gây reload loop trên FE do nhiều interceptor cùng redirect /logout
            // Thay vào đó chỉ gắn header cảnh báo để FE có thể hiển thị thông báo nhẹ nếu muốn.
            // (Nếu sau này cần re-enable: chuyển sang idle timeout dựa trên last_activity thay vì created_at token)
            $request->headers->set('X-Session-Status', 'active');
        }

        return $next($request);
    }
}