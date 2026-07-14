<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * RBAC Middleware — Penegakan di layer backend.
 *
 * Hierarki: ADMIN > TIER_2 > TIER_1
 * Return 403 Forbidden (bukan data ter-redact) jika role tidak memenuhi.
 *
 * @see SECURITY.md Section 3.3
 * @see AGENT.md Section 5 — Jangan bypass middleware ini untuk endpoint apapun
 */
class RbacMiddleware
{
    /**
     * Handle incoming request.
     *
     * Usage di route: ->middleware('rbac:TIER_2')
     *
     * @param string $minimumRole Role minimum yang diperlukan (TIER_1, TIER_2, ADMIN)
     */
    public function handle(Request $request, Closure $next, string $minimumRole): Response
    {
        $user = $request->user();

        if (! $user) {
            return response()->json([
                'message' => 'Unauthenticated.',
            ], 401);
        }

        if (! $user->hasMinimumRole($minimumRole)) {
            return response()->json([
                'message' => 'Forbidden. Minimum role required: ' . $minimumRole,
            ], 403);
        }

        return $next($request);
    }
}
