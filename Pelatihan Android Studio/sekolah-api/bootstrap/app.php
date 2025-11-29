<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        api: __DIR__ . '/../routes/api.php',
        apiPrefix: 'api',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Global middleware untuk mencegah user web masuk ke API routes
        $middleware->web(append: [
            \App\Http\Middleware\EnsureWebRequest::class,
        ]);

        // CRITICAL FIX: Temporarily disable custom middleware for debugging
        // $middleware->api(prepend: [
        //     \App\Http\Middleware\EnsureApiRequest::class,
        //     \App\Http\Middleware\PerformanceMonitoring::class,
        // ]);

        $middleware->alias([
            'role' => \App\Http\Middleware\RoleMiddleware::class,
            'ensure.web' => \App\Http\Middleware\EnsureWebRequest::class,
            'ensure.api' => \App\Http\Middleware\EnsureApiRequest::class,
            'throttle' => \Illuminate\Routing\Middleware\ThrottleRequests::class,
            'throttle.custom' => \App\Http\Middleware\RequestThrottling::class,
            'circuit.breaker' => \App\Http\Middleware\CircuitBreakerMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Custom handling untuk memastikan web request tidak mendapat JSON response
        $exceptions->render(function (\Throwable $e, $request) {
            // Jika ini request ke route web dan bukan AJAX/API request
            if (
                !$request->is('api/*') &&
                !$request->ajax() &&
                !$request->wantsJson() &&
                $request->header('Accept') &&
                str_contains($request->header('Accept'), 'text/html')
            ) {

                // Handle 404 errors untuk web interface
                if ($e instanceof \Symfony\Component\HttpKernel\Exception\NotFoundHttpException) {
                    return response()->view('errors.404', [], 404);
                }

                // Handle authentication errors untuk web interface
                if ($e instanceof \Illuminate\Auth\AuthenticationException) {
                    return redirect()->route('login')->with('error', 'Silakan login terlebih dahulu.');
                }

                // Handle authorization errors untuk web interface
                if ($e instanceof \Illuminate\Auth\Access\AuthorizationException) {
                    return back()->with('error', 'Anda tidak memiliki akses untuk melakukan tindakan ini.');
                }
            }

            return null; // Let Laravel handle other cases normally
        });
    })->create();
