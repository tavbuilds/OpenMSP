<x-filament-panels::page>
    <div class="space-y-6">
    <p class="text-sm text-gray-500 dark:text-gray-400">
        {{ __('Tokens authenticate the Agent API and the built-in MCP endpoint. The plaintext value is shown only once.') }}
        {{ __('Administrators can revoke other people’s tokens under Users.') }}
    </p>

    <div
        class="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-900"
        x-data="{ copied: false }"
    >
        <div class="text-sm font-medium text-gray-950 dark:text-white">{{ __('MCP endpoint') }}</div>
        <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
            {{ __('Paste this URL in Grok (Connectors → Custom) or any MCP client. Auth is the Bearer token you create below.') }}
        </p>
        <div class="mt-2 flex flex-col gap-2 sm:flex-row sm:items-center">
            <code class="block min-w-0 flex-1 break-all rounded-lg bg-gray-50 px-3 py-2 text-sm dark:bg-gray-800" x-ref="mcpUrl">{{ $this->mcpUrl() }}</code>
            <button
                type="button"
                class="inline-flex items-center justify-center rounded-lg bg-primary-600 px-3 py-2 text-sm font-medium text-white hover:bg-primary-500"
                x-on:click="navigator.clipboard.writeText($refs.mcpUrl.textContent.trim()); copied = true; setTimeout(() => copied = false, 1500)"
            >
                <span x-show="!copied">{{ __('Copy URL') }}</span>
                <span x-cloak x-show="copied">{{ __('Copied') }}</span>
            </button>
        </div>
        <a href="{{ \App\Filament\Pages\McpConnection::getUrl() }}" class="mt-3 inline-flex text-sm font-medium text-primary-600 hover:underline">
            {{ __('MCP setup & tools') }}
        </a>
    </div>

    @if (filled($this->plainTextToken))
        <div
            class="rounded-xl border border-primary-200 bg-primary-50 p-4 dark:border-primary-800 dark:bg-primary-950"
            x-data="{ copiedToken: false, copiedHeader: false }"
        >
            <div class="text-sm font-medium">{{ __('New token — copy it now') }}</div>
            <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">{{ __('This token is shown only once.') }}</p>

            <div class="mt-3 text-xs font-medium uppercase tracking-wide text-gray-500">{{ __('Token') }}</div>
            <div class="mt-1 flex flex-col gap-2 sm:flex-row sm:items-center">
                <code class="block min-w-0 flex-1 break-all text-sm" x-ref="token">{{ $this->plainTextToken }}</code>
                <button
                    type="button"
                    class="inline-flex items-center justify-center rounded-lg bg-primary-600 px-3 py-2 text-sm font-medium text-white hover:bg-primary-500"
                    x-on:click="navigator.clipboard.writeText($refs.token.textContent.trim()); copiedToken = true; setTimeout(() => copiedToken = false, 1500)"
                >
                    <span x-show="!copiedToken">{{ __('Copy token') }}</span>
                    <span x-cloak x-show="copiedToken">{{ __('Copied') }}</span>
                </button>
            </div>

            <div class="mt-4 text-xs font-medium uppercase tracking-wide text-gray-500">{{ __('MCP endpoint') }}</div>
            <code class="mt-1 block break-all text-sm">{{ $this->mcpUrl() }}</code>

            <div class="mt-4 text-xs font-medium uppercase tracking-wide text-gray-500">{{ __('Authorization header') }}</div>
            <div class="mt-1 flex flex-col gap-2 sm:flex-row sm:items-center">
                <code class="block min-w-0 flex-1 break-all text-sm" x-ref="header">Authorization: Bearer {{ $this->plainTextToken }}</code>
                <button
                    type="button"
                    class="inline-flex items-center justify-center rounded-lg border border-primary-300 px-3 py-2 text-sm font-medium text-primary-700 hover:bg-primary-100 dark:border-primary-700 dark:text-primary-200 dark:hover:bg-primary-900"
                    x-on:click="navigator.clipboard.writeText($refs.header.textContent.trim()); copiedHeader = true; setTimeout(() => copiedHeader = false, 1500)"
                >
                    <span x-show="!copiedHeader">{{ __('Copy header') }}</span>
                    <span x-cloak x-show="copiedHeader">{{ __('Copied') }}</span>
                </button>
            </div>
        </div>
    @endif

    <div class="overflow-x-auto">
        {{ $this->table }}
    </div>
    </div>
</x-filament-panels::page>
