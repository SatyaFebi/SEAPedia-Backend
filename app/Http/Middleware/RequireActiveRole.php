<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class RequireActiveRole
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next, ?string $requiredRole = null)
    {
        $user = auth()->user();

        if (! $user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        // Fetch user roles
        $roles = $user->roles()->pluck('role')->toArray();

        // If a specific role is requested, verify user owns it
        if ($requiredRole) {
            if (! in_array($requiredRole, $roles)) {
                return response()->json(['message' => "Forbidden: You do not possess the {$requiredRole} role."], 403);
            }

            // Verify X-Active-Role matches the required role
            $activeRole = $request->header('X-Active-Role');

            if (! $activeRole) {
                return response()->json(['message' => 'Forbidden: Active role header (X-Active-Role) is missing.'], 403);
            }

            if ($activeRole !== $requiredRole) {
                return response()->json(['message' => "Forbidden: Your active role session is not set to {$requiredRole}."], 403);
            }
        }

        return $next($request);
    }
}
