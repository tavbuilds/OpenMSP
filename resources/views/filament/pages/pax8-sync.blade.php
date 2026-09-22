<x-filament-panels::page>
    <div class="omsp-stack">
        <p class="omsp-prose">
            {{ __('Connect your Pax8 partner account. OpenMSP pulls companies, catalog products you pick, partner cost price and subscriptions. Your own sale prices are not overwritten.') }}
        </p>

        <ol class="omsp-steps">
            <li>{{ __('In Pax8: Integrations → Credentials → Create API credential. Name it OpenMSP.') }}</li>
            <li>{{ __('Copy Client ID and Client secret (secret is shown once).') }}</li>
            <li>{{ __('Paste them below, Save, then Test connection and Sync now.') }}</li>
            <li>{{ __('Search the Pax8 catalog and import the Microsoft 365 (or other) products you sell. Nightly sync keeps their cost price current.') }}</li>
        </ol>

        <form wire:submit="save">
            {{ $this->form }}
        </form>

        @if ($this->lastSyncAt() || $this->lastReport() || $this->lastError())
            <x-filament::section
                :heading="__('Last sync')"
                :description="$this->lastSyncAt() ?: null"
            >
                @if ($report = $this->lastReport())
                    <dl class="omsp-summary">
                        <div>
                            <dt>{{ __('Companies created') }}</dt>
                            <dd>{{ $report['companies_created'] ?? 0 }}</dd>
                        </div>
                        <div>
                            <dt>{{ __('Companies matched') }}</dt>
                            <dd>{{ $report['companies_matched'] ?? 0 }}</dd>
                        </div>
                        <div>
                            <dt>{{ __('Products updated') }}</dt>
                            <dd>{{ $report['products_upserted'] ?? 0 }}</dd>
                        </div>
                        <div>
                            <dt>{{ __('Contracts created') }}</dt>
                            <dd>{{ $report['contracts_created'] ?? 0 }}</dd>
                        </div>
                        <div>
                            <dt>{{ __('Contracts updated') }}</dt>
                            <dd>{{ $report['contracts_updated'] ?? 0 }}</dd>
                        </div>
                        <div>
                            <dt>{{ __('Prices updated') }}</dt>
                            <dd>{{ $report['prices_updated'] ?? 0 }}</dd>
                        </div>
                    </dl>
                @endif

                @if ($this->lastError())
                    <p class="omsp-error">{{ $this->lastError() }}</p>
                @endif
            </x-filament::section>
        @endif

        <x-filament::section
            :heading="__('Catalog search')"
            :description="__('Search Pax8 (for example “Microsoft 365 Business”) and import only the SKUs you want. Those stay on the nightly price sync.')"
        >
            <form wire:submit.prevent="searchCatalog" class="omsp-search">
                <div class="omsp-search-main">
                    <label class="omsp-sr-only" for="pax8-catalog-query">{{ __('Product') }}</label>
                    <x-filament::input.wrapper>
                        <x-filament::input
                            id="pax8-catalog-query"
                            type="text"
                            wire:model="catalogQuery"
                            placeholder="Microsoft 365 Business"
                        />
                    </x-filament::input.wrapper>
                </div>

                <div class="omsp-search-vendor">
                    <label class="omsp-sr-only" for="pax8-catalog-vendor">{{ __('Vendor') }}</label>
                    <x-filament::input.wrapper>
                        <x-filament::input
                            id="pax8-catalog-vendor"
                            type="text"
                            wire:model="catalogVendor"
                            placeholder="Microsoft"
                        />
                    </x-filament::input.wrapper>
                </div>

                <x-filament::button type="submit" wire:loading.attr="disabled">
                    {{ __('Search') }}
                </x-filament::button>
            </form>

            @if ($this->catalogError)
                <p class="omsp-error">{{ $this->catalogError }}</p>
            @endif

            @if ($this->catalogHits !== [])
                <div class="omsp-table-wrap" style="margin-top: 1rem">
                    <table class="omsp-table">
                        <thead>
                            <tr>
                                <th scope="col">{{ __('Name') }}</th>
                                <th scope="col">{{ __('Vendor') }}</th>
                                <th scope="col">{{ __('SKU') }}</th>
                                <th scope="col"><span class="omsp-sr-only">{{ __('Import') }}</span></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($this->catalogHits as $hit)
                                @php $id = (string) ($hit['id'] ?? ''); @endphp
                                <tr>
                                    <td>{{ $hit['name'] ?? '—' }}</td>
                                    <td>{{ $hit['vendorName'] ?? '—' }}</td>
                                    <td class="omsp-table-mono">{{ $hit['sku'] ?? $hit['vendorSku'] ?? '—' }}</td>
                                    <td>
                                        @if ($id !== '' && $this->isImported($id))
                                            <x-filament::badge color="success">
                                                {{ __('Imported') }}
                                            </x-filament::badge>
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
        </x-filament::section>

        @if ($this->importedProducts()->isNotEmpty())
            <x-filament::section :heading="__('Imported Pax8 products')">
                <ul class="omsp-list">
                    @foreach ($this->importedProducts() as $product)
                        <li>
                            <span>{{ $product->name }}</span>
                            <span class="omsp-list-meta">
                                {{ $product->vendor?->name }}
                                · {{ __('Cost') }} {{ \App\Support\Money::format($product->default_cost_price, $product->currency) }}
                                · {{ __('Sale') }} {{ \App\Support\Money::format($product->default_sale_price, $product->currency) }}
                            </span>
                        </li>
                    @endforeach
                </ul>
            </x-filament::section>
        @endif
    </div>
</x-filament-panels::page>
