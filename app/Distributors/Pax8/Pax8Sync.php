<?php

namespace App\Distributors\Pax8;

use App\Distributors\SyncReport;
use App\Enums\BillingCycle;
use App\Enums\ContractStatus;
use App\Enums\ProductType;
use App\Models\Company;
use App\Models\Contract;
use App\Models\Product;
use App\Models\ProductPriceOption;
use App\Models\Vendor;
use App\Support\PlatformSettings;
use Carbon\Carbon;
use Throwable;

class Pax8Sync
{
    public function __construct(private Pax8Client $client) {}

    /** @var array<string, true> */
    private array $seenCompanies = [];

    /** @var array<string, true> */
    private array $seenProducts = [];

    public function testConnection(): string
    {
        $this->client->token();

        return 'ok';
    }

    public function run(): SyncReport
    {
        $report = new SyncReport;
        $this->seenCompanies = [];
        $this->seenProducts = [];

        $companies = $this->indexById($this->client->companies());
        foreach ($companies as $row) {
            try {
                if (strtolower((string) ($row['status'] ?? 'active')) === 'deleted') {
                    continue;
                }
                $this->upsertCompany($row, $report);
            } catch (Throwable $e) {
                $report->addError('Company '.($row['id'] ?? '').': '.$e->getMessage());
            }
        }

        $productCache = [];
        foreach (Product::query()->where('source', Pax8Client::SOURCE)->whereNotNull('source_id')->get() as $existing) {
            try {
                $this->upsertProduct((string) $existing->source_id, [], $productCache, $report);
            } catch (Throwable $e) {
                $report->addError('Product '.$existing->source_id.': '.$e->getMessage());
            }
        }

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

    public function importProduct(string $productId): Product
    {
        $report = new SyncReport;
        $cache = [];

        return $this->upsertProduct($productId, [], $cache, $report);
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
            if ($id === '' || ! isset($this->seenCompanies[$id])) {
                $report->companiesMatched++;
                if ($id !== '') {
                    $this->seenCompanies[$id] = true;
                }
            }

            return $match;
        }

        $created = Company::query()->create($fields);
        if ($id !== '') {
            $this->seenCompanies[$id] = true;
        }
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

        $parsed = $productId !== '' ? $this->parsePriceOptions($productId) : [];
        $pricing = $this->preferredPricing($parsed);

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
            $suggestedWas = $match->suggested_sale_price;
            $followsSuggested = $suggestedWas !== null && abs($saleWas - (float) $suggestedWas) < 0.02;
            unset($fields['name']);
            $match->fill($fields);
            if (($saleWas <= 0 || $followsSuggested) && $pricing['sale'] !== null) {
                $match->default_sale_price = $pricing['sale'];
            }
            $dirtyCost = $match->isDirty('default_cost_price');
            $match->save();
            $this->persistPriceOptions($match, $parsed);
            if ($productId === '' || ! isset($this->seenProducts[$productId])) {
                $report->productsUpserted++;
                if ($dirtyCost) {
                    $report->pricesUpdated++;
                }
                if ($productId !== '') {
                    $this->seenProducts[$productId] = true;
                }
            }
            $cache[$productId] = $match;

            return $match;
        }

