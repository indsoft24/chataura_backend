<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->api(append: [
            \App\Http\Middleware\GzipApiResponse::class,
        ]);

        $middleware->alias([
            'auth.api' => \App\Http\Middleware\AuthenticateApi::class,
            'admin' => \App\Http\Middleware\EnsureAdmin::class,
            'check.suspension' => \App\Http\Middleware\CheckUserSuspension::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Handle API errors with JSON response
        $exceptions->render(function (\Throwable $e, \Illuminate\Http\Request $request) {
            if ($request->is('api/*')) {
                \Log::error('API unhandled exception', [
                    'message' => $e->getMessage(),
                    'exception' => get_class($e),
                    'url' => $request->fullUrl(),
                    'method' => $request->method(),
                    'user_id' => optional($request->user())->id,
                ]);

                return response()->json([
                    'success' => false,
                    'error' => [
                        'code' => 'SERVER_ERROR',
                        'message' => config('app.debug') ? $e->getMessage() : 'Internal server error',
                    ],
                ], 500);
            }
        });
    })->create();
