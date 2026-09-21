<?php

use App\Http\Controllers\McpController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Native MCP (Streamable HTTP)
|--------------------------------------------------------------------------
|
| Public URL: {APP_URL}/mcp
| Auth: Sanctum Bearer token (same tokens as /api/v1).
| Shown in admin → System → MCP. No sidecar install required.
|
*/

Route::options('/mcp', McpController::class);

Route::match(['GET', 'POST', 'DELETE'], '/mcp', McpController::class)
    ->middleware(['auth:sanctum', 'throttle:120,1']);
