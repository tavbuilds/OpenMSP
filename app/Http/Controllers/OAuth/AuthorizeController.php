<?php

namespace App\Http\Controllers\OAuth;

use App\Http\Controllers\Controller;
use App\Models\OAuthAuthCode;
use App\OAuth\McpOAuth;
use App\Support\PlatformSettings;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class AuthorizeController extends Controller
{
    public function show(Request $request): View|RedirectResponse
    {
        $error = $this->validateAuthorizeRequest($request);
        if ($error !== null) {
            return $this->reject($request, $error[0], $error[1]);
        }

        return view('oauth.authorize', [
            'platformName' => PlatformSettings::name(),
            'user' => $request->user(),
            'clientId' => (string) $request->query('client_id'),
            'redirectUri' => (string) $request->query('redirect_uri'),
            'state' => (string) $request->query('state', ''),
            'scope' => (string) $request->query('scope', McpOAuth::SCOPE),
            'codeChallenge' => (string) $request->query('code_challenge'),
            'codeChallengeMethod' => (string) $request->query('code_challenge_method', 'S256'),
            'resource' => (string) $request->query('resource', ''),
        ]);
    }

    public function approve(Request $request): RedirectResponse|View
    {
        $error = $this->validateAuthorizeRequest($request);
        if ($error !== null) {
            return $this->reject($request, $error[0], $error[1]);
        }

        if ($request->input('decision') !== 'allow') {
            return $this->redirectWith($request, [
                'error' => 'access_denied',
                'error_description' => 'The user denied the request.',
            ]);
        }

        $code = Str::lower(Str::random(48));
        OAuthAuthCode::query()->create([
            'id' => $code,
            'client_id' => (string) $request->input('client_id'),
            'user_id' => $request->user()->id,
            'redirect_uri' => (string) $request->input('redirect_uri'),
            'code_challenge' => (string) $request->input('code_challenge'),
            'code_challenge_method' => (string) $request->input('code_challenge_method', 'S256'),
            'scopes' => (string) $request->input('scope', McpOAuth::SCOPE) ?: McpOAuth::SCOPE,
            'expires_at' => now()->addMinutes(5),
        ]);

        return $this->redirectWith($request, ['code' => $code]);
    }

    /**
     * @return array{0: string, 1: string}|null
     */
    private function validateAuthorizeRequest(Request $request): ?array
    {
        $clientId = (string) $request->input('client_id', $request->query('client_id'));
        $redirectUri = (string) $request->input('redirect_uri', $request->query('redirect_uri'));
        $responseType = (string) $request->input('response_type', $request->query('response_type'));
        $challenge = (string) $request->input('code_challenge', $request->query('code_challenge'));
        $method = (string) $request->input('code_challenge_method', $request->query('code_challenge_method', 'S256'));

        if ($clientId === '' || $redirectUri === '') {
            return ['invalid_request', 'client_id and redirect_uri are required.'];
        }

        if (! McpOAuth::clientAllowsRedirect($clientId, $redirectUri)) {
            return ['invalid_request', 'Unknown client or redirect_uri is not allowed.'];
        }

        if ($responseType !== 'code') {
            return ['unsupported_response_type', 'Only response_type=code is supported.'];
        }

        if ($method !== 'S256' || strlen($challenge) < 43) {
            return ['invalid_request', 'PKCE S256 code_challenge is required.'];
        }

        return null;
    }

    /**
     * @param  array<string, string>  $params
     */
    private function redirectWith(Request $request, array $params): RedirectResponse
    {
        $redirectUri = (string) $request->input('redirect_uri', $request->query('redirect_uri'));
        $state = (string) $request->input('state', $request->query('state', ''));
        if ($state !== '') {
            $params['state'] = $state;
        }

        $separator = str_contains($redirectUri, '?') ? '&' : '?';

        return redirect()->away($redirectUri.$separator.http_build_query($params));
    }

    private function reject(Request $request, string $error, string $description): View|RedirectResponse
    {
        $redirectUri = (string) $request->input('redirect_uri', $request->query('redirect_uri'));
        $clientId = (string) $request->input('client_id', $request->query('client_id'));

        if ($redirectUri !== '' && McpOAuth::clientAllowsRedirect($clientId, $redirectUri)) {
            return $this->redirectWith($request, [
                'error' => $error,
                'error_description' => $description,
            ]);
        }

        return view('oauth.error', [
            'platformName' => PlatformSettings::name(),
            'error' => $error,
            'description' => $description,
        ]);
    }
}
