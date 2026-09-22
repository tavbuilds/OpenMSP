<x-filament-panels::page>
    <div class="omsp-stack">
        <p class="omsp-prose">
            {{ __('Tokens authenticate the Agent API and the built-in MCP endpoint. The plaintext value is shown only once.') }}
            {{ __('Administrators can revoke other people’s tokens under Users.') }}
        </p>

        <x-filament::section
            :heading="__('MCP endpoint')"
            :description="__('Paste this URL in Grok (Connectors → Custom) or any MCP client. Auth is the Bearer token you create below.')"
        >
            <x-copy-field :value="$this->mcpUrl()" />

            <x-slot name="footer">
                <x-filament::link :href="\App\Filament\Pages\McpConnection::getUrl()" size="sm">
                    {{ __('MCP setup & tools') }}
                </x-filament::link>
            </x-slot>
        </x-filament::section>

        @include('filament.pages.partials.oauth-fields')

        @if (filled($this->plainTextToken))
            <x-filament::section
                :heading="__('New token — copy it now')"
                :description="__('This token is shown only once. Store it somewhere safe before you leave this page.')"
                icon="heroicon-o-key"
                icon-color="primary"
            >
                <div class="omsp-fields">
                    <x-copy-field :label="__('Token')" :value="$this->plainTextToken" />
                    <x-copy-field :label="__('MCP endpoint')" :value="$this->mcpUrl()" />
                    <x-copy-field
                        :label="__('Authorization header')"
                        :value="'Authorization: Bearer '.$this->plainTextToken"
                    />
                </div>
            </x-filament::section>
        @endif

        {{ $this->table }}
    </div>
</x-filament-panels::page>
