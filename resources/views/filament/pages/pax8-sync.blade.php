<x-filament-panels::page>
    <div class="space-y-6">
        <p class="text-sm text-gray-600 dark:text-gray-400">
            {{ __('Connect your Pax8 partner account. OpenMSP pulls companies, catalog products you pick, partner cost (inkoop) and subscriptions. Your own sale prices are not overwritten.') }}
        </p>

        <ol class="list-decimal space-y-1 pl-5 text-sm text-gray-600 dark:text-gray-400">
            <li>{{ __('In Pax8: Integrations → Credentials → Create API credential. Name it OpenMSP.') }}</li>
            <li>{{ __('Copy Client ID and Client secret (secret is shown once).') }}</li>
            <li>{{ __('Paste them below, Save, then Test connection and Sync now.') }}</li>
            <li>{{ __('Search the Pax8 catalog and import the Microsoft 365 (or other) products you sell. Nightly sync keeps their inkoopprijs current.') }}</li>
        </ol>

        <form wire:submit="save" class="space-y-4">
            {{ $this->form }}
        </form>

        @if ($this->lastSyncAt() || $this->lastReport() || $this->lastError())
            <div class="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-900">
                <div class="text-sm font-medium text-gray-950 dark:text-white">{{ __('Last sync') }}</div>
                @if ($this->lastSyncAt())
                    <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">{{ $this->lastSyncAt() }}</p>
                @endif
                @if ($report = $this->lastReport())
                    <ul class="mt-2 grid gap-1 text-sm text-gray-600 dark:text-gray-400 sm:grid-cols-2">
                        <li>{{ __('Companies created') }}: {{ $report['companies_created'] ?? 0 }}</li>
                        <li>{{ __('Companies matched') }}: {{ $report['companies_matched'] ?? 0 }}</li>
                        <li>{{ __('Products updated') }}: {{ $report['products_upserted'] ?? 0 }}</li>
                        <li>{{ __('Contracts created') }}: {{ $report['contracts_created'] ?? 0 }}</li>
                        <li>{{ __('Contracts updated') }}: {{ $report['contracts_updated'] ?? 0 }}</li>
                        <li>{{ __('Prices updated') }}: {{ $report['prices_updated'] ?? 0 }}</li>
                    </ul>
                @endif
                @if ($this->lastError())
                    <p class="mt-3 text-sm text-danger-600">{{ $this->lastError() }}</p>
                @endif
            </div>
        @endif

        <div class="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-900">
            <div class="text-sm font-medium text-gray-950 dark:text-white">{{ __('Catalog search') }}</div>
            <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                {{ __('Search Pax8 (for example “Microsoft 365 Business”) and import only the SKUs you want. Those stay on the nightly price sync.') }}
            </p>
            <form wire:submit.prevent="searchCatalog" class="mt-3 flex flex-col gap-2 sm:flex-row">
                <input
                    type="text"
                    wire:model="catalogQuery"
                    class="block w-full rounded-lg border-gray-300 text-sm dark:border-gray-700 dark:bg-gray-800"
                    placeholder="Microsoft 365 Business"
                />
                <input
                    type="text"
                    wire:model="catalogVendor"
                    class="block w-full rounded-lg border-gray-300 text-sm sm:max-w-48 dark:border-gray-700 dark:bg-gray-800"
                    placeholder="Microsoft"
                />
                <x-filament::button type="submit">
                    {{ __('Search') }}
                </x-filament::button>
            </form>
            @if ($this->catalogError)
                <p class="mt-3 text-sm text-danger-600">{{ $this->catalogError }}</p>
            @endif
            @if ($this->catalogHits !== [])
                <div class="mt-4 overflow-x-auto">
                    <table class="min-w-full text-left text-sm">
                        <thead class="text-gray-500">
                            <tr>
                                <th class="py-2 pr-3 font-medium">{{ __('Name') }}</th>
                                <th class="py-2 pr-3 font-medium">{{ __('Vendor') }}</th>
                                <th class="py-2 pr-3 font-medium">{{ __('SKU') }}</th>
                                <th class="py-2 font-medium"></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($this->catalogHits as $hit)
                                @php $id = (string) ($hit['id'] ?? ''); @endphp
                                <tr class="border-t border-gray-100 dark:border-gray-800">
                                    <td class="py-2 pr-3">{{ $hit['name'] ?? '—' }}</td>
                                    <td class="py-2 pr-3">{{ $hit['vendorName'] ?? '—' }}</td>
                                    <td class="py-2 pr-3 font-mono text-xs">{{ $hit['sku'] ?? $hit['vendorSku'] ?? '—' }}</td>
                                    <td class="py-2 text-right">
                                        @if ($id !== '' && $this->isImported($id))
                                            <span class="text-xs text-gray-500">{{ __('Imported') }}</span>
                                        @elseif ($id !== '')
                                            <x-filament::button
                                                size="sm"
                                                color="gray"
                                                wire:click="importCatalogProduct('{{ $id }}')"
                                                wire:loading.attr="disabled"
                                            >
                                                {{ __('Import') }}
                                            </x-filament::button>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>

        @if ($this->importedProducts()->isNotEmpty())
            <div class="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-900">
                <div class="text-sm font-medium text-gray-950 dark:text-white">{{ __('Imported Pax8 products') }}</div>
                <ul class="mt-2 space-y-1 text-sm text-gray-600 dark:text-gray-400">
                    @foreach ($this->importedProducts() as $product)
                        <li>
                            {{ $product->name }}
                            · {{ $product->vendor?->name }}
                            · {{ __('Cost') }} € {{ number_format((float) $product->default_cost_price, 2) }}
                            · {{ __('Sale') }} € {{ number_format((float) $product->default_sale_price, 2) }}
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif
    </div>
</x-filament-panels::page>
