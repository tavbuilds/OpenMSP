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
            // Passkeys do not go through the guard's credential check: the
            // package verifies the assertion and signs the user in itself.
            // Both ways in stay open — a lost device must not lock an
            // operator out — but only one of them is a credential check.
            'driver' => 'eloquent',
            'model' => env('AUTH_MODEL', User::class),
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
