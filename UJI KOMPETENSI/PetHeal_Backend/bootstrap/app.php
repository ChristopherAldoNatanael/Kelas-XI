<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        api: __DIR__ . '/../routes/api.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Register custom middleware aliases
        $middleware->alias([
            'admin.auth' => \App\Http\Middleware\AdminAuth::class,
            'super_admin' => \App\Http\Middleware\EnsureSuperAdmin::class,
        ]);

        // Add locale middleware to web group
        $middleware->web(prepend: [
            \App\Http\Middleware\SetLocale::class,
        ]);

        $middleware->append(\App\Http\Middleware\CacheStaticAssets::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // PHASE 2 (contract freeze): single canonical 401 for all API auth
        // failures. Behavior unchanged (still 401); only the message text is
        // unified. Android branches on status code, never on this string.
        $exceptions->render(function (\Illuminate\Auth\AuthenticationException $e, $request) {
            if ($request->is('api/*')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthenticated.'
                ], 401);
            }
            return null;
        });
        // PHASE 2: canonical validation envelope. Laravel default is
        // {message, errors} without `success`; Android enveloped guards treat
        // it the same, but the frozen contract guarantees `success: false`.
        // Shape of message/errors is Laravel-standard (additive `success`).
        $exceptions->render(function (\Illuminate\Validation\ValidationException $e, $request) {
            if ($request->is('api/*')) {
                return response()->json([
                    'success' => false,
                    'message' => $e->getMessage(),
                    'errors' => $e->errors(),
                ], 422);
            }
            return null;
        });
        // PHASE 2: canonical 404 envelope for model/route misses on API
        // (findOrFail paths previously leaked framework HTML/JSON shapes).
        $exceptions->render(function (\Symfony\Component\HttpKernel\Exception\NotFoundHttpException $e, $request) {
            if ($request->is('api/*')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Resource not found.',
                ], 404);
            }
            return null;
        });
        $exceptions->render(function (\Illuminate\Database\Eloquent\ModelNotFoundException $e, $request) {
            if ($request->is('api/*')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Resource not found.',
                ], 404);
            }
            return null;
        });
        // PHASE 3 (E-01): sanitize 500s on API. Several controllers echo
        // $e->getMessage() (SQLSTATE/paths) and APP_DEBUG may be true behind
        // a public URL. Generic envelope here; details stay server-side logs.
        // Sub-500 HTTP exceptions (405/429/...) pass through untouched.
        $exceptions->render(function (\Throwable $e, $request) {
            if (!$request->is('api/*')) {
                return null;
            }
            if ($e instanceof \Symfony\Component\HttpKernel\Exception\HttpExceptionInterface
                && $e->getStatusCode() < 500) {
                return null;
            }
            return response()->json([
                'success' => false,
                'message' => 'Internal server error.',
            ], 500);
        });
    })
    ->create();
