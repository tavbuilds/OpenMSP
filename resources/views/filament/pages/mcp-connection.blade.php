<x-filament-panels::page>
    <div class="omsp-stack">
        <p class="omsp-prose">
            {{ __('Connect Grok and other agents here. No extra software to install — this URL is the MCP server.') }}
        </p>

        <x-filament::section :heading="__('Server URL')">
            <x-copy-field :value="$this->getUrlForAgents()" />
        </x-filament::section>

        <x-filament::section
            :heading="__('Authentication')"
            :description="__('Grok uses OAuth (values below). Cursor and scripts can still use a Bearer token from API tokens.')"
        >
            <x-filament::link :href="\App\Filament\Pages\ApiTokens::getUrl()" size="sm">
                {{ __('Open API tokens') }}
            </x-filament::link>
        </x-filament::section>

        @include('filament.pages.partials.oauth-fields')

        <x-filament::section :heading="__('Grok')">
            <ol class="omsp-steps">
                <li>{{ __('Open grok.com/connectors → New Connector → Custom.') }}</li>
                <li>{{ __('Paste the server URL.') }}</li>
                <li>{{ __('When Grok asks for OAuth, copy the values from the card above. Leave the client secret empty and pick PKCE.') }}</li>
                <li>{{ __('Save & Connect, sign in to OpenMSP, click Allow.') }}</li>
            </ol>
        </x-filament::section>

        <x-filament::section
            :heading="__('Available tools')"
            :description="__('The same permissions as the Agent API apply (viewer read-only, sales can write, admin/manager can delete).')"
        >
            <ul class="omsp-tiles">
                @foreach ($this->tools() as $tool)
                    <li class="omsp-tile">
                        <div class="omsp-tile-title">{{ $tool['name'] }}</div>
                        <p class="omsp-tile-text">{{ $tool['description'] }}</p>
                    </li>
                @endforeach
            </ul>
        </x-filament::section>
    </div>
</x-filament-panels::page>
