<x-filament-panels::page>
    <form wire:submit="save">
        {{ $this->form }}

        <div class="mt-6">
            <x-filament::button type="submit">
                Save
            </x-filament::button>
        </div>
    </form>

    <div class="mt-10 space-y-3 rounded-xl border border-danger-200 p-4 dark:border-danger-800">
        <h3 class="text-base font-semibold">Reset</h3>
        <p class="text-sm text-gray-500 dark:text-gray-400">
            Revert UI overrides to environment variables, or revoke every API token.
            The last administrator cannot be deleted.
        </p>
        <div class="flex flex-col gap-2 sm:flex-row sm:flex-wrap">
            {{ $this->clearMailSecretsAction }}
            {{ $this->clearStripeAction }}
            {{ $this->revokeAllTokensAction }}
            {{ $this->restartOnboardingAction }}
        </div>
    </div>
</x-filament-panels::page>
