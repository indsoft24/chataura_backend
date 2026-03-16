<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckUserSuspension
{
    /**
     * Block API access for suspended users. Run after auth so user is set.
     * Reloads user from DB so suspension state is always current (e.g. after unsuspend).
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (!$user) {
            return $next($request);
        }

        // Force fresh read from DB so recently unsuspended users are not blocked by stale state
        $user->refresh();

        if (!$user->isSuspended()) {
            return $next($request);
        }

        return response()->json([
            'success' => false,
            'error' => [
                'code' => 'ACCOUNT_SUSPENDED',
                'message' => 'Your account has been suspended.',
                'reason' => $user->suspended_reason,
                'suspended_until' => $user->suspended_until?->toIso8601String(),
            ],
        ], 403);
    }
}
