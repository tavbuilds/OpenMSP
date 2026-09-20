<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\Concerns\AuthorizesAgentApi;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\PersonalAccessTokenResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

/**
 * Own-token self-service for Sanctum personal access tokens.
 * Agents/operators no longer need tinker for routine token create/list/revoke.
 */
class TokenController extends Controller
{
    use AuthorizesAgentApi;

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorizeRead($request);

        $tokens = $request->user()
            ->tokens()
            ->orderByDesc('created_at')
            ->get();

        return PersonalAccessTokenResource::collection($tokens);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorizeRead($request);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'abilities' => ['nullable', 'array'],
            'abilities.*' => ['string', 'max:255'],
        ]);

        $abilities = $data['abilities'] ?? ['*'];
        if ($abilities === []) {
            $abilities = ['*'];
        }

        $newToken = $request->user()->createToken($data['name'], $abilities);
        $accessToken = $newToken->accessToken;

        return response()->json([
            'data' => [
                'id' => $accessToken->id,
                'name' => $accessToken->name,
                'abilities' => $accessToken->abilities,
                'last_used_at' => $accessToken->last_used_at?->toIso8601String(),
                'created_at' => $accessToken->created_at?->toIso8601String(),
                'plain_text_token' => $newToken->plainTextToken,
            ],
        ], 201);
    }

    public function destroy(Request $request, int $token): Response
    {
        $this->authorizeRead($request);

        $model = $request->user()->tokens()->whereKey($token)->firstOrFail();
        $model->delete();

        return response()->noContent();
    }
}
