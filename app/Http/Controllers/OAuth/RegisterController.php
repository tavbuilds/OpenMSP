<?php

namespace App\Http\Controllers\OAuth;

use App\Http\Controllers\Controller;
use App\Models\OAuthClient;
use App\OAuth\McpOAuth;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Str;

class RegisterController extends Controller
{
    public function options(): Response
    {
        return $this->cors(response()->noContent());
    }

    public function store(Request $request): JsonResponse
    {
        $uris = $request->input('redirect_uris', []);
        if (! is_array($uris) || $uris === []) {
            return $this->cors(response()->json([
                'error' => 'invalid_client_metadata',
                'error_description' => 'redirect_uris is required.',
            ], 400));
        }

        $allowed = [];
        foreach ($uris as $uri) {
            if (! is_string($uri) || ! McpOAuth::redirectUriAllowed($uri)) {
                return $this->cors(response()->json([
                    'error' => 'invalid_redirect_uri',
                    'error_description' => 'Redirect URI is not allowed.',
                ], 400));
            }
            $allowed[] = $uri;
        }

        $clientId = Str::uuid()->toString();
        $name = is_string($request->input('client_name')) ? $request->input('client_name') : 'MCP client';

        OAuthClient::query()->create([
            'client_id' => $clientId,
            'name' => $name,
            'redirect_uris' => array_values(array_unique($allowed)),
            'token_endpoint_auth_method' => 'none',
        ]);

        return $this->cors(response()->json([
            'client_id' => $clientId,
            'client_id_issued_at' => time(),
            'client_name' => $name,
            'redirect_uris' => array_values(array_unique($allowed)),
            'grant_types' => ['authorization_code', 'refresh_token'],
            'response_types' => ['code'],
            'token_endpoint_auth_method' => 'none',
            'code_challenge_methods' => ['S256'],
        ], 201));
    }

    private function cors(JsonResponse|Response $response): JsonResponse|Response
    {
        return $response
            ->header('Access-Control-Allow-Origin', '*')
            ->header('Access-Control-Allow-Methods', 'GET, POST, OPTIONS')
            ->header('Access-Control-Allow-Headers', 'Authorization, Content-Type, Accept');
    }
}
