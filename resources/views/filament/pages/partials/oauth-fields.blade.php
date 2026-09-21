<div class="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-900">
    <div class="text-sm font-medium text-gray-950 dark:text-white">{{ __('Grok OAuth (custom connector)') }}</div>
    <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
        {{ __('Grok asks for OAuth, not a Bearer token. Fill the form with these values, leave the client secret empty, then sign in and Allow.') }}
    </p>

    @php
        $fields = [
            ['label' => __('Client ID'), 'value' => \App\OAuth\McpOAuth::PUBLIC_CLIENT_ID],
            ['label' => __('Client secret'), 'value' => '', 'empty' => true],
            ['label' => __('Authorization endpoint'), 'value' => \App\OAuth\McpOAuth::authorizationEndpoint()],
            ['label' => __('Token endpoint'), 'value' => \App\OAuth\McpOAuth::tokenEndpoint()],
            ['label' => __('Scopes'), 'value' => \App\OAuth\McpOAuth::SCOPE],
            ['label' => __('Token authentication method'), 'value' => __('none (PKCE only, recommended)')],
        ];
    @endphp

    <dl class="mt-3 space-y-3">
        @foreach ($fields as $i => $field)
            <div x-data="{ copied: false }">
                <dt class="text-xs font-medium uppercase tracking-wide text-gray-500">{{ $field['label'] }}</dt>
                <dd class="mt-1 flex flex-col gap-2 sm:flex-row sm:items-center">
                    <code class="block min-w-0 flex-1 break-all rounded-lg bg-gray-50 px-3 py-2 text-sm dark:bg-gray-800" x-ref="val">{{ ($field['empty'] ?? false) ? __('(leave empty)') : $field['value'] }}</code>
                    @unless ($field['empty'] ?? false)
                        <button
                            type="button"
                            class="inline-flex items-center justify-center rounded-lg bg-primary-600 px-3 py-2 text-sm font-medium text-white hover:bg-primary-500"
                            x-on:click="navigator.clipboard.writeText($refs.val.textContent.trim()); copied = true; setTimeout(() => copied = false, 1500)"
                        >
                            <span x-show="!copied">{{ __('Copy') }}</span>
                            <span x-cloak x-show="copied">{{ __('Copied') }}</span>
                        </button>
                    @endunless
                </dd>
            </div>
        @endforeach
    </dl>
</div>
