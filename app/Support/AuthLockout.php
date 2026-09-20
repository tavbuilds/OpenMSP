<?php

namespace App\Support;

use App\Exceptions\TooManyAuthAttemptsException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

final class AuthLockout
{
    public const MAX_FAILURES = 5;

    public const WINDOW_SECONDS = 900;

    public const LOCK_SECONDS = 900;

    /**
     * @param  array{failures: int, window_started: int, locked_until: int}  $state
     * @return array{failures: int, window_started: int, locked_until: int}
     */
    public static function nextState(array $state, int $now): array
    {
        $lockedUntil = $state['locked_until'] ?? 0;
        $windowStarted = $state['window_started'] ?? $now;
        $failures = $state['failures'] ?? 0;

        if ($lockedUntil > $now) {
            $failures++;

            return [
                'failures' => $failures,
                'window_started' => $windowStarted,
                'locked_until' => $lockedUntil,
            ];
        }

        if (($now - $windowStarted) >= self::WINDOW_SECONDS) {
            $failures = 0;
            $windowStarted = $now;
        }

        $failures++;
        $lockedUntil = 0;
        if ($failures >= self::MAX_FAILURES) {
            $lockedUntil = $now + self::LOCK_SECONDS;
        }

        return [
            'failures' => $failures,
            'window_started' => $windowStarted,
            'locked_until' => $lockedUntil,
        ];
    }

    public static function isLocked(?array $state, int $now): bool
    {
        return is_array($state) && ($state['locked_until'] ?? 0) > $now;
    }

    public static function assert(Request $request, ?string $email): void
    {
        $now = time();
        foreach (self::keys($request, $email) as $key) {
            $state = Cache::get($key);
            if (self::isLocked(is_array($state) ? $state : null, $now)) {
                throw TooManyAuthAttemptsException::make();
            }
        }
    }

    public static function hit(Request $request, ?string $email): void
    {
        $now = time();
        foreach (self::keys($request, $email) as $key) {
            $current = Cache::get($key);
            $state = self::nextState(is_array($current) ? $current : [
                'failures' => 0,
                'window_started' => $now,
                'locked_until' => 0,
            ], $now);
            Cache::put($key, $state, self::WINDOW_SECONDS + self::LOCK_SECONDS);
        }
    }

    public static function clear(Request $request, ?string $email): void
    {
        foreach (self::keys($request, $email) as $key) {
            Cache::forget($key);
        }
    }

    public static function clientIp(Request $request): string
    {
        $forwarded = $request->header('x-forwarded-for');
        if (is_string($forwarded) && $forwarded !== '') {
            return trim(explode(',', $forwarded)[0]);
        }
        $real = $request->header('x-real-ip');
        if (is_string($real) && $real !== '') {
            return $real;
        }

        return $request->ip() ?: 'unknown';
    }

    /** @return list<string> */
    public static function keys(Request $request, ?string $email): array
    {
        $keys = ['authlock:ip:'.self::clientIp($request)];
        $normalized = strtolower(trim((string) $email));
        if ($normalized !== '') {
            $keys[] = 'authlock:email:'.$normalized;
        }

        return $keys;
    }
}
