<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('Allow access') }} — {{ $platformName }}</title>
    <style>
        :root { color-scheme: light dark; }
        body { font-family: ui-sans-serif, system-ui, sans-serif; margin: 0; min-height: 100vh; display: flex; align-items: center; justify-content: center; background: #0b1220; color: #e5e7eb; padding: 1.25rem; }
        .card { width: 100%; max-width: 28rem; background: #111827; border: 1px solid #1f2937; border-radius: 1rem; padding: 1.5rem; }
        h1 { font-size: 1.15rem; margin: 0 0 .5rem; }
        p { color: #9ca3af; font-size: .95rem; line-height: 1.45; }
        .who { margin: 1rem 0; padding: .75rem 1rem; border-radius: .75rem; background: #0b1220; font-size: .9rem; }
        .row { display: flex; gap: .75rem; flex-wrap: wrap; margin-top: 1.25rem; }
        button { flex: 1; min-width: 8rem; border: 0; border-radius: .75rem; padding: .75rem 1rem; font-weight: 600; cursor: pointer; }
        .allow { background: #2563eb; color: white; }
        .deny { background: transparent; color: #e5e7eb; border: 1px solid #374151; }
        code { font-size: .8rem; word-break: break-all; }
    </style>
</head>
<body>
    <div class="card">
        <h1>{{ __('Allow Grok to use :name?', ['name' => $platformName]) }}</h1>
        <p>{{ __('This lets the connector read and change records with the same rights as your user account.') }}</p>
        <div class="who">
            {{ __('Signed in as') }} <strong>{{ $user->name }}</strong><br>
            <code>{{ $user->email }}</code>
        </div>
        <form method="post" action="{{ route('oauth.authorize.approve') }}">
            @csrf
            <input type="hidden" name="client_id" value="{{ $clientId }}">
            <input type="hidden" name="redirect_uri" value="{{ $redirectUri }}">
            <input type="hidden" name="state" value="{{ $state }}">
            <input type="hidden" name="scope" value="{{ $scope }}">
            <input type="hidden" name="code_challenge" value="{{ $codeChallenge }}">
            <input type="hidden" name="code_challenge_method" value="{{ $codeChallengeMethod }}">
            <input type="hidden" name="resource" value="{{ $resource }}">
            <input type="hidden" name="response_type" value="code">
            <div class="row">
                <button class="deny" type="submit" name="decision" value="deny">{{ __('Deny') }}</button>
                <button class="allow" type="submit" name="decision" value="allow">{{ __('Allow') }}</button>
            </div>
        </form>
    </div>
</body>
</html>
