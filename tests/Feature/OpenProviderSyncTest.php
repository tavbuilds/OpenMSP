<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Domain;
use App\Models\Product;
use App\Models\User;
use App\Registrars\OpenProvider\OpenProviderClient;
use App\Registrars\OpenProvider\OpenProviderSync;
use App\Support\PlatformSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class OpenProviderSyncTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        PlatformSettings::set(PlatformSettings::OPENPROVIDER_USERNAME, 'reseller');
        PlatformSettings::set(PlatformSettings::OPENPROVIDER_PASSWORD, 'secret');
    }

    /**
     * Openprovider's envelope, one entry per sync run: Http::fake() stubs
     * accumulate, so a second call cannot replace the first — a sequence can.
     *
     * @param  list<list<array<string, mixed>>>  $domainRuns
     * @param  list<list<array<string, mixed>>>  $tldRuns
     */
    private function fake(array $domainRuns, array $tldRuns = [[]]): void
    {
        $sequence = function (array $runs) {
            $sequence = Http::sequence();
            foreach ($runs as $rows) {
                $sequence->push(['code' => 0, 'data' => ['results' => $rows, 'total' => count($rows)]]);
            }

            return $sequence->whenEmpty(Http::response(['code' => 0, 'data' => ['results' => [], 'total' => 0]]));
        };

        Http::fake([
            'https://api.openprovider.eu/v1beta/auth/login' => Http::response([
                'code' => 0,
                'data' => ['token' => 'tok', 'reseller_id' => 1],
            ]),
            'https://api.openprovider.eu/v1beta/domains*' => $sequence($domainRuns),
            'https://api.openprovider.eu/v1beta/tlds*' => $sequence($tldRuns),
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function domainRow(array $overrides = []): array
    {
        return array_merge([
            'id' => 4711,
            'domain' => ['name' => 'klantnaam', 'extension' => 'nl'],
            'expiration_date' => '2027-03-01 00:00:00',
            'renewal_date' => '2027-03-01 00:00:00',
            'autorenew' => 'on',
            'status' => 'ACT',
            'owner_company_name' => 'Some Owner BV',
        ], $overrides);
    }

    /**
     * @return array<string, mixed>
     */
    private function tldRow(float $reseller = 6.5, float $product = 11.0): array
    {
        return [
            'name' => 'nl',
            'prices' => [
                'renew_price' => [
                    'reseller' => ['currency' => 'EUR', 'price' => $reseller],
                    'product' => ['currency' => 'EUR', 'price' => $product],
                ],
            ],
        ];
    }

    public function test_sync_imports_domains_with_expiry_and_auto_renew(): void
    {
        $this->fake([[$this->domainRow()]]);

        $report = app(OpenProviderSync::class)->run();

        $domain = Domain::query()->firstOrFail();
        $this->assertSame('klantnaam.nl', $domain->name);
        $this->assertSame('nl', $domain->extension);
        $this->assertSame('2027-03-01', $domain->expires_at?->toDateString());
        $this->assertTrue($domain->auto_renew);
        $this->assertSame('ACT', $domain->status);
        $this->assertSame(1, $report->domainsCreated);
        // Nobody assigned it yet, and the sync did not guess.
        $this->assertNull($domain->company_id);
        $this->assertSame(1, $report->domainsUnassigned);
    }

    /**
     * The whole point of assigning by hand: a nightly sync must not undo it.
     */
    public function test_sync_keeps_the_customer_link_notes_and_visibility(): void
    {
        $company = Company::create(['name' => 'Klant BV', 'country' => 'NL']);
        $this->fake([
            [$this->domainRow()],
            [$this->domainRow(['autorenew' => 'off'])],
        ]);
        app(OpenProviderSync::class)->run();

        $domain = Domain::query()->firstOrFail();
        $domain->forceFill([
            'company_id' => $company->id,
            'notes' => 'Moved here from the old registrar.',
            'notes_visible_to_customer' => true,
            'notify_7' => false,
        ])->save();

        app(OpenProviderSync::class)->run();

        $domain->refresh();
        $this->assertSame($company->id, $domain->company_id);
        $this->assertSame('Moved here from the old registrar.', $domain->notes);
        $this->assertTrue($domain->notes_visible_to_customer);
        $this->assertFalse($domain->notify_7);
        // Registrar-owned fields do follow.
        $this->assertFalse($domain->auto_renew);
        $this->assertSame(1, Domain::query()->count());
    }

    /** A domain typed in before the first sync is adopted, not duplicated. */
    public function test_a_manually_added_domain_is_adopted_by_the_sync(): void
    {
        $company = Company::create(['name' => 'Klant BV', 'country' => 'NL']);
        Domain::query()->create([
            'company_id' => $company->id,
            'name' => 'KlantNaam.NL',
        ]);

        $this->fake([[$this->domainRow()]]);
        app(OpenProviderSync::class)->run();

        $this->assertSame(1, Domain::query()->count());
        $domain = Domain::query()->firstOrFail();
        $this->assertSame($company->id, $domain->company_id);
        $this->assertSame('4711', (string) $domain->source_id);
    }

    /**
     * Without this a renewed domain would stay silent forever, on the strength
     * of the mail it sent last year.
     */
    public function test_a_renewal_starts_a_new_reminder_cycle(): void
    {
        $this->fake([
            [$this->domainRow()],
            [$this->domainRow(['expiration_date' => '2028-03-01 00:00:00'])],
        ]);
        app(OpenProviderSync::class)->run();

        $domain = Domain::query()->firstOrFail();
        $domain->forceFill(['sent_offsets' => [30, 14, 7], 'expired_notified_at' => now()])->save();

        app(OpenProviderSync::class)->run();

        $domain->refresh();
        $this->assertSame([], $domain->sent_offsets);
        $this->assertNull($domain->expired_notified_at);
    }

    public function test_extension_prices_land_in_the_catalog_and_link_to_domains(): void
    {
        $this->fake([[$this->domainRow()]], [[$this->tldRow(6.5, 11.0)]]);

        app(OpenProviderSync::class)->run();

        $product = Product::query()->where('source', OpenProviderClient::SOURCE)->firstOrFail();
        $this->assertSame('.nl domain', $product->name);
        $this->assertEquals(6.5, (float) $product->default_cost_price);
        $this->assertEquals(11.0, (float) $product->default_sale_price);

        $domain = Domain::query()->firstOrFail();
        $this->assertSame($product->id, $domain->product_id);
        $this->assertEquals(6.5, $domain->costPrice());
    }

    /** Our sale price is ours; only the purchase price follows the registrar. */
    public function test_a_price_refresh_keeps_our_own_sale_price(): void
    {
        $this->fake(
            [[$this->domainRow()], [$this->domainRow()]],
            [[$this->tldRow(6.5, 11.0)], [$this->tldRow(7.25, 12.0)]],
        );
        app(OpenProviderSync::class)->run();

        $product = Product::query()->where('source', OpenProviderClient::SOURCE)->firstOrFail();
        $product->forceFill(['default_sale_price' => 19.95])->save();

        app(OpenProviderSync::class)->run();

        $product->refresh();
        $this->assertEquals(7.25, (float) $product->default_cost_price);
        $this->assertEquals(19.95, (float) $product->default_sale_price);
    }

    public function test_a_login_failure_names_the_ip_whitelist(): void
    {
        Http::fake([
            'https://api.openprovider.eu/v1beta/auth/login' => Http::response(
                ['code' => 196, 'desc' => 'Authorization failed'],
                401,
            ),
        ]);

        try {
            app(OpenProviderClient::class)->token();
            $this->fail('Expected a RuntimeException.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('Authorization failed', $e->getMessage());
            $this->assertStringContainsString('whitelisted', $e->getMessage());
        }
    }

    public function test_admin_openprovider_page_renders(): void
    {
        PlatformSettings::markOnboardingComplete();

        $this->actingAs(User::factory()->admin()->create())
            ->get('/admin/openprovider')
            ->assertOk()
            ->assertSee('Account → Security', false);
    }
}
