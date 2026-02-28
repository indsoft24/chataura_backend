<?php

namespace App\Http\Middleware;

use App\Helpers\ApiResponse;
use App\Models\User;
use App\Services\JwtService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateApi
{
    public function __construct(
        private JwtService $jwtService
    ) {}

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->bearerToken();

        if (!$token) {
            return ApiResponse::unauthorized('Token not provided');
        }

        $user = $this->jwtService->getUserFromToken($token);

        if (!$user) {
            return ApiResponse::unauthorized('Invalid or expired token');
        }

        $request->setUserResolver(function () use ($user) {
            return $user;
        });

        // Heartbeat: update last_seen_at (throttle to at most once per minute)
        User::where('id', $user->id)->where(function ($q) {
            $q->whereNull('last_seen_at')->orWhere('last_seen_at', '<', now()->subMinute());
        })->update(['last_seen_at' => now()]);

        return $next($request);
    }
}

