<?php

namespace Tests\Unit;

use App\Monitoring\UptimeKuma\PrometheusCertMetrics;
use Tests\TestCase;

class PrometheusCertMetricsTest extends TestCase
{
    public function test_it_reads_certificate_days_and_skips_the_rest(): void
    {
        $exposition = <<<'PROM'
# HELP monitor_cert_days_remaining days
# TYPE monitor_cert_days_remaining gauge
monitor_cert_days_remaining{monitor_name="Mail",monitor_type="http",monitor_url="https://mail.example.com",monitor_hostname="",monitor_port=""} 14
monitor_cert_days_remaining{monitor_name="Say \"hi\"",monitor_type="http",monitor_url="https://q.example",monitor_hostname="",monitor_port=""} 9
monitor_cert_days_remaining{monitor_name="Junk",monitor_type="http",monitor_url="https://junk.example",monitor_hostname="",monitor_port=""} NaN
monitor_status{monitor_name="Mail",monitor_type="http",monitor_url="https://mail.example.com",monitor_hostname="",monitor_port=""} 1
PROM;

        $samples = PrometheusCertMetrics::certificateDays($exposition);

        $this->assertCount(2, $samples);
        $this->assertSame('Mail', $samples[0]['monitor_name']);
        $this->assertSame(14, $samples[0]['days_remaining']);
        $this->assertSame('https://mail.example.com', $samples[0]['monitor_url']);
        $this->assertSame('Say "hi"', $samples[1]['monitor_name']);
        $this->assertSame(9, $samples[1]['days_remaining']);
    }
}
