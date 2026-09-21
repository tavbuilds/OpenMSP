<?php

namespace App\Http\Controllers\OAuth;

use App\Http\Controllers\Controller;
use App\OAuth\McpOAuth;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class MetadataController extends Controller
{
    public function authorizationServer(): JsonResponse
    {
        return $this->cors(response()->json(McpOAuth::authorizationServerMetadata()));
    }

    public function protectedResource(): JsonResponse
    {
        return $this->cors(response()->json(McpOAuth::protectedResourceMetadata()));
    }

    public function options(): Response
    {
        return $this->cors(response()->noContent());
    }

    private function cors(JsonResponse|Response $response): JsonResponse|Response
    {
        return $response
            ->header('Access-Control-Allow-Origin', '*')
            ->header('Access-Control-Allow-Methods', 'GET, POST, OPTIONS')
            ->header('Access-Control-Allow-Headers', 'Authorization, Content-Type, Accept, MCP-Protocol-Version');
    }
}
