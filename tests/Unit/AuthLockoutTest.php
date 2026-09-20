<?php

namespace Tests\Unit;

use App\Support\AuthLockout;
use PHPUnit\Framework\TestCase;

class AuthLockoutTest extends TestCase
{
    public function test_fifth_failure_in_window_locks(): void
    {
        $now = 1_000_000;
        $state = ['failures' => 0, 'window_started' => $now, 'locked_until' => 0];
        for ($i = 0; $i < 4; $i++) {
            $state = AuthLockout::nextState($state, $now + $i);
            $this->assertFalse(AuthLockout::isLocked($state, $now + $i));
        }
        $state = AuthLockout::nextState($state, $now + 4);
        $this->assertTrue(AuthLockout::isLocked($state, $now + 4));
        $this->assertSame($now + 4 + AuthLockout::LOCK_SECONDS, $state['locked_until']);
        $this->assertSame(5, $state['failures']);
    }

    public function test_window_expiry_without_lock_resets_counter(): void
    {
        $now = 1_000_000;
        $state = AuthLockout::nextState([
            'failures' => 3,
            'window_started' => $now,
            'locked_until' => 0,
        ], $now + AuthLockout::WINDOW_SECONDS + 1);

        $this->assertSame(1, $state['failures']);
        $this->assertFalse(AuthLockout::isLocked($state, $now + AuthLockout::WINDOW_SECONDS + 1));
    }

    public function test_failures_after_lock_still_increment_but_keep_until(): void
    {
        $now = 1_000_000;
        $lockedUntil = $now + 100;
        $state = AuthLockout::nextState([
            'failures' => 5,
            'window_started' => $now,
            'locked_until' => $lockedUntil,
        ], $now + 10);

        $this->assertSame(6, $state['failures']);
        $this->assertSame($lockedUntil, $state['locked_until']);
        $this->assertTrue(AuthLockout::isLocked($state, $now + 10));
    }
}
