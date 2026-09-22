<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#0b1220">
    <title>{{ __('Authorization error') }} — {{ $platformName }}</title>
    @include('oauth.styles')
</head>
<body>
    <main class="card">
        <h1>{{ __('Authorization error') }}</h1>
        @if (filled($description))
            <p>{{ $description }}</p>
        @endif
        <p class="error-code">
            <span class="who-label">{{ __('Error code') }}</span>
            <code>{{ $error }}</code>
        </p>
    </main>
</body>
</html>
