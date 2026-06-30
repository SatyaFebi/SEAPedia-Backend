<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;

class AuthenticateToken
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next)
    {
        $token = $request->bearerToken();

        if ($token) {
            $user = User::where('api_token', $token)->first();
            if ($user) {
                auth()->setUser($user);
            }
        }

        return $next($request);
    }
}
