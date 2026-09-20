<?php

namespace App\Http\Middleware;

use App\Support\LocaleCatalog;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $locale = LocaleCatalog::detect(
            stored: $request->cookie(LocaleCatalog::COOKIE),
            acceptLanguage: $request->header('Accept-Language'),
            platformDefault: (string) config('app.locale'),
        );

        app()->setLocale($locale);
        app()->setFallbackLocale(LocaleCatalog::DEFAULT);

        $response = $next($request);
        $response->headers->set('Content-Language', $locale);

        return $response;
    }
}
