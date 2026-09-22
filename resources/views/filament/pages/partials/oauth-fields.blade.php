@php
    $fields = [
        ['label' => __('Client ID'), 'value' => \App\OAuth\McpOAuth::PUBLIC_CLIENT_ID],
        ['label' => __('Client secret'), 'placeholder' => __('(leave empty)')],
        ['label' => __('Authorization endpoint'), 'value' => \App\OAuth\McpOAuth::authorizationEndpoint()],
        ['label' => __('Token endpoint'), 'value' => \App\OAuth\McpOAuth::tokenEndpoint()],
        ['label' => __('Scopes'), 'value' => \App\OAuth\McpOAuth::SCOPE],
        ['label' => __('Token authentication method'), 'value' => __('none (PKCE only, recommended)')],
    ];
@endphp

<x-filament::section
    :heading="__('Grok OAuth (custom connector)')"
    :description="__('Grok asks for OAuth, not a Bearer token. Fill the form with these values, leave the client secret empty, then sign in and Allow.')"
>
    <div class="omsp-fields">
        @foreach ($fields as $field)
            <x-copy-field
                :label="$field['label']"
                :value="$field['value'] ?? ''"
                :placeholder="$field['placeholder'] ?? null"
            />
        @endforeach
    </div>
</x-filament::section>
