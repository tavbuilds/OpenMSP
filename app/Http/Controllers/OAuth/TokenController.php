<?php

namespace App\Http\Controllers\OAuth;

use App\Http\Controllers\Controller;
use App\Models\OAuthAuthCode;
use App\Models\OAuthRefreshToken;
use App\Models\User;
use App\OAuth\McpOAuth;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Laravel\Sanctum\PersonalAccessToken;

class TokenController extends Controller
{
    public function options(): Response
    {
        return $this->cors(response()->noContent());
    }

    public function issue(Request $request): JsonResponse
    {
        $grant = (string) $request->input('grant_type');

        return match ($grant) {
            'authorization_code' => $this->authorizationCode($request),
            'refresh_token' => $this->refresh($request),
            default => $this->error('unsupported_grant_type', 'Only authorization_code and refresh_token are supported.'),
        };
    }

    private function authorizationCode(Request $request): JsonResponse
    {
        $code = (string) $request->input('code');
        $clientId = (string) $request->input('client_id');
        $redirectUri = (string) $request->input('redirect_uri');
        $verifier = (string) $request->input('code_verifier');

        $authCode = OAuthAuthCode::query()->where('id', $code)->first();
        if ($authCode === null || $authCode->consumed_at !== null || $authCode->expires_at->isPast()) {
            return $this->error('invalid_grant', 'Authorization code is invalid or expired.');
        }

        if ($authCode->client_id !== $clientId || $authCode->redirect_uri !== $redirectUri) {
            return $this->error('invalid_grant', 'client_id or redirect_uri mismatch.');
        }

        if ($verifier === '' || ! hash_equals($authCode->code_challenge, McpOAuth::s256($verifier))) {
            return $this->error('invalid_grant', 'PKCE verification failed.');
        }

        $authCode->forceFill(['consumed_at' => now()])->save();

        return $this->issueTokens($authCode->user, $clientId);
    }

    private function refresh(Request $request): JsonResponse
    {
        $plain = (string) $request->input('refresh_token');
        $clientId = (string) $request->input('client_id');
        if ($plain === '') {
            return $this->error('invalid_request', 'refresh_token is required.');
        }

        $record = OAuthRefreshToken::query()->where('token_hash', hash('sha256', $plain))->first();
        if ($record === null || $record->consumed_at !== null || $record->expires_at->isPast()) {
            return $this->error('invalid_grant', 'Refresh token is invalid or expired.');
        }

        if ($clientId !== '' && $record->client_id !== $clientId) {
            return $this->error('invalid_grant', 'client_id mismatch.');
        }

        $record->forceFill(['consumed_at' => now()])->save();

        if ($record->personal_access_token_id) {
            PersonalAccessToken::query()->whereKey($record->personal_access_token_id)->delete();
        }

        return $this->issueTokens($record->user, $record->client_id);
    }

    private function issueTokens(User $user, string $clientId): JsonResponse
    {
        $access = $user->createToken('mcp-oauth:'.$clientId, ['*'], now()->addHour());
        $refreshPlain = bin2hex(random_bytes(32));

        OAuthRefreshToken::query()->create([
            'token_hash' => hash('sha256', $refreshPlain),
            'client_id' => $clientId,
            'user_id' => $user->id,
            'personal_access_token_id' => $access->accessToken->id,
            'expires_at' => now()->addDays(30),
        ]);

        return $this->cors(response()->json([
            'access_token' => $access->plainTextToken,
            'token_type' => 'Bearer',
            'expires_in' => 3600,
            'refresh_token' => $refreshPlain,
            'scope' => McpOAuth::SCOPE,
        ]));
    }

    private function error(string $error, string $description): JsonResponse
    {
        return $this->cors(response()->json([
            'error' => $error,
            'error_description' => $description,
        ], 400));
    }

    private function cors(JsonResponse|Response $response): JsonResponse|Response
    {
        return $response
            ->header('Access-Control-Allow-Origin', '*')
            ->header('Access-Control-Allow-Methods', 'GET, POST, OPTIONS')
            ->header('Access-Control-Allow-Headers', 'Authorization, Content-Type, Accept');
    }
}
