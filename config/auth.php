<?php

use App\Models\Contact;
use App\Models\User;

return [

    'defaults' => [
        'guard' => env('AUTH_GUARD', 'web'),
        'passwords' => env('AUTH_PASSWORD_BROKER', 'users'),
    ],

    'guards' => [
        'web' => [
            'driver' => 'session',
            'provider' => 'users',
        ],
        // Customer portal (/portal) — Contact persons, magic-link only.
        'portal' => [
            'driver' => 'session',
            'provider' => 'contacts',
        ],
    ],

    'providers' => [
        'users' => [
            // Resolves a user from a passkey assertion, and falls back to the
            // password when the request is an ordinary sign-in. Both methods
            // stay open: a lost device must not lock an operator out.
            'driver' => 'eloquent-webauthn',
            'model' => env('AUTH_MODEL', User::class),
            'password_fallback' => true,
        ],
        'contacts' => [
            'driver' => 'eloquent',
            'model' => Contact::class,
        ],
    ],

    'passwords' => [
        'users' => [
            'provider' => 'users',
            'table' => env('AUTH_PASSWORD_RESET_TOKEN_TABLE', 'password_reset_tokens'),
            'expire' => 60,
            'throttle' => 60,
        ],
    ],

    'password_timeout' => env('AUTH_PASSWORD_TIMEOUT', 10800),

];