        $fields['default_sale_price'] = $pricing['sale'] ?? $pricing['cost'];
        $created = Product::query()->create($fields);
        $this->persistPriceOptions($created, $parsed);
        $report->productsUpserted++;
        if ($productId !== '') {
            $this->seenProducts[$productId] = true;
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
     * @return list<array<string, mixed>>
     */
    private function parsePriceOptions(string $productId): array
    {
        try {
            $rows = $this->client->productPricing($productId);
        } catch (Throwable) {
            return [];
        }

        $out = [];
        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }
            $rates = is_array($row['rates'] ?? null) ? $row['rates'] : [$row];
            $billingTerm = (string) ($row['billingTerm'] ?? 'Monthly');
            $commitmentMonths = $this->commitmentMonths($row);
            $commitmentTerm = $this->commitmentLabel($row, $commitmentMonths);
            foreach ($rates as $rate) {
                if (! is_array($rate)) {
                    continue;
                }
                $out[] = [
                    'billing_term' => $billingTerm,
                    'commitment_term' => $commitmentTerm,
                    'commitment_months' => $commitmentMonths,
                    'billing_cycle' => $this->mapBillingCycle($billingTerm),
                    'pricing_type' => $row['type'] ?? null,
                    'unit_of_measure' => $row['unitOfMeasurement'] ?? $rate['unitOfMeasurement'] ?? null,
                    'charge_type' => $rate['chargeType'] ?? null,
                    'min_qty' => (int) ($rate['startQuantityRange'] ?? $rate['startQuantity'] ?? 0),
                    'max_qty' => isset($rate['endQuantityRange'])
                        ? (int) $rate['endQuantityRange']
                        : (isset($rate['endQuantity']) ? (int) $rate['endQuantity'] : null),
                    'cost_price' => (float) ($rate['partnerBuyRate'] ?? $rate['cost'] ?? 0),
                    'sale_price' => (float) ($rate['suggestedRetailPrice'] ?? $rate['price'] ?? 0),
                    'currency' => strtoupper((string) ($row['currencyCode'] ?? $rate['currencyCode'] ?? 'EUR')),
                ];
            }
        }

