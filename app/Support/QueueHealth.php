<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Is anything actually working the queue?
 *
 * Every notification in this application is queued, so a stopped worker means
 * no sign-in links and no reminders — while the mail settings test, which
 * sends inside the request, still reports success. That combination is very
 * hard to diagnose from the outside, so the settings page says it out loud.
 *
 * Only the database driver can be inspected from here. On any other driver
 * these report "nothing to say" rather than guessing.
 */
class QueueHealth
{
    /** A job older than this with nobody touching it means no worker. */
    private const STALE_AFTER_MINUTES = 5;

    public static function inspectable(): bool
    {
        return config('queue.default') === 'database' && Schema::hasTable('jobs');
    }

    public static function pending(): int
    {
        return self::inspectable() ? self::count('jobs') : 0;
    }

    public static function failed(): int
    {
        return Schema::hasTable('failed_jobs') ? self::count('failed_jobs') : 0;
    }

    /** When the oldest waiting job was queued, if anything is waiting. */
    public static function oldestPendingAt(): ?CarbonImmutable
    {
        if (! self::inspectable()) {
            return null;
        }

        try {
            $timestamp = DB::table('jobs')->min('created_at');
        } catch (Throwable) {
            return null;
        }

        return $timestamp === null ? null : CarbonImmutable::createFromTimestamp((int) $timestamp);
    }

    /**
     * Work is waiting and has been for long enough that a running worker
     * would have picked it up. A busy queue is not a stalled one, so this
     * looks at the age of the oldest job rather than the size of the pile.
     */
    public static function looksStalled(): bool
    {
        $oldest = self::oldestPendingAt();

        return $oldest !== null && $oldest->lt(now()->subMinutes(self::STALE_AFTER_MINUTES));
    }

    private static function count(string $table): int
    {
        try {
            return (int) DB::table($table)->count();
        } catch (Throwable) {
            return 0;
        }
    }
}
