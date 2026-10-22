<?php

namespace App\Http\Middleware;

use App\Support\LocaleCatalog;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        // What they chose on their account wins: it followed them here
        // from another browser, and it is the same value every mail we
        // send them is written in.
        $account = Auth::guard('web')->user() ?? Auth::guard('portal')->user();

        $locale = LocaleCatalog::detect(
            stored: $account?->language ?: $request->cookie(LocaleCatalog::COOKIE),
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
