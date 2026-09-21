<?php

use App\Http\Middleware\SetLocale;
use App\OAuth\McpOAuth;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function () {
            Route::middleware('web')
                ->group(base_path('routes/portal.php'));
            Route::middleware('api')
                ->group(base_path('routes/mcp.php'));
            require base_path('routes/oauth.php');
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->trustProxies(at: '*');
        $middleware->redirectGuestsTo(fn () => url('/admin/login'));
        $middleware->web(append: [
            SetLocale::class,
        ]);
        $middleware->validateCsrfTokens(except: [
            'stripe/webhook',
            'hooks/endpoints/*',
            'mcp',
            'oauth/token',
            'oauth/register',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->is('mcp') || $request->is('oauth/token') || $request->is('oauth/register'),
        );
        $exceptions->respond(function ($response, Throwable $e, Request $request) {
            if ($request->is('mcp') && $response->getStatusCode() === 401) {
                $meta = McpOAuth::protectedResourceMetadataUrl();
                $response->headers->set('WWW-Authenticate', 'Bearer realm="OpenMSP", resource_metadata="'.$meta.'"');
                $response->headers->set('Access-Control-Allow-Origin', '*');
                $response->headers->set('Access-Control-Allow-Headers', 'Authorization, Content-Type, Accept, MCP-Protocol-Version, Mcp-Session-Id');
            }

            return $response;
        });
    })->create();
