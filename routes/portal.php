<?php

use App\Http\Controllers\Portal\AutoCollectController;
use App\Http\Controllers\Portal\DashboardController;
use App\Http\Controllers\Portal\InvoiceController;
use App\Http\Controllers\Portal\LoginController;
use App\Http\Middleware\EnsurePortalAuthenticated;
use Illuminate\Support\Facades\Route;

Route::prefix('portal')->name('portal.')->group(function () {
    Route::get('/login', [LoginController::class, 'show'])->name('login');
    Route::post('/login', [LoginController::class, 'requestLink'])
        ->middleware('throttle:10,1')
        ->name('login.request');
    Route::get('/magic/{contact}', [LoginController::class, 'magic'])
        ->middleware('signed')
        ->name('magic');

    Route::middleware(EnsurePortalAuthenticated::class)->group(function () {
        Route::get('/', DashboardController::class)->name('dashboard');
        Route::post('/logout', [LoginController::class, 'logout'])->name('logout');

        Route::get('/services/{contract}/auto-collect', AutoCollectController::class)->name('auto-collect');
        Route::get('/services/{contract}/auto-collect/success', [AutoCollectController::class, 'success'])->name('auto-collect.success');

        Route::get('/invoices', [InvoiceController::class, 'index'])->name('invoices');
        Route::get('/invoices/{invoice}/download', [InvoiceController::class, 'download'])->name('invoices.download');
    });
});
