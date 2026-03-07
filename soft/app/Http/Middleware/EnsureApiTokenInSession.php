<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;

class EnsureApiTokenInSession
{
    /**
     * Ensure authenticated web sessions always have a Sanctum token
     * available for SPA-style API calls (via meta[name="api-token"]).
     */
    public function handle(Request $request, Closure $next)
    {
        if (auth()->check()) {
            $tokenId = session('api_token_id');
            $tokenOk = false;

            if (is_numeric($tokenId)) {
                $tokenOk = PersonalAccessToken::where('id', (int) $tokenId)
                    ->where('tokenable_type', get_class(auth()->user()))
                    ->where('tokenable_id', auth()->id())
                    ->exists();
            }

            if (!$tokenOk) {
                $newToken = auth()->user()->createToken('web-access');
                session([
                    'api_token' => $newToken->plainTextToken,
                    'api_token_id' => $newToken->accessToken->id,
                ]);
            }
        }

        return $next($request);
    }
}
