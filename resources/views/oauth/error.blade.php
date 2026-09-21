<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('Authorization error') }} — {{ $platformName }}</title>
    <style>
        body { font-family: ui-sans-serif, system-ui, sans-serif; margin: 0; min-height: 100vh; display: flex; align-items: center; justify-content: center; background: #0b1220; color: #e5e7eb; padding: 1.25rem; }
        .card { width: 100%; max-width: 28rem; background: #111827; border: 1px solid #1f2937; border-radius: 1rem; padding: 1.5rem; }
        h1 { font-size: 1.15rem; margin: 0 0 .5rem; }
        p { color: #9ca3af; }
        code { color: #fca5a5; }
    </style>
</head>
<body>
    <div class="card">
        <h1>{{ __('Authorization error') }}</h1>
        <p><code>{{ $error }}</code></p>
        <p>{{ $description }}</p>
    </div>
</body>
</html>
