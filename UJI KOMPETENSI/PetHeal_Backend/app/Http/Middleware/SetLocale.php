<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $locale = session('app_locale', 'id');

        if ($request->has('lang') && in_array($request->input('lang'), ['en', 'id'])) {
            $locale = $request->input('lang');
            session(['app_locale' => $locale]);
        }

        app()->setLocale($locale);

        return $next($request);
    }
}