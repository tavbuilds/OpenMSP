<?php

use App\Http\Controllers\Api\V1\CompanyController;
use App\Http\Controllers\Api\V1\ContactController;
use App\Http\Controllers\Api\V1\ContractController;
use App\Http\Controllers\Api\V1\DashboardController;
use App\Http\Controllers\Api\V1\DomainController;
use App\Http\Controllers\Api\V1\EndpointController;
use App\Http\Controllers\Api\V1\PlannedTaskController;
use App\Http\Controllers\Api\V1\ProductComponentController;
use App\Http\Controllers\Api\V1\ProductController;
use App\Http\Controllers\Api\V1\PurchaseBundleController;
use App\Http\Controllers\Api\V1\TokenController;
use App\Http\Controllers\Api\V1\UptimeKumaController;
use App\Http\Controllers\Api\V1\VendorController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Agent / machine JSON API (v1)
|--------------------------------------------------------------------------
|
| All routes require a Sanctum personal access token
| (Authorization: Bearer <token>). Role checks mirror Filament:
| - viewer: read-only
| - sales+: create/update
| - admin/manager: delete
|
*/

Route::prefix('v1')
    ->middleware('auth:sanctum')
    ->group(function () {
        Route::get('dashboard', [DashboardController::class, 'show']);

        Route::apiResource('companies', CompanyController::class);
        Route::get('companies/{company}/contacts', [ContactController::class, 'indexByCompany']);
        Route::post('companies/{company}/contacts', [ContactController::class, 'storeByCompany']);

        Route::apiResource('contacts', ContactController::class);

        // Static path before apiResource so "upcoming-renewals" is not treated as an id.
        Route::get('contracts/upcoming-renewals', [ContractController::class, 'upcomingRenewals']);
        Route::apiResource('contracts', ContractController::class);

        Route::apiResource('products', ProductController::class);
        Route::get('products/{product}/components', [ProductComponentController::class, 'indexByProduct']);
        Route::post('products/{product}/components', [ProductComponentController::class, 'storeByProduct']);

        Route::apiResource('product-components', ProductComponentController::class);
        Route::apiResource('purchase-bundles', PurchaseBundleController::class);
        Route::apiResource('vendors', VendorController::class);

        Route::get('planned-tasks/upcoming', [PlannedTaskController::class, 'upcoming']);
        Route::apiResource('planned-tasks', PlannedTaskController::class)
            ->parameters(['planned-tasks' => 'plannedTask']);

        Route::get('domains/upcoming-expiries', [DomainController::class, 'upcoming']);
        Route::post('domains/assign', [DomainController::class, 'assign']);
        Route::apiResource('domains', DomainController::class);

        Route::get('endpoints/upcoming', [EndpointController::class, 'upcoming']);
        Route::post('endpoints/{endpoint}/rotate-webhook', [EndpointController::class, 'rotateWebhook']);
        Route::apiResource('endpoints', EndpointController::class);

        Route::get('integrations/uptime-kuma', [UptimeKumaController::class, 'show']);
        Route::match(['put', 'patch'], 'integrations/uptime-kuma', [UptimeKumaController::class, 'update']);
        Route::post('integrations/uptime-kuma/test', [UptimeKumaController::class, 'test']);
        Route::post('integrations/uptime-kuma/sync', [UptimeKumaController::class, 'sync']);
        Route::post('integrations/uptime-kuma/webhooks', [UptimeKumaController::class, 'pushWebhooks']);

        // Own Sanctum tokens only (list / create / revoke).
        Route::get('tokens', [TokenController::class, 'index']);
        Route::post('tokens', [TokenController::class, 'store']);
        Route::delete('tokens/{token}', [TokenController::class, 'destroy']);
    });
