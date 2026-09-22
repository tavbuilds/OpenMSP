<x-filament-panels::page>
    <div class="omsp-stack">
        <form wire:submit="save" class="omsp-stack">
            {{ $this->form }}

            <div class="omsp-actions">
                <x-filament::button type="submit">
                    {{ __('Save') }}
                </x-filament::button>
            </div>
        </form>

        <x-filament::section
            :heading="__('Reset')"
            :description="__('Revert UI overrides to environment variables, or revoke every API token. The last administrator cannot be deleted.')"
            icon="heroicon-o-exclamation-triangle"
            icon-color="danger"
        >
            <div class="omsp-actions">
                {{ $this->clearMailSecretsAction }}
                {{ $this->clearStripeAction }}
                {{ $this->revokeAllTokensAction }}
                {{ $this->restartOnboardingAction }}
            </div>
        </x-filament::section>
    </div>
</x-filament-panels::page>
