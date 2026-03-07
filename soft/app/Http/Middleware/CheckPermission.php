<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class CheckPermission
{
    public function handle(Request $request, Closure $next, string $permission): Response
    {
        $isApiRequest = $request->is('api/*') || $request->expectsJson();

        if (!Auth::check()) {
            if ($isApiRequest) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized'
                ], 401);
            }
            return redirect()->route('login');
        }

        $user = Auth::user();

        if (!$user->hasPermission($permission)) {
            if ($isApiRequest) {
                return response()->json([
                    'success' => false,
                    'message' => 'Không có quyền thực hiện hành động này'
                ], 403);
            }

            // If there's no safe "back" URL, fall back to dashboard.
            $previousUrl = url()->previous();
            if (!$previousUrl || $previousUrl === $request->fullUrl()) {
                return redirect('/dashboard')->with('error', 'Không có quyền truy cập');
            }

            return redirect()->back()->with('error', 'Không có quyền truy cập');
        }

        return $next($request);
    }
}