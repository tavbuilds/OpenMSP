<x-filament-panels::page>
    <div class="omsp-stack">
        <p class="omsp-prose">
            {{ __('Pull your registered domains out of Openprovider: expiry date, auto-renew and the purchase price per extension. Which customer a domain belongs to is decided in OpenMSP — a sync never changes it.') }}
        </p>

        <ol class="omsp-steps">
            <li>{{ __('In Openprovider: whitelist this server’s IP address under Account → Security.') }}</li>
            <li>{{ __('Enter your control-panel username and password below, then Save.') }}</li>
            <li>{{ __('Test connection, then Sync now.') }}</li>
            <li>{{ __('Open Domains, filter on “Not assigned to a customer” and assign them in bulk. Later syncs keep those links.') }}</li>
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
                            <dt>{{ __('Domains created') }}</dt>
                            <dd>{{ $report['domains_created'] ?? 0 }}</dd>
                        </div>
                        <div>
                            <dt>{{ __('Domains updated') }}</dt>
                            <dd>{{ $report['domains_updated'] ?? 0 }}</dd>
                        </div>
                        <div>
                            <dt>{{ __('Without a customer') }}</dt>
                            <dd>{{ $report['domains_unassigned'] ?? 0 }}</dd>
                        </div>
                        <div>
                            <dt>{{ __('Extension prices updated') }}</dt>
                            <dd>{{ $report['tld_prices_updated'] ?? 0 }}</dd>
                        </div>
                    </dl>
                @endif

                @if ($this->lastError())
                    <p class="omsp-error">{{ $this->lastError() }}</p>
                @endif
            </x-filament::section>
        @endif

        @if ($this->unassignedCount() > 0)
            <x-filament::section
                :heading="__('Waiting for a customer')"
                icon="heroicon-o-exclamation-triangle"
                icon-color="warning"
            >
                <p class="omsp-prose">
                    {{ __(':count domain(s) are not assigned to a customer yet. They stay out of the customer portal until they are.', ['count' => $this->unassignedCount()]) }}
                </p>

                <x-slot name="footer">
                    <x-filament::button
                        tag="a"
                        :href="\App\Filament\Resources\Domains\DomainResource::getUrl('index')"
                        color="gray"
                    >
                        {{ __('Open domains') }}
                    </x-filament::button>
                </x-slot>
            </x-filament::section>
        @endif
    </div>
</x-filament-panels::page>
