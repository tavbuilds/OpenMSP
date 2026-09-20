<?php

namespace App\Exceptions;

use Illuminate\Validation\ValidationException;

final class TooManyAuthAttemptsException extends ValidationException
{
    public static function make(): self
    {
        return static::withMessages([
            'data.email' => __('Too many attempts. Try again in a few minutes.'),
        ]);
    }
}
