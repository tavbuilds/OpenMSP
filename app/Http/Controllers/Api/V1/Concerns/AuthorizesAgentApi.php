<?php

namespace App\Http\Controllers\Api\V1\Concerns;

use Illuminate\Http\Request;

trait AuthorizesAgentApi
{
    protected function authorizeRead(Request $request): void
    {
        abort_unless($request->user()?->role !== null, 403);
    }

    protected function authorizeWrite(Request $request): void
    {
        abort_unless($request->user()?->canManageContracts() ?? false, 403);
    }

    protected function authorizeDelete(Request $request): void
    {
        abort_unless($request->user()?->canAdminister() ?? false, 403);
    }
}
