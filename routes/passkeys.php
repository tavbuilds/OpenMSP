<?php

use App\Http\Controllers\Auth\PasskeyLoginController;
use App\Http\Controllers\Auth\PasskeyRegistrationController;
use Illuminate\Support\Facades\Route;

/*
 * The WebAuthn ceremonies for the admin panel.
 *
 * These are plain routes rather than Livewire calls because the browser API
 * is asynchronous and binary: the page runs the ceremony and posts the
 * result here. They carry the panel's session middleware — the challenge
 * lives in the session — and sign-in is throttled like the password form.
 */
Route::prefix('admin/passkeys')->name('passkeys.')->group(function (): void {
    Route::post('login/options', [PasskeyLoginController::class, 'options'])->name('login.options');
    Route::post('login', [PasskeyLoginController::class, 'login'])->name('login');

    Route::middleware(['auth'])->group(function (): void {
        Route::post('register/options', [PasskeyRegistrationController::class, 'options'])->name('register.options');
        Route::post('register', [PasskeyRegistrationController::class, 'store'])->name('register');
    });
});
