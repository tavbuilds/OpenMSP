<x-filament-panels::page>
    <div class="omsp-stack">
        <p class="omsp-prose">
            {{ __('Demo data is a sample portfolio (customers, licenses, a failed collection, upcoming notice dates). Every row is flagged') }}
            <code>is_demo</code>.
            {{ __('Real records are left alone.') }}
        </p>

        <x-filament::section :heading="__('Status')">
            @if ($this->loaded())
                <x-filament::badge color="warning">
                    {{ __('Demo data is in the database') }}
                </x-filament::badge>
            @else
                <x-filament::badge color="gray">
                    {{ __('No demo data') }}
                </x-filament::badge>
            @endif

            <x-slot name="footer">
                <div class="omsp-actions">
                    {{ $this->seedAction }}
                    {{ $this->purgeAction }}
                </div>
            </x-slot>
        </x-filament::section>

        <p class="omsp-prose">
            {{ __('You can also remove it from the command line:') }}
            <code>php artisan demo:purge</code>
        </p>
    </div>
</x-filament-panels::page>
