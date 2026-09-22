<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#0b1220">
    <title>{{ __('Allow access') }} — {{ $platformName }}</title>
    @include('oauth.styles')
</head>
<body>
    <main class="card">
        <h1>{{ __('Allow Grok to use :name?', ['name' => $platformName]) }}</h1>
        <p>{{ __('This lets the connector read and change records with the same rights as your user account.') }}</p>

        <div class="who">
            <span class="who-label">{{ __('Signed in as') }}</span>
            <strong>{{ $user->name }}</strong>
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
    </main>
</body>
</html>
