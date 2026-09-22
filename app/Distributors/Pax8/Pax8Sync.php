<?php

namespace App\Distributors\Pax8;

use App\Distributors\SyncReport;
use App\Enums\BillingCycle;
use App\Enums\ContractStatus;
use App\Enums\ProductType;
use App\Models\Company;
use App\Models\Contract;
use App\Models\Product;
use App\Models\Vendor;
use App\Support\PlatformSettings;
use Carbon\Carbon;
use Throwable;

class Pax8Sync
{
    public function __construct(private Pax8Client $client) {}

    public function testConnection(): string
    {
        $this->client->token();

        return 'ok';
    }

    public function run(): SyncReport
    {
        $report = new SyncReport;
        $companies = $this->indexById($this->client->companies());
        $productCache = [];

        foreach ($this->client->subscriptions() as $row) {
            try {
                $this->syncSubscription($row, $companies, $productCache, $report);
            } catch (Throwable $e) {
                $id = (string) ($row['id'] ?? '');
                $report->addError('Subscription '.$id.': '.$e->getMessage());
            }
        }

        PlatformSettings::set(PlatformSettings::PAX8_LAST_SYNC_AT, now()->toIso8601String());
        PlatformSettings::set(PlatformSettings::PAX8_LAST_SYNC_REPORT, json_encode($report->toArray()));
        if ($report->errors === []) {
            PlatformSettings::forget(PlatformSettings::PAX8_LAST_ERROR);
        } else {
            PlatformSettings::set(PlatformSettings::PAX8_LAST_ERROR, implode("\n", array_slice($report->errors, 0, 8)));
        }

        return $report;
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  array<string, array<string, mixed>>  $companies
     * @param  array<string, Product>  $productCache
     */
    private function syncSubscription(array $row, array $companies, array &$productCache, SyncReport $report): void
    {
        $subscriptionId = (string) ($row['id'] ?? '');
        if ($subscriptionId === '') {
            return;
        }

        $companyId = (string) ($row['companyId'] ?? data_get($row, 'company.id', ''));
        $productId = (string) ($row['productId'] ?? data_get($row, 'product.id', ''));
        $companyPayload = $companies[$companyId] ?? (is_array($row['company'] ?? null) ? $row['company'] : ['id' => $companyId, 'name' => $row['companyName'] ?? 'Pax8 company']);

        $company = $this->upsertCompany($companyPayload, $report);
        $product = $this->upsertProduct($productId, $row, $productCache, $report);

        $quantity = max(1, (int) ($row['quantity'] ?? 1));
        $status = $this->mapStatus((string) ($row['status'] ?? 'Active'));
        $cycle = $this->mapBillingCycle((string) ($row['billingTerm'] ?? $row['commitment'] ?? 'Monthly'));
        $start = $this->date($row['startDate'] ?? $row['billingStart'] ?? $row['createdDate'] ?? now()->toDateString()) ?? now()->toDateString();
        $renewal = $this->date($row['endDate'] ?? null);
        $cost = $this->subscriptionCost($row, $product);
        $name = (string) ($row['productName'] ?? data_get($row, 'product.name') ?? $product->name);

        $existing = Contract::query()
            ->where('source', Pax8Client::SOURCE)
            ->where('source_id', $subscriptionId)
            ->first();

        $payload = [
            'company_id' => $company->id,
            'product_id' => $product->id,
            'vendor_id' => $product->vendor_id,
            'name' => $name,
            'reference' => $subscriptionId,
            'type' => ProductType::Subscription,
            'quantity' => $quantity,
            'cost_price' => $cost,
            'currency' => strtoupper((string) ($row['currency'] ?? $product->currency ?? 'EUR')),
            'billing_cycle' => $cycle,
            'start_date' => $start,
            'renewal_date' => $renewal,
            'auto_renew' => ! in_array($status, [ContractStatus::Cancelled, ContractStatus::Expired], true),
            'status' => $status,
            'source' => Pax8Client::SOURCE,
            'source_id' => $subscriptionId,
        ];

        if ($existing) {
            unset($payload['name']);
            $existing->fill($payload);
            if ($existing->isDirty('cost_price')) {
                $report->pricesUpdated++;
            }
            $existing->save();
            $report->contractsUpdated++;

            return;
        }

        $sale = (float) $product->default_sale_price;
        if ($sale <= 0 && $product->suggested_sale_price) {
            $sale = (float) $product->suggested_sale_price;
        }
        $payload['sale_price'] = $sale;
        Contract::query()->create($payload);
        $report->contractsCreated++;
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function upsertCompany(array $row, SyncReport $report): Company
    {
        $id = (string) ($row['id'] ?? '');
        $name = trim((string) ($row['name'] ?? 'Pax8 company'));
        $address = is_array($row['address'] ?? null) ? $row['address'] : [];

        $match = $id !== ''
            ? Company::query()->where('source', Pax8Client::SOURCE)->where('source_id', $id)->first()
            : null;
        if ($match === null) {
            $match = Company::query()->whereRaw('lower(name) = ?', [mb_strtolower($name)])->first();
        }

        $fields = [
            'name' => $name,
            'city' => $address['city'] ?? $row['city'] ?? $match?->city,
            'postal_code' => $address['postalCode'] ?? $address['postcode'] ?? $match?->postal_code,
            'country' => $address['country'] ?? $row['country'] ?? $match?->country ?? 'NL',
            'address' => $address['street'] ?? $match?->address,
            'source' => Pax8Client::SOURCE,
            'source_id' => $id !== '' ? $id : $match?->source_id,
        ];

        if ($match) {
            $match->fill(array_filter($fields, fn ($v) => $v !== null && $v !== ''));
            $match->save();
            $report->companiesMatched++;

            return $match;
        }

        $created = Company::query()->create($fields);
        $report->companiesCreated++;

        return $created;
    }

    /**
     * @param  array<string, mixed>  $subscription
     * @param  array<string, Product>  $cache
     */
    private function upsertProduct(string $productId, array $subscription, array &$cache, SyncReport $report): Product
    {
        if ($productId !== '' && isset($cache[$productId])) {
            return $cache[$productId];
        }

        $detail = [];
        if ($productId !== '') {
            try {
                $detail = $this->client->product($productId);
            } catch (Throwable) {
                $detail = [];
            }
        }

        $name = (string) ($detail['name'] ?? $subscription['productName'] ?? data_get($subscription, 'product.name') ?? 'Pax8 product');
        $vendorName = trim((string) ($detail['vendorName'] ?? $subscription['vendorName'] ?? 'Pax8'));
        $sku = $detail['sku'] ?? $detail['vendorSku'] ?? null;
        $vendor = $this->upsertVendor($vendorName, (string) ($detail['vendorId'] ?? ''));

        $pricing = $productId !== '' ? $this->pickPricing($productId) : ['cost' => 0.0, 'sale' => null, 'cycle' => BillingCycle::Monthly, 'currency' => 'EUR'];

        $match = $productId !== ''
            ? Product::query()->where('source', Pax8Client::SOURCE)->where('source_id', $productId)->first()
            : null;

        $fields = [
            'vendor_id' => $vendor->id,
            'name' => $name,
            'sku' => is_string($sku) ? $sku : $match?->sku,
            'type' => ProductType::Subscription,
            'default_cost_price' => $pricing['cost'],
            'suggested_sale_price' => $pricing['sale'],
            'currency' => $pricing['currency'],
            'billing_cycle' => $pricing['cycle'],
            'description' => $detail['shortDescription'] ?? $match?->description,
            'active' => true,
            'source' => Pax8Client::SOURCE,
            'source_id' => $productId !== '' ? $productId : $match?->source_id,
        ];

        if ($match) {
            $saleWas = (float) $match->default_sale_price;
            unset($fields['name']);
            $match->fill($fields);
            if ($saleWas <= 0 && $pricing['sale']) {
                $match->default_sale_price = $pricing['sale'];
            }
            if ($match->isDirty('default_cost_price')) {
                $report->pricesUpdated++;
            }
            $match->save();
            $report->productsUpserted++;
            $cache[$productId] = $match;

            return $match;
        }

        $fields['default_sale_price'] = $pricing['sale'] ?? $pricing['cost'];
        $created = Product::query()->create($fields);
        $report->productsUpserted++;
        if ($productId !== '') {
            $cache[$productId] = $created;
        }

        return $created;
    }

    private function upsertVendor(string $name, string $vendorId): Vendor
    {
        $name = $name !== '' ? $name : 'Pax8';
        $match = $vendorId !== ''
            ? Vendor::query()->where('source', Pax8Client::SOURCE)->where('source_id', $vendorId)->first()
            : null;
        $match ??= Vendor::query()->whereRaw('lower(name) = ?', [mb_strtolower($name)])->first();

        if ($match) {
            if ($vendorId !== '' && $match->source_id === null) {
                $match->forceFill(['source' => Pax8Client::SOURCE, 'source_id' => $vendorId])->save();
            }

            return $match;
        }

        return Vendor::query()->create([
            'name' => $name,
            'source' => Pax8Client::SOURCE,
            'source_id' => $vendorId !== '' ? $vendorId : null,
        ]);
    }

    /**
     * @return array{cost: float, sale: ?float, cycle: BillingCycle, currency: string}
     */
    private function pickPricing(string $productId): array
    {
        try {
            $rows = $this->client->productPricing($productId);
        } catch (Throwable) {
            return ['cost' => 0.0, 'sale' => null, 'cycle' => BillingCycle::Monthly, 'currency' => 'EUR'];
        }

        $preferred = null;
        foreach (['Monthly', 'Annual', 'One-Time'] as $term) {
            foreach ($rows as $row) {
                if (strcasecmp((string) ($row['billingTerm'] ?? ''), $term) === 0) {
                    $preferred = $row;
                    break 2;
                }
            }
        }
        $preferred ??= $rows[0] ?? null;
        if (! is_array($preferred)) {
            return ['cost' => 0.0, 'sale' => null, 'cycle' => BillingCycle::Monthly, 'currency' => 'EUR'];
        }

        $rate = is_array($preferred['rates'][0] ?? null) ? $preferred['rates'][0] : $preferred;

        return [
            'cost' => (float) ($rate['partnerBuyRate'] ?? $rate['cost'] ?? 0),
            'sale' => isset($rate['suggestedRetailPrice']) ? (float) $rate['suggestedRetailPrice'] : null,
            'cycle' => $this->mapBillingCycle((string) ($preferred['billingTerm'] ?? 'Monthly')),
            'currency' => strtoupper((string) ($preferred['currencyCode'] ?? $rate['currencyCode'] ?? 'EUR')),
        ];
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function subscriptionCost(array $row, Product $product): float
    {
        $fromSub = $row['partnerBuyRate'] ?? $row['cost'] ?? null;
        if (is_numeric($fromSub)) {
            return (float) $fromSub;
        }

        return (float) $product->default_cost_price;
    }

    private function mapBillingCycle(string $term): BillingCycle
    {
        $term = strtolower($term);

        return match (true) {
            str_contains($term, 'month') => BillingCycle::Monthly,
            str_contains($term, 'quarter') => BillingCycle::Quarterly,
            str_contains($term, 'year') || str_contains($term, 'annual') => BillingCycle::Yearly,
            str_contains($term, 'one') || str_contains($term, 'trial') || str_contains($term, 'activation') => BillingCycle::Once,
            default => BillingCycle::Monthly,
        };
    }

    private function mapStatus(string $status): ContractStatus
    {
        $status = strtolower($status);

        return match (true) {
            str_contains($status, 'cancel') => ContractStatus::Cancelled,
            str_contains($status, 'expir') => ContractStatus::Expired,
            str_contains($status, 'pending') || str_contains($status, 'trial') => ContractStatus::Pending,
            default => ContractStatus::Active,
        };
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

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return array<string, array<string, mixed>>
     */
    private function indexById(array $rows): array
    {
        $out = [];
        foreach ($rows as $row) {
            $id = (string) ($row['id'] ?? '');
            if ($id !== '') {
                $out[$id] = $row;
            }
        }

        return $out;
    }
}
