<?php

namespace Tests\Unit;

use App\Support\EndpointPayloadParser;
use Tests\TestCase;

class EndpointPayloadParserTest extends TestCase
{
    public function test_reads_explicit_expires_at(): void
    {
        $parsed = EndpointPayloadParser::parse(['expires_at' => '2026-12-01']);
        $this->assertSame('2026-12-01', $parsed['expires_at']?->toDateString());
    }

    public function test_reads_nested_valid_to_and_monitor_url(): void
    {
        $parsed = EndpointPayloadParser::parse([
            'monitor' => ['url' => 'https://vpn.klant.nl/login', 'name' => 'VPN'],
            'tls' => ['valid_to' => '2027-03-15T00:00:00Z'],
        ]);
        $this->assertSame('2027-03-15', $parsed['expires_at']?->toDateString());
        $this->assertSame('vpn.klant.nl', $parsed['hostname']);
    }

    public function test_uptime_kuma_message_days(): void
    {
        $parsed = EndpointPayloadParser::parse([
            'msg' => '[web] SSL Certificate will expire in 14 days',
            'monitor' => ['url' => 'https://web.example.com'],
        ]);
        $this->assertSame(now()->addDays(14)->toDateString(), $parsed['expires_at']?->toDateString());
        $this->assertSame('web.example.com', $parsed['hostname']);
    }

    public function test_days_remaining_numeric(): void
    {
        $parsed = EndpointPayloadParser::parse(['days_remaining' => 7]);
        $this->assertSame(now()->addDays(7)->toDateString(), $parsed['expires_at']?->toDateString());
    }
}
