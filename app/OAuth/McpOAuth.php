<?php

namespace App\OAuth;

use App\Models\OAuthClient;

final class McpOAuth
{
    public const PUBLIC_CLIENT_ID = 'openmsp';

    public const SCOPE = 'mcp';

    /** @var list<string> */
    public const PUBLIC_CLIENT_IDS = ['openmsp', 'grok'];

    public static function issuer(): string
    {
        return rtrim((string) config('app.url'), '/');
    }

    public static function resource(): string
    {
        return self::issuer().'/mcp';
    }

    public static function authorizationEndpoint(): string
    {
        return self::issuer().'/oauth/authorize';
    }

    public static function tokenEndpoint(): string
    {
        return self::issuer().'/oauth/token';
    }

    public static function registrationEndpoint(): string
    {
        return self::issuer().'/oauth/register';
    }

    public static function protectedResourceMetadataUrl(): string
    {
        return self::issuer().'/.well-known/oauth-protected-resource';
    }

    /**
     * @return array<string, mixed>
     */
    public static function authorizationServerMetadata(): array
    {
        return [
            'issuer' => self::issuer(),
            'authorization_endpoint' => self::authorizationEndpoint(),
            'token_endpoint' => self::tokenEndpoint(),
            'registration_endpoint' => self::registrationEndpoint(),
            'scopes_supported' => [self::SCOPE],
            'response_types_supported' => ['code'],
            'grant_types_supported' => ['authorization_code', 'refresh_token'],
            'code_challenge_methods_supported' => ['S256'],
            'token_endpoint_auth_methods_supported' => ['none'],
            'revocation_endpoint_auth_methods_supported' => ['none'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function protectedResourceMetadata(): array
    {
        return [
            'resource' => self::resource(),
            'authorization_servers' => [self::issuer()],
            'bearer_methods_supported' => ['header'],
            'scopes_supported' => [self::SCOPE],
        ];
    }

    public static function s256(string $verifier): string
    {
        return rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=');
    }

    public static function redirectUriAllowed(string $uri): bool
    {
        $parts = parse_url($uri);
        if (! is_array($parts) || empty($parts['scheme']) || empty($parts['host'])) {
            return false;
        }

        $scheme = strtolower((string) $parts['scheme']);
        $host = strtolower((string) $parts['host']);

        if ($scheme === 'https') {
            foreach (self::allowedHttpsHosts() as $allowed) {
                if ($host === $allowed || str_ends_with($host, '.'.$allowed)) {
                    return true;
                }
            }
        }

        if (in_array($scheme, ['http', 'https'], true) && in_array($host, ['localhost', '127.0.0.1'], true)) {
            return true;
        }

        $appHost = strtolower((string) parse_url(self::issuer(), PHP_URL_HOST));

        return $appHost !== '' && $host === $appHost && in_array($scheme, ['http', 'https'], true);
    }

    /**
     * @return list<string>
     */
    public static function allowedHttpsHosts(): array
    {
        return [
            'grok.com',
            'x.ai',
            'x.com',
            'twitter.com',
            'claude.ai',
            'claude.com',
            'anthropic.com',
            'chatgpt.com',
            'openai.com',
            'cursor.com',
            'cursor.sh',
        ];
    }

    public static function isPublicClientId(string $clientId): bool
    {
        return in_array($clientId, self::PUBLIC_CLIENT_IDS, true);
    }

    public static function clientAllowsRedirect(string $clientId, string $redirectUri): bool
    {
        if (! self::redirectUriAllowed($redirectUri)) {
            return false;
        }

        if (self::isPublicClientId($clientId)) {
            return true;
        }

        $client = OAuthClient::query()->where('client_id', $clientId)->first();
        if ($client === null) {
            return false;
        }

        $uris = $client->redirect_uris ?? [];

        return in_array($redirectUri, $uris, true);
    }
}
