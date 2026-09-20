<?php

use App\Http\Controllers\EndpointWebhookController;
use App\Http\Controllers\LocaleController;
use App\Http\Controllers\StripeWebhookController;
use Illuminate\Support\Facades\Route;

// Root goes to the panel. The panel (/admin) handles the rest:
// - no users        -> setup / registration ("create account")
// - not signed in   -> login
// - signed in       -> dashboard
// Customer portal: /portal (see routes/portal.php)
Route::redirect('/', '/admin');

Route::get('/locale/{locale}', LocaleController::class)
    ->name('locale.switch');

Route::post('/stripe/webhook', StripeWebhookController::class)
    ->name('stripe.webhook');

Route::post('/hooks/endpoints/{token}', EndpointWebhookController::class)
    ->middleware('throttle:60,1')
    ->name('hooks.endpoints');
