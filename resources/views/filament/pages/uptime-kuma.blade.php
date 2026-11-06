<x-filament-panels::page>
    <div class="omsp-stack">
        <p class="omsp-prose">
            {{ __('Save the Uptime Kuma address here. The API key is for certificate days. The login is what lets OpenMSP create the HTTPS monitor and add the webhook URL of each endpoint.') }}
        </p>

        <form wire:submit="save">
            {{ $this->form }}
        </form>
    </div>
</x-filament-panels::page>
