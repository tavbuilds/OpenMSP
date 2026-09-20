<x-filament-panels::page>
    <p class="mb-4 text-sm text-gray-500 dark:text-gray-400">
        First time here? Walk through the steps. Everything can be changed or reset later
        under <strong>Settings</strong> and <strong>API tokens</strong>.
    </p>

    @if (filled($this->plainTextToken))
        <div class="mb-6 rounded-xl border border-primary-200 bg-primary-50 p-4 dark:border-primary-800 dark:bg-primary-950">
            <div class="text-sm font-medium">API token — copy it now</div>
            <code class="mt-2 block break-all text-sm">{{ $this->plainTextToken }}</code>
            <div class="mt-3">
                <x-filament::button tag="a" href="{{ url('/admin') }}" class="w-full sm:w-auto">
                    Go to the dashboard
                </x-filament::button>
            </div>
        </div>
    @else
        <form wire:submit="complete">
            {{ $this->form }}

            <div class="mt-6 flex flex-col gap-2 sm:flex-row sm:flex-wrap">
                <x-filament::button type="submit">
                    Finish setup
                </x-filament::button>
                <x-filament::button color="gray" wire:click="skip" type="button">
                    Skip, set this later
                </x-filament::button>
            </div>
        </form>
    @endif
</x-filament-panels::page>
