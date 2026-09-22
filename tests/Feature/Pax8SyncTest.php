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
            ->assertSee('Create API credential', false);
    }

    public function test_token_failure_is_readable(): void
    {
        Http::fake([
            'https://api.pax8.com/v1/token' => Http::response(['error' => 'invalid_client'], 401),
        ]);

        $this->expectException(\RuntimeException::class);
        app(Pax8Client::class)->token();
    }
}
