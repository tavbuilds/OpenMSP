<?php

namespace App\Registrars\OpenProvider;

use App\Enums\BillingCycle;
use App\Enums\DomainSource;
use App\Enums\ProductType;
use App\Models\Domain;
use App\Models\Product;
use App\Models\Vendor;
use App\Registrars\DomainSyncReport;
use App\Support\PlatformSettings;
use Carbon\Carbon;
use Illuminate\Support\Str;
use Throwable;

/**
 * Pulls the domain portfolio and the price per extension out of Openprovider.
 *
 * Two rules hold every run:
 *
 *  - the customer link is ours. Openprovider knows owner handles, not this
 *    platform's companies, so `company_id` is written by hand in the admin and
 *    this sync never touches it — not on the first run, not on the hundredth.
 *  - so are the notes and the notify toggles, for the same reason.
 */
class OpenProviderSync
{
    public function __construct(private OpenProviderClient $client) {}

    public function testConnection(): string
    {
        $this->client->forgetToken();
        $this->client->token();

        return 'ok';
    }

    public function run(): DomainSyncReport
    {
        $report = new DomainSyncReport;

        $rows = $this->client->domains();
        $extensions = [];

        foreach ($rows as $row) {
            try {
                $domain = $this->upsertDomain($row, $report);
                if ($domain && filled($domain->extension)) {
                    $extensions[] = $domain->extension;
                }
            } catch (Throwable $e) {
                $report->addError('Domain '.($row['id'] ?? '?').': '.$e->getMessage());
            }
        }

        try {
            $this->syncTldPrices($extensions, $report);
        } catch (Throwable $e) {
            $report->addError('TLD prices: '.$e->getMessage());
        }

        $report->domainsUnassigned = Domain::query()->whereNull('company_id')->count();

        PlatformSettings::set(PlatformSettings::OPENPROVIDER_LAST_SYNC_AT, now()->toIso8601String());
        PlatformSettings::set(PlatformSettings::OPENPROVIDER_LAST_SYNC_REPORT, json_encode($report->toArray()));
        if ($report->errors === []) {
            PlatformSettings::forget(PlatformSettings::OPENPROVIDER_LAST_ERROR);
        } else {
            PlatformSettings::set(PlatformSettings::OPENPROVIDER_LAST_ERROR, implode("\n", array_slice($report->errors, 0, 8)));
        }

        return $report;
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function upsertDomain(array $row, DomainSyncReport $report): ?Domain
    {
        $name = $this->domainName($row);
        if ($name === null) {
            return null;
        }

        $sourceId = isset($row['id']) ? (string) $row['id'] : null;
        $expires = $this->date($row['expiration_date'] ?? null);

        $existing = null;
        if ($sourceId !== null && $sourceId !== '') {
            $existing = Domain::query()
                ->where('source', DomainSource::OpenProvider->value)
                ->where('source_id', $sourceId)
                ->first();
        }
        // A domain someone typed in before the first sync is the same domain.
        $existing ??= Domain::query()->where('name', $name)->first();

        $fields = [
            'name' => $name,
            'extension' => Str::lower((string) (data_get($row, 'domain.extension') ?: Str::after($name, '.'))),
            'expires_at' => $expires,
            'renewal_date' => $this->date($row['renewal_date'] ?? null),
            'auto_renew' => $this->autoRenew($row),
            'status' => filled($row['status'] ?? null) ? (string) $row['status'] : null,
            'source' => DomainSource::OpenProvider->value,
            'source_id' => $sourceId,
            'last_synced_at' => now(),
        ];

        if ($existing === null) {
            $created = Domain::query()->create($fields);
            $report->domainsCreated++;

            return $created;
        }

        // A renewal pushes the date out; that starts a new reminder cycle,
        // otherwise last year's mail would keep this one quiet forever.
        if ($expires !== null && $existing->expires_at?->toDateString() !== $expires) {
            $fields = [...$fields, ...$existing->freshExpiryCycle()];
        }

        $existing->fill($fields);
        $changed = $existing->isDirty();
        $existing->save();
        if ($changed) {
            $report->domainsUpdated++;
        }

        return $existing;
    }

    /**
     * One catalog product per extension, holding what the renewal costs us.
     * Domains point at it, so a price change lands on every .nl at once.
     *
     * @param  list<string>  $extensions
     */
    private function syncTldPrices(array $extensions, DomainSyncReport $report): void
    {
        $extensions = array_values(array_unique(array_filter($extensions)));
        if ($extensions === []) {
            return;
        }

        $vendor = $this->vendor();

        foreach ($this->client->tlds($extensions) as $tld) {
            $name = Str::lower(trim((string) ($tld['name'] ?? '')));
            if ($name === '') {
                continue;
            }

            $cost = $this->price($tld, 'reseller');
            $sale = $this->price($tld, 'product');
            $currency = $this->currency($tld);

            $product = Product::query()
                ->where('source', OpenProviderClient::SOURCE)
                ->where('source_id', 'tld:'.$name)
                ->first();

            $fields = [
                'vendor_id' => $vendor->id,
                'sku' => 'tld-'.$name,
                'type' => ProductType::Service,
                'billing_cycle' => BillingCycle::Yearly,
                'currency' => $currency,
                'active' => true,
                'source' => OpenProviderClient::SOURCE,
                'source_id' => 'tld:'.$name,
            ];
            if ($cost !== null) {
                $fields['default_cost_price'] = $cost;
                $fields['suggested_sale_price'] = $sale;
            }

            if ($product === null) {
                $product = Product::query()->create([
                    ...$fields,
                    // Stored in the database, so English like every other seeded name.
                    'name' => '.'.$name.' domain',
                    // The sale price is the operator's call; seed it with
                    // Openprovider's retail price and never overwrite it again.
                    'default_sale_price' => $sale ?? $cost ?? 0,
                ]);
                $report->tldPricesUpdated++;
            } else {
                $product->fill($fields);
                if ($product->isDirty('default_cost_price')) {
                    $report->tldPricesUpdated++;
                }
                $product->save();
            }

            // Only fill an empty link: a deliberate override stays.
            Domain::query()
                ->where('extension', $name)
                ->whereNull('product_id')
                ->update(['product_id' => $product->id]);
        }
    }

    private function vendor(): Vendor
    {
        $match = Vendor::query()
            ->where('source', OpenProviderClient::SOURCE)
            ->where('source_id', 'registrar')
            ->first();

        $match ??= Vendor::query()->whereRaw('lower(name) = ?', ['openprovider'])->first();

        if ($match) {
            if ($match->source_id === null) {
                $match->forceFill(['source' => OpenProviderClient::SOURCE, 'source_id' => 'registrar'])->save();
            }

            return $match;
        }

        return Vendor::query()->create([
            'name' => 'Openprovider',
            'website' => 'https://www.openprovider.com',
            'source' => OpenProviderClient::SOURCE,
            'source_id' => 'registrar',
        ]);
    }

    /**
     * The yearly renewal, which is what a domain actually costs year on year.
     * Falls back to the registration price when a TLD quotes only that.
     *
     * @param  array<string, mixed>  $tld
     */
    private function price(array $tld, string $side): ?float
    {
        foreach (['renew_price', 'create_price', 'reseller_price'] as $key) {
            $value = data_get($tld, "prices.{$key}.{$side}.price");
            if (is_numeric($value) && (float) $value > 0) {
                return round((float) $value, 2);
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $tld
     */
    private function currency(array $tld): string
    {
        foreach (['renew_price', 'create_price', 'reseller_price'] as $key) {
            $value = data_get($tld, "prices.{$key}.reseller.currency");
            if (is_string($value) && $value !== '') {
                return Str::upper($value);
            }
        }

        return 'EUR';
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function domainName(array $row): ?string
    {
        $name = trim((string) (data_get($row, 'domain.name') ?? ''));
        $extension = trim((string) (data_get($row, 'domain.extension') ?? ''));

        if ($name === '') {
            return null;
        }

        return Str::lower($extension !== '' ? $name.'.'.$extension : $name);
    }

    /**
     * Openprovider sends a string: "on", "off", or "default" (the account
     * setting). "default" is only knowable per account, so it is not a promise
     * that the domain renews.
     *
     * @param  array<string, mixed>  $row
     */
    private function autoRenew(array $row): bool
    {
        return Str::lower(trim((string) ($row['autorenew'] ?? ''))) === 'on';
    }

    private function date(mixed $value): ?string
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        try {
            return Carbon::parse($value)->toDateString();
        } catch (Throwable) {
            return null;
        }
    }
}
