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

    /**
     * Having no demo data is not the same as being done with it.
     *
     * A fresh install has none either, and needs the page to load some.
     */
    public function test_a_fresh_install_is_still_offered_the_page(): void
    {
        $this->assertFalse(DemoData::exists());
        $this->assertFalse(DemoData::wasDismissed());
        $this->assertTrue(DemoData::stillOffered());
    }

    public function test_deleting_the_demo_data_retires_the_page(): void
    {
        DemoData::seed();
        $this->assertTrue(DemoData::stillOffered());

        DemoData::purge();

        $this->assertTrue(DemoData::wasDismissed());
        $this->assertFalse(DemoData::stillOffered());
    }

    /** Purging from the command line is the same decision. */
    public function test_purging_from_the_command_line_retires_it_too(): void
    {
        DemoData::seed();

        $this->artisan('demo:purge')->assertSuccessful();

        $this->assertFalse(DemoData::stillOffered());
    }

    /** Loading it again is the documented way back. */
    public function test_seeding_again_brings_the_page_back(): void
    {
        DemoData::seed();
        DemoData::purge();
        $this->assertFalse(DemoData::stillOffered());

        $this->artisan('demo:seed')->assertSuccessful();

        $this->assertFalse(DemoData::wasDismissed());
        $this->assertTrue(DemoData::stillOffered());
    }

    /**
     * Demo rows that turn up after a dismissal need a way out.
     *
     * Hiding the only page that removes them would strand them there.
     */
    public function test_demo_data_appearing_again_un_retires_the_page(): void
    {
        DemoData::seed();
        DemoData::purge();
        $this->assertFalse(DemoData::stillOffered());

        Company::create(['name' => 'Strooigoed BV', 'country' => 'NL', 'is_demo' => true]);

        $this->assertTrue(DemoData::stillOffered());
    }
}
