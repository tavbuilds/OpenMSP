<?php

namespace App\Http\Controllers;

use App\Models\Endpoint;
use App\Support\EndpointPayloadParser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EndpointWebhookController extends Controller
{
    public function __invoke(Request $request, string $token): JsonResponse
    {
        $endpoint = Endpoint::query()->where('webhook_token', $token)->first();
        if (! $endpoint) {
            abort(404);
        }

        $payload = $request->all();
        if ($payload === [] && is_string($request->getContent()) && $request->getContent() !== '') {
            $decoded = json_decode($request->getContent(), true);
            if (is_array($decoded)) {
                $payload = $decoded;
            }
        }

        $parsed = EndpointPayloadParser::parse(is_array($payload) ? $payload : []);
        $endpoint->applyWebhook($parsed, is_array($payload) ? $payload : []);

        return response()->json([
            'ok' => true,
            'parsed' => $parsed['expires_at'] !== null,
            'expires_at' => $endpoint->fresh()->expires_at?->toDateString(),
            'status' => $endpoint->fresh()->last_status,
        ]);
    }
}
