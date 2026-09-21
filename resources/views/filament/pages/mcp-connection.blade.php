<x-filament-panels::page>
    <div class="space-y-6">
        <p class="text-sm text-gray-600 dark:text-gray-400">
            {{ __('Connect Grok and other agents here. No extra software to install — this URL is the MCP server.') }}
        </p>

        <div class="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-900">
            <div class="text-sm font-medium text-gray-950 dark:text-white">{{ __('Server URL') }}</div>
            <div
                class="mt-2 flex flex-col gap-2 sm:flex-row sm:items-center"
                x-data="{ copied: false }"
            >
                <code
                    class="block min-w-0 flex-1 break-all rounded-lg bg-gray-50 px-3 py-2 text-sm dark:bg-gray-800"
                    x-ref="url"
                >{{ $this->getUrlForAgents() }}</code>
                <button
                    type="button"
                    class="inline-flex items-center justify-center rounded-lg bg-primary-600 px-3 py-2 text-sm font-medium text-white hover:bg-primary-500"
                    x-on:click="
                        navigator.clipboard.writeText($refs.url.textContent.trim());
                        copied = true;
                        setTimeout(() => copied = false, 1500);
                    "
                >
                    <span x-show="!copied">{{ __('Copy URL') }}</span>
                    <span x-cloak x-show="copied">{{ __('Copied') }}</span>
                </button>
            </div>
        </div>

        <div class="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-900">
            <div class="text-sm font-medium text-gray-950 dark:text-white">{{ __('Authentication') }}</div>
            <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">
                {{ __('Create a token on API tokens, then paste it as a Bearer token in the agent.') }}
            </p>
            <p class="mt-2 font-mono text-xs break-all text-gray-500">
                Authorization: Bearer <token>
            </p>
            <a
                href="{{ \App\Filament\Pages\ApiTokens::getUrl() }}"
                class="mt-3 inline-flex text-sm font-medium text-primary-600 hover:underline"
            >
                {{ __('Open API tokens') }}
            </a>
        </div>

        <div class="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-900">
            <div class="text-sm font-medium text-gray-950 dark:text-white">{{ __('Grok') }}</div>
            <ol class="mt-2 list-decimal space-y-1 pl-5 text-sm text-gray-600 dark:text-gray-400">
                <li>{{ __('Open grok.com/connectors → New Connector → Custom.') }}</li>
                <li>{{ __('Paste the server URL.') }}</li>
                <li>{{ __('When asked for auth, choose Bearer and paste your API token.') }}</li>
                <li>{{ __('Enable the connector in the chat and ask Grok to list companies or show the dashboard.') }}</li>
            </ol>
        </div>

        <div class="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-900">
            <div class="text-sm font-medium text-gray-950 dark:text-white">{{ __('Available tools') }}</div>
            <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                {{ __('The same permissions as the Agent API apply (viewer read-only, sales can write, admin/manager can delete).') }}
            </p>
            <ul class="mt-3 grid gap-2 sm:grid-cols-2">
                @foreach ($this->tools() as $tool)
                    <li class="rounded-lg bg-gray-50 px-3 py-2 dark:bg-gray-800">
                        <div class="font-mono text-xs font-medium text-gray-950 dark:text-white">{{ $tool['name'] }}</div>
                        <div class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">{{ $tool['description'] }}</div>
                    </li>
                @endforeach
            </ul>
        </div>
    </div>
</x-filament-panels::page>