        return $this->filterPartnerRates($out);
    }

    /**
     * Keep the rates Pax8 assigns to this partner: skip trials/zero, prefer Flat
     * over Volume/Tiered/Mark-Up, and for volume only the qty=1 band.
     *
     * @param  list<array<string, mixed>>  $options
     * @return list<array<string, mixed>>
     */
    private function filterPartnerRates(array $options): array
    {
        $options = array_values(array_filter($options, function (array $option): bool {
            $term = strtolower((string) $option['billing_term']);
            if (str_contains($term, 'trial') || str_contains($term, 'activation')) {
                return false;
            }

            return (float) $option['cost_price'] > 0 || (float) $option['sale_price'] > 0;
        }));

        $options = array_values(array_filter($options, function (array $option): bool {
            $type = strtolower((string) ($option['pricing_type'] ?? ''));
            if (! in_array($type, ['volume', 'tiered'], true)) {
                return true;
            }
            $max = $option['max_qty'];

            return (int) $option['min_qty'] <= 1 && ($max === null || (int) $max >= 1);
        }));

        $flatKeys = [];
        foreach ($options as $option) {
            if (strcasecmp((string) ($option['pricing_type'] ?? ''), 'Flat') === 0) {
                $flatKeys[$this->rateGroupKey($option)] = true;
            }
        }
        if ($flatKeys !== []) {
            $options = array_values(array_filter($options, function (array $option) use ($flatKeys): bool {
                $key = $this->rateGroupKey($option);
                if (! isset($flatKeys[$key])) {
                    return true;
                }

                return strcasecmp((string) ($option['pricing_type'] ?? ''), 'Flat') === 0;
            }));
        }

        $grouped = [];
        foreach ($options as $option) {
            $grouped[$this->rateGroupKey($option).'|'.strtolower((string) ($option['pricing_type'] ?? 'flat'))][] = $option;
        }

        $deduped = [];
        foreach ($grouped as $rows) {
            usort($rows, function (array $a, array $b): int {
                $aBound = $a['max_qty'] !== null ? 1 : 0;
                $bBound = $b['max_qty'] !== null ? 1 : 0;
                if ($aBound !== $bBound) {
                    return $bBound <=> $aBound;
                }

                return ((float) $b['cost_price']) <=> ((float) $a['cost_price']);
            });
            $deduped[] = $rows[0];
        }

        return array_values($deduped);
    }

    /**
     * @param  array<string, mixed>  $option
     */
    private function rateGroupKey(array $option): string
    {
        return strtolower($option['commitment_term'].'|'.$option['billing_term'].'|'.$option['currency']);
    }

    /**
     * @param  list<array<string, mixed>>  $options
     * @return array{cost: float, sale: ?float, cycle: BillingCycle, currency: string}
     */
    private function preferredPricing(array $options): array
    {
        if ($options === []) {
            return ['cost' => 0.0, 'sale' => null, 'cycle' => BillingCycle::Monthly, 'currency' => 'EUR'];
        }

        $best = $this->preferredOption($options);
        if ($best === null) {
            return ['cost' => 0.0, 'sale' => null, 'cycle' => BillingCycle::Monthly, 'currency' => 'EUR'];
        }

        $cycle = $best['billing_cycle'] instanceof BillingCycle ? $best['billing_cycle'] : BillingCycle::Monthly;

        return [
            'cost' => (float) $best['cost_price'],
            'sale' => $best['sale_price'] > 0 ? (float) $best['sale_price'] : null,
            'cycle' => $cycle,
            'currency' => (string) $best['currency'],
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $options
     */
    private function persistPriceOptions(Product $product, array $options): void
    {
        if (! $product->exists) {
            return;
        }

        $product->priceOptions()->delete();
        $preferred = $this->preferredOption($options);

        foreach ($options as $option) {
            $cycle = $option['billing_cycle'] instanceof BillingCycle
                ? $option['billing_cycle']
                : BillingCycle::Monthly;
            ProductPriceOption::query()->create([
                'product_id' => $product->id,
                'billing_term' => $option['billing_term'],
                'commitment_term' => $option['commitment_term'],
                'commitment_months' => $option['commitment_months'],
                'billing_cycle' => $cycle->value,
                'pricing_type' => $option['pricing_type'] ?? null,
                'unit_of_measure' => $option['unit_of_measure'],
                'charge_type' => $option['charge_type'],
                'min_qty' => $option['min_qty'],
                'max_qty' => $option['max_qty'],
                'cost_price' => $option['cost_price'],
                'sale_price' => $option['sale_price'],
                'currency' => $option['currency'],
                'is_default' => $preferred !== null
                    && $option['billing_term'] === $preferred['billing_term']
                    && $option['commitment_months'] === $preferred['commitment_months']
                    && $option['min_qty'] === $preferred['min_qty']
                    && $option['currency'] === $preferred['currency'],
            ]);
        }
    }

    /**
     * @param  list<array<string, mixed>>  $options
     * @return array<string, mixed>|null
     */
    private function preferredOption(array $options): ?array
    {
        if ($options === []) {
            return null;
        }

        usort($options, function (array $a, array $b): int {
            $score = fn (array $o): int => ($o['currency'] === 'EUR' ? 100 : 0)
                + (($o['billing_cycle'] ?? null) === BillingCycle::Monthly ? 50 : 0)
                + (($o['commitment_months'] ?? 0) === 12 ? 20 : 0)
                + (($o['min_qty'] ?? 1) <= 1 ? 5 : 0);

            return $score($b) <=> $score($a);
        });

        return $options[0];
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function commitmentMonths(array $row): int
    {
        if (isset($row['commitmentTermInMonths']) && is_numeric($row['commitmentTermInMonths'])) {
            return max(1, (int) $row['commitmentTermInMonths']);
        }

        $term = strtolower((string) ($row['commitmentTerm'] ?? $row['commitment'] ?? ''));
        if ($term === '') {
            $term = strtolower((string) ($row['billingTerm'] ?? ''));
        }

        return match (true) {
            str_contains($term, '36') || str_contains($term, '3-year') || str_contains($term, '3 year') => 36,
            str_contains($term, '24') || str_contains($term, '2-year') || str_contains($term, '2 year') => 24,
            str_contains($term, 'year') || str_contains($term, 'annual') => 12,
            default => 1,
        };
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function commitmentLabel(array $row, int $months): string
    {
        $raw = trim((string) ($row['commitmentTerm'] ?? ''));
        if ($raw !== '') {
            return $raw;
        }

        return match ($months) {
            36 => '3-Year',
            24 => '2-Year',
            12 => '1-Year',
            default => 'Monthly',
        };
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
