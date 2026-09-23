<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Contract;
use App\Models\Domain;
use App\Support\DemoData;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DemoDataTest extends TestCase
{
    use RefreshDatabase;

    public function test_seed_and_purge_only_touch_demo_rows(): void
    {
        $real = Company::create(['name' => 'Echte Klant', 'country' => 'NL', 'is_demo' => false]);

        DemoData::seed();
        $this->assertTrue(DemoData::exists());
        $this->assertGreaterThan(0, Company::query()->where('is_demo', true)->count());
        $this->assertGreaterThan(0, Contract::query()->where('stripe_payment_status', 'past_due')->count());
        // One imported domain is deliberately left without a customer, so the
        // "assign these" step is visible in the demo.
        $this->assertGreaterThan(0, Domain::query()->where('is_demo', true)->whereNotNull('company_id')->count());
        $this->assertSame(1, Domain::query()->where('is_demo', true)->whereNull('company_id')->count());

        DemoData::purge();

        $this->assertFalse(DemoData::exists());
        $this->assertSame(0, Company::query()->where('is_demo', true)->count());
        $this->assertSame(0, Domain::query()->count());
        $this->assertTrue(Company::query()->whereKey($real->id)->exists());
        $this->assertSame('Echte Klant', $real->fresh()->name);
    }
}
