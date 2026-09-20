<?php

namespace Tests\Feature;

use App\Exceptions\TooManyAuthAttemptsException;
use App\Support\AuthLockout;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

class AuthLockoutFeatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_five_failures_lock_the_same_ip_and_email(): void
    {
        $request = Request::create('/admin/login', 'POST', server: ['REMOTE_ADDR' => '203.0.113.10']);

        for ($i = 0; $i < AuthLockout::MAX_FAILURES; $i++) {
            AuthLockout::hit($request, 'owner@example.com');
        }

        $this->expectException(TooManyAuthAttemptsException::class);
        AuthLockout::assert($request, 'owner@example.com');
    }

    public function test_success_clears_the_lock(): void
    {
        $request = Request::create('/admin/login', 'POST', server: ['REMOTE_ADDR' => '203.0.113.11']);
        for ($i = 0; $i < AuthLockout::MAX_FAILURES; $i++) {
            AuthLockout::hit($request, 'owner@example.com');
        }
        AuthLockout::clear($request, 'owner@example.com');
        AuthLockout::assert($request, 'owner@example.com');
        $this->assertTrue(true);
    }
}
