<x-filament-panels::page>
    <div class="space-y-6">
        <p class="text-sm text-gray-600 dark:text-gray-400">
            {{ __('Connect your Pax8 partner account. OpenMSP pulls companies, the products you actually sell, partner cost (inkoop) and suggested retail. Your own sale prices are not overwritten.') }}
        </p>

        <ol class="list-decimal space-y-1 pl-5 text-sm text-gray-600 dark:text-gray-400">
            <li>{{ __('In Pax8: Integrations → Credentials → Create API credential. Name it OpenMSP.') }}</li>
            <li>{{ __('Copy Client ID and Client secret (secret is shown once).') }}</li>
            <li>{{ __('Paste them below, Save, then Test connection and Sync now.') }}</li>
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
    </div>
</x-filament-panels::page>
