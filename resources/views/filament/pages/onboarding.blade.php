<x-filament-panels::page>
    <div class="omsp-stack">
        <p class="omsp-prose">
            {{ __('First time here? Walk through the steps. Everything can be changed or reset later under :settings and :tokens.', [
                'settings' => __('Settings'),
                'tokens' => __('API tokens'),
            ]) }}
        </p>

        @if (filled($this->plainTextToken))
            <x-filament::section
                :heading="__('API token — copy it now')"
                :description="__('This token is shown only once. Store it somewhere safe before you leave this page.')"
                icon="heroicon-o-key"
                icon-color="primary"
            >
                <x-copy-field :value="$this->plainTextToken" />

                <x-slot name="footer">
                    <x-filament::button tag="a" href="{{ url('/admin') }}">
                        {{ __('Go to the dashboard') }}
                    </x-filament::button>
                </x-slot>
            </x-filament::section>
        @else
            <form wire:submit="complete" class="omsp-stack">
                {{ $this->form }}

                <div class="omsp-actions">
                    <x-filament::button type="submit">
                        {{ __('Finish setup') }}
                    </x-filament::button>
                    <x-filament::button color="gray" wire:click="skip" type="button">
                        {{ __('Skip, set this later') }}
                    </x-filament::button>
                </div>
            </form>
        @endif
    </div>
</x-filament-panels::page>
