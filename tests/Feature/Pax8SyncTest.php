<?php

namespace Tests\Feature;

use App\Distributors\Pax8\Pax8Client;
use App\Distributors\Pax8\Pax8Sync;
use App\Enums\BillingCycle;
use App\Enums\ContractStatus;
use App\Models\Company;
use App\Models\Contract;
use App\Models\Product;
use App\Models\User;
use App\Models\Vendor;
use App\Support\PlatformSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class Pax8SyncTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        PlatformSettings::set(PlatformSettings::PAX8_CLIENT_ID, 'cid');
        PlatformSettings::set(PlatformSettings::PAX8_CLIENT_SECRET, 'csecret');
    }

    public function test_sync_imports_company_product_cost_and_subscription(): void
    {
        Http::fake([
            'https://api.pax8.com/v1/token' => Http::response(['access_token' => 'tok', 'expires_in' => 3600]),
            'https://api.pax8.com/v1/companies*' => Http::response([
                'content' => [[
                    'id' => 'co-1',
                    'name' => 'Acme BV',
                    'address' => ['city' => 'Utrecht', 'country' => 'NL', 'postalCode' => '3511'],
                ]],
                'page' => ['size' => 200, 'totalElements' => 1, 'totalPages' => 1, 'number' => 0],
            ]),
            'https://api.pax8.com/v1/subscriptions*' => Http::response([
                'content' => [[
                    'id' => 'sub-1',
                    'companyId' => 'co-1',
                    'productId' => 'prod-1',
                    'productName' => 'Microsoft 365 Business Premium',
                    'quantity' => 8,
                    'status' => 'Active',
                    'billingTerm' => 'Monthly',
                    'startDate' => '2026-01-01',
                    'endDate' => '2027-01-01',
                ]],
                'page' => ['size' => 200, 'totalElements' => 1, 'totalPages' => 1, 'number' => 0],
            ]),
            'https://api.pax8.com/v1/products/prod-1/pricing' => Http::response([
                'content' => [[
                    'billingTerm' => 'Monthly',
                    'currencyCode' => 'EUR',
                    'rates' => [[
                        'partnerBuyRate' => 10.5,
                        'suggestedRetailPrice' => 22.0,
                    ]],
                ]],
            ]),
            'https://api.pax8.com/v1/products/prod-1' => Http::response([
                'id' => 'prod-1',
                'name' => 'Microsoft 365 Business Premium',
                'vendorName' => 'Microsoft',
                'sku' => 'CFQ7TTC0LFLX',
            ]),
        ]);

        $report = app(Pax8Sync::class)->run();

        $this->assertSame(1, $report->companiesCreated);
        $this->assertSame(1, $report->productsUpserted);
        $this->assertSame(1, $report->contractsCreated);

        $product = Product::query()->where('source_id', 'prod-1')->first();
        $this->assertNotNull($product);
        $this->assertEquals(10.5, (float) $product->default_cost_price);
        $this->assertEquals(22.0, (float) $product->default_sale_price);
        $this->assertSame('Microsoft', $product->vendor->name);
        $this->assertSame(BillingCycle::Monthly, $product->billing_cycle);

        $contract = Contract::query()->where('source_id', 'sub-1')->first();
        $this->assertNotNull($contract);
        $this->assertSame(8, $contract->quantity);
        $this->assertEquals(10.5, (float) $contract->cost_price);
        $this->assertEquals(22.0, (float) $contract->sale_price);
        $this->assertSame(ContractStatus::Active, $contract->status);
        $this->assertSame('Acme BV', $contract->company->name);
    }

    public function test_second_sync_updates_cost_but_keeps_sale_price(): void
    {
        Vendor::query()->create(['name' => 'Microsoft']);
        $company = Company::create(['name' => 'Acme BV', 'country' => 'NL', 'source' => 'pax8', 'source_id' => 'co-1']);
        $product = Product::query()->create([
            'name' => 'Microsoft 365 Business Premium',
            'vendor_id' => Vendor::query()->first()->id,
            'default_cost_price' => 9,
            'default_sale_price' => 30,
            'source' => 'pax8',
            'source_id' => 'prod-1',
        ]);
        Contract::query()->create([
            'company_id' => $company->id,
            'product_id' => $product->id,
            'vendor_id' => $product->vendor_id,
            'name' => 'M365',
            'quantity' => 8,
            'cost_price' => 9,
            'sale_price' => 30,
            'start_date' => '2026-01-01',
            'source' => 'pax8',
            'source_id' => 'sub-1',
        ]);

        Http::fake([
            'https://api.pax8.com/v1/token' => Http::response(['access_token' => 'tok', 'expires_in' => 3600]),
            'https://api.pax8.com/v1/companies*' => Http::response([
                'content' => [['id' => 'co-1', 'name' => 'Acme BV']],
                'page' => ['totalPages' => 1],
            ]),
            'https://api.pax8.com/v1/subscriptions*' => Http::response([
                'content' => [[
                    'id' => 'sub-1',
                    'companyId' => 'co-1',
                    'productId' => 'prod-1',
                    'quantity' => 12,
                    'status' => 'Active',
                    'billingTerm' => 'Monthly',
                    'startDate' => '2026-01-01',
                ]],
                'page' => ['totalPages' => 1],
            ]),
            'https://api.pax8.com/v1/products/prod-1/pricing' => Http::response([
                'content' => [[
                    'billingTerm' => 'Monthly',
                    'rates' => [['partnerBuyRate' => 11.25, 'suggestedRetailPrice' => 22]],
                ]],
            ]),
            'https://api.pax8.com/v1/products/prod-1' => Http::response([
                'id' => 'prod-1',
                'name' => 'Microsoft 365 Business Premium',
                'vendorName' => 'Microsoft',
            ]),
        ]);

        app(Pax8Sync::class)->run();

        $product->refresh();
        $contract = Contract::query()->where('source_id', 'sub-1')->first();
        $this->assertEquals(11.25, (float) $product->default_cost_price);
        $this->assertEquals(30.0, (float) $product->default_sale_price);
        $this->assertSame(12, $contract->quantity);
        $this->assertEquals(11.25, (float) $contract->cost_price);
        $this->assertEquals(30.0, (float) $contract->sale_price);
        $this->assertSame(1, Contract::query()->count());
    }

    public function test_admin_pax8_page_renders(): void
    {
        $admin = User::factory()->admin()->create();
        PlatformSettings::markOnboardingComplete();

        $this->actingAs($admin)
            ->get('/admin/pax8')
            ->assertOk()
            ->assertSee('Create API credential', false)
            ->assertSee('Catalog search', false);
    }

    public function test_token_failure_is_readable(): void
    {
        Http::fake([
            'https://api.pax8.com/v1/token' => Http::response(['error' => 'invalid_client'], 401),
        ]);

        $this->expectException(\RuntimeException::class);
        app(Pax8Client::class)->token();
    }

    public function test_sync_imports_companies_without_subscriptions(): void
    {
        Http::fake([
            'https://api.pax8.com/v1/token' => Http::response(['access_token' => 'tok', 'expires_in' => 3600]),
            'https://api.pax8.com/v1/companies*' => Http::response([
                'content' => [
                    ['id' => 'co-self', 'name' => 'Tav-IT'],
                    ['id' => 'co-2', 'name' => 'Klant B.V.'],
                    ['id' => 'co-3', 'name' => 'Klant C'],
                    ['id' => 'co-4', 'name' => 'Klant D'],
                ],
                'page' => ['totalPages' => 1],
            ]),
            'https://api.pax8.com/v1/subscriptions*' => Http::response([
                'content' => [],
                'page' => ['totalPages' => 1],
            ]),
        ]);

        $report = app(Pax8Sync::class)->run();

        $this->assertSame(4, $report->companiesCreated);
        $this->assertSame(0, $report->contractsCreated);
        $this->assertSame(4, Company::query()->count());
        $this->assertTrue(Company::query()->where('source_id', 'co-2')->exists());
    }

    public function test_catalog_import_then_nightly_refreshes_cost(): void
    {
        Http::fake(function (Request $request) {
            $url = $request->url();
            if (str_ends_with($url, '/v1/token')) {
                return Http::response(['access_token' => 'tok', 'expires_in' => 3600]);
            }
            if (str_contains($url, '/products/') && str_ends_with($url, '/pricing')) {
                return Http::response([
                    'content' => [[
                        'billingTerm' => 'Monthly',
                        'currencyCode' => 'EUR',
                        'rates' => [['partnerBuyRate' => 12.4, 'suggestedRetailPrice' => 22.8]],
                    ]],
                ]);
            }
            if (preg_match('#/products/prod-m365$#', $url)) {
                return Http::response([
                    'id' => 'prod-m365',
                    'name' => 'Microsoft 365 Business Premium',
                    'vendorName' => 'Microsoft',
                    'sku' => 'CFQ7TTC0LFLX',
                ]);
            }
            if (str_contains($url, '/products')) {
                return Http::response([
                    'content' => [[
                        'id' => 'prod-m365',
                        'name' => 'Microsoft 365 Business Premium',
                        'vendorName' => 'Microsoft',
                        'sku' => 'CFQ7TTC0LFLX',
                    ]],
                    'page' => ['totalPages' => 1],
                ]);
            }
            if (str_contains($url, '/companies')) {
                return Http::response(['content' => [], 'page' => ['totalPages' => 1]]);
            }
            if (str_contains($url, '/subscriptions')) {
                return Http::response(['content' => [], 'page' => ['totalPages' => 1]]);
            }

            return Http::response(['error' => 'unexpected '.$url], 500);
        });

        $hits = app(Pax8Client::class)->searchProducts('Microsoft 365 Business', 'Microsoft');
        $this->assertSame('prod-m365', $hits[0]['id']);

        $product = app(Pax8Sync::class)->importProduct('prod-m365');
        $this->assertSame('Microsoft 365 Business Premium', $product->name);
        $this->assertEquals(12.4, (float) $product->default_cost_price);
        $this->assertEquals(22.8, (float) $product->default_sale_price);

        $product->update(['default_cost_price' => 9, 'default_sale_price' => 30]);

        $report = app(Pax8Sync::class)->run();
        $product->refresh();

        $this->assertSame(1, $report->productsUpserted);
        $this->assertEquals(12.4, (float) $product->default_cost_price);
        $this->assertEquals(30.0, (float) $product->default_sale_price);
        $this->assertSame(0, $report->contractsCreated);
    }

    public function test_all_pax8_price_options_are_stored_and_monthly_1_year_is_list_price(): void
    {
        Http::fake(function (Request $request) {
            $url = $request->url();
            if (str_ends_with($url, '/v1/token')) {
                return Http::response(['access_token' => 'tok', 'expires_in' => 3600]);
            }
            if (str_contains($url, '/pricing')) {
                return Http::response([
                    'content' => [
                        [
                            'billingTerm' => 'Monthly',
                            'commitmentTerm' => 'Monthly',
                            'commitmentTermInMonths' => 1,
                            'unitOfMeasurement' => 'User',
                            'type' => 'Volume',
                            'currencyCode' => 'EUR',
                            'rates' => [[
                                'partnerBuyRate' => 16.3680,
                                'suggestedRetailPrice' => 18.60,
                                'startQuantityRange' => 0,
                            ]],
                        ],
                        [
                            'billingTerm' => 'Monthly',
                            'commitmentTerm' => 'Monthly',
                            'commitmentTermInMonths' => 1,
                            'unitOfMeasurement' => 'User',
                            'type' => 'Flat',
                            'currencyCode' => 'EUR',
                            'rates' => [[
                                'partnerBuyRate' => 20.1256,
                                'suggestedRetailPrice' => 22.87,
                                'startQuantityRange' => 1,
                                'endQuantityRange' => 300,
                            ]],
                        ],
                        [
                            'billingTerm' => 'Trial',
                            'commitmentTerm' => 'Monthly',
                            'commitmentTermInMonths' => 1,
                            'type' => 'Flat',
                            'rates' => [[
                                'partnerBuyRate' => 0,
                                'suggestedRetailPrice' => 0,
                            ]],
                        ],
                        [
                            'billingTerm' => 'Monthly',
                            'commitmentTerm' => '1-Year',
                            'commitmentTermInMonths' => 12,
                            'unitOfMeasurement' => 'User',
                            'type' => 'Flat',
                            'currencyCode' => 'EUR',
                            'rates' => [[
                                'partnerBuyRate' => 17.6088,
                                'suggestedRetailPrice' => 20.01,
                                'startQuantityRange' => 1,
                                'endQuantityRange' => 300,
                            ]],
                        ],
                        [
                            'billingTerm' => 'Annual',
                            'commitmentTerm' => '1-Year',
                            'commitmentTermInMonths' => 12,
                            'unitOfMeasurement' => 'User',
                            'type' => 'Volume',
                            'currencyCode' => 'EUR',
                            'rates' => [[
                                'partnerBuyRate' => 196.4160,
                                'suggestedRetailPrice' => 223.20,
                                'startQuantityRange' => 0,
                            ]],
                        ],
                        [
                            'billingTerm' => 'Annual',
                            'commitmentTerm' => '1-Year',
                            'commitmentTermInMonths' => 12,
                            'unitOfMeasurement' => 'User',
                            'type' => 'Flat',
                            'currencyCode' => 'EUR',
                            'rates' => [[
                                'partnerBuyRate' => 201.2736,
                                'suggestedRetailPrice' => 228.72,
                                'startQuantityRange' => 1,
                                'endQuantityRange' => 300,
                            ]],
                        ],
                    ],
                ]);
            }
            if (str_contains($url, '/products/')) {
                return Http::response([
                    'id' => 'prod-nce',
                    'name' => 'Microsoft 365 Business Premium [New Commerce Experience]',
                    'vendorName' => 'Microsoft',
                    'sku' => 'MST-NCE-103-C100',
                ]);
            }

            return Http::response(['error' => $url], 500);
        });

        $product = app(Pax8Sync::class)->importProduct('prod-nce');

        $this->assertSame(3, $product->priceOptions()->count());
        $this->assertEquals(17.6088, (float) $product->default_cost_price);
        $this->assertEquals(20.01, (float) $product->default_sale_price);
        $this->assertFalse($product->priceOptions->contains(fn ($o) => (float) $o->cost_price === 16.3680));
        $this->assertFalse($product->priceOptions->contains(fn ($o) => (float) $o->cost_price === 0.0));
        $this->assertFalse($product->priceOptions->contains(fn ($o) => (float) $o->cost_price === 196.4160));
        $this->assertSame(BillingCycle::Monthly, $product->billing_cycle);

        $default = $product->defaultPriceOption();
        $this->assertNotNull($default);
        $this->assertSame('1-Year', $default->commitment_term);
        $this->assertSame('Monthly', $default->billing_term);
        $this->assertTrue($default->is_default);

        $this->assertTrue($product->priceOptions->contains(
            fn ($o) => $o->billing_term === 'Annual' && (float) $o->cost_price === 201.2736
        ));
    }
}
