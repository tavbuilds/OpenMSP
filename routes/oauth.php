<?php

use App\Http\Controllers\OAuth\AuthorizeController;
use App\Http\Controllers\OAuth\MetadataController;
use App\Http\Controllers\OAuth\RegisterController;
use App\Http\Controllers\OAuth\TokenController;
use Illuminate\Support\Facades\Route;

Route::get('/.well-known/oauth-authorization-server', [MetadataController::class, 'authorizationServer']);
Route::get('/.well-known/oauth-authorization-server/{path}', [MetadataController::class, 'authorizationServer'])
    ->where('path', '.*');
Route::get('/.well-known/oauth-protected-resource', [MetadataController::class, 'protectedResource']);
Route::get('/.well-known/oauth-protected-resource/{path}', [MetadataController::class, 'protectedResource'])
    ->where('path', '.*');
Route::options('/.well-known/oauth-authorization-server', [MetadataController::class, 'options']);
Route::options('/.well-known/oauth-protected-resource', [MetadataController::class, 'options']);
Route::options('/.well-known/oauth-protected-resource/{path}', [MetadataController::class, 'options'])
    ->where('path', '.*');

Route::options('/oauth/register', [RegisterController::class, 'options']);
Route::post('/oauth/register', [RegisterController::class, 'store'])
    ->middleware('throttle:20,1');

Route::options('/oauth/token', [TokenController::class, 'options']);
Route::post('/oauth/token', [TokenController::class, 'issue'])
    ->middleware('throttle:60,1');

Route::middleware('web')->group(function (): void {
    Route::get('/oauth/authorize', [AuthorizeController::class, 'show'])
        ->middleware('auth')
        ->name('oauth.authorize');
    Route::post('/oauth/authorize', [AuthorizeController::class, 'approve'])
        ->middleware(['auth', 'throttle:30,1'])
        ->name('oauth.authorize.approve');
});
