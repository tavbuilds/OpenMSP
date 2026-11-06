<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\Concerns\AuthorizesAgentApi;
use App\Http\Controllers\Controller;
use App\Monitoring\UptimeKuma\UptimeKumaClient;
use App\Monitoring\UptimeKuma\UptimeKumaException;
use App\Monitoring\UptimeKuma\UptimeKumaSync;
use App\Monitoring\UptimeKuma\UptimeKumaWebhooks;
use App\Support\PlatformSettings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class UptimeKumaController extends Controller
{
    use AuthorizesAgentApi;

    public function show(Request $request): JsonResponse
    {
        $this->authorizeRead($request);

        return response()->json(['data' => $this->status()]);
    }

    public function update(Request $request): JsonResponse
    {
        $this->authorizeSettings($request);

        $request->validate([
            'base_url' => ['sometimes', 'nullable', 'string', 'max:255'],
            'api_key' => ['sometimes', 'nullable', 'string', 'max:500'],
            'username' => ['sometimes', 'nullable', 'string', 'max:255'],
            'password' => ['sometimes', 'nullable', 'string', 'max:500'],
        ]);

        if (! $request->exists('base_url') && ! $request->exists('api_key') && ! $request->exists('username') && ! $request->exists('password')) {
            throw ValidationException::withMessages([
                'base_url' => ['Provide base_url, api_key, username, password, or any combination. Send an empty string to clear a value.'],
            ]);
        }

        $savedKey = false;
        $savedPassword = false;

        if ($request->exists('base_url')) {
            $baseUrl = $this->normaliseBaseUrl($request->input('base_url'));
            if ($baseUrl === null) {
                PlatformSettings::forget(PlatformSettings::UPTIME_KUMA_BASE_URL);
            } else {
                PlatformSettings::set(PlatformSettings::UPTIME_KUMA_BASE_URL, $baseUrl);
            }
        }

        if ($request->exists('api_key')) {
            $apiKey = trim((string) $request->input('api_key'));
            if ($apiKey === '') {
                PlatformSettings::forget(PlatformSettings::UPTIME_KUMA_API_KEY);
            } else {
                PlatformSettings::set(PlatformSettings::UPTIME_KUMA_API_KEY, $apiKey);
                $savedKey = true;
            }
        }

        if ($request->exists('username')) {
            $username = trim((string) $request->input('username'));
            if ($username === '') {
                PlatformSettings::forget(PlatformSettings::UPTIME_KUMA_USERNAME);
            } else {
                PlatformSettings::set(PlatformSettings::UPTIME_KUMA_USERNAME, $username);
            }
        }

        if ($request->exists('password')) {
            $password = (string) $request->input('password');
            if (trim($password) === '') {
                PlatformSettings::forget(PlatformSettings::UPTIME_KUMA_PASSWORD);
            } else {
                PlatformSettings::set(PlatformSettings::UPTIME_KUMA_PASSWORD, $password);
                $savedPassword = true;
            }
        }

        return response()->json([
            'data' => [
                ...$this->status(),
                'api_key_saved' => $savedKey,
                'password_saved' => $savedPassword,
            ],
        ]);
    }

    public function test(Request $request, UptimeKumaClient $client): JsonResponse
    {
        $this->authorizeSettings($request);

        try {
            $result = $client->test();
        } catch (UptimeKumaException $e) {
            PlatformSettings::set(PlatformSettings::UPTIME_KUMA_LAST_ERROR, $e->getMessage());

            throw ValidationException::withMessages([
                'uptime_kuma' => [$e->getMessage()],
            ]);
        }

        PlatformSettings::forget(PlatformSettings::UPTIME_KUMA_LAST_ERROR);

        return response()->json(['data' => $result]);
    }

    public function sync(Request $request, UptimeKumaSync $sync): JsonResponse
    {
        $this->authorizeSettings($request);

        $request->validate([
            'create_missing' => ['sometimes', 'boolean'],
        ]);

        try {
            $report = $sync->run($request->boolean('create_missing'));
        } catch (UptimeKumaException $e) {
            throw ValidationException::withMessages([
                'uptime_kuma' => [$e->getMessage()],
            ]);
        }

        return response()->json(['data' => $report]);
    }

    public function pushWebhooks(Request $request, UptimeKumaWebhooks $webhooks): JsonResponse
    {
        $this->authorizeSettings($request);

        $request->validate([
            'endpoint_id' => ['sometimes', 'nullable', 'integer', 'exists:endpoints,id'],
            'endpoint_ids' => ['sometimes', 'array'],
            'endpoint_ids.*' => ['integer', 'distinct', 'exists:endpoints,id'],
            'failed_only' => ['sometimes', 'boolean'],
        ]);

        $endpointId = $request->filled('endpoint_id') ? $request->integer('endpoint_id') : null;
        $endpointIds = $endpointId === null && $request->exists('endpoint_ids')
            ? $request->input('endpoint_ids')
            : null;

        try {
            $report = $webhooks->push(
                $endpointId,
                $request->boolean('failed_only') && $endpointId === null && $endpointIds === null,
                is_array($endpointIds) ? $endpointIds : null,
            );
        } catch (UptimeKumaException $e) {
            throw ValidationException::withMessages([
                'uptime_kuma' => [$e->getMessage()],
            ]);
        }

        return response()->json(['data' => $report]);
    }

    /**
     * @return array<string, mixed>
     */
    private function status(): array
    {
        return [
            'base_url' => PlatformSettings::get(PlatformSettings::UPTIME_KUMA_BASE_URL),
            'api_key_set' => filled(PlatformSettings::get(PlatformSettings::UPTIME_KUMA_API_KEY)),
            'username' => PlatformSettings::get(PlatformSettings::UPTIME_KUMA_USERNAME),
            'password_set' => filled(PlatformSettings::get(PlatformSettings::UPTIME_KUMA_PASSWORD)),
            'last_sync_at' => PlatformSettings::get(PlatformSettings::UPTIME_KUMA_LAST_SYNC_AT),
            'last_error' => PlatformSettings::get(PlatformSettings::UPTIME_KUMA_LAST_ERROR),
            'last_sync_report' => $this->lastReport(),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function lastReport(): ?array
    {
        $raw = PlatformSettings::get(PlatformSettings::UPTIME_KUMA_LAST_SYNC_REPORT);
        if (! is_string($raw) || $raw === '') {
            return null;
        }

        $decoded = json_decode($raw, true);

        return is_array($decoded) ? $decoded : null;
    }

    private function normaliseBaseUrl(mixed $value): ?string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }

        if (str_ends_with($value, '/metrics')) {
            $value = substr($value, 0, -strlen('/metrics'));
        }

        $value = rtrim($value, '/');
        $parts = parse_url($value);
        $scheme = is_array($parts) ? ($parts['scheme'] ?? '') : '';

        if (! is_array($parts) || ! in_array($scheme, ['http', 'https'], true) || empty($parts['host'])) {
            throw ValidationException::withMessages([
                'base_url' => ['base_url must be an http(s) URL of the Uptime Kuma server, without /metrics.'],
            ]);
        }

        if (isset($parts['user']) || isset($parts['pass'])) {
            throw ValidationException::withMessages([
                'base_url' => ['Put the API key in api_key, not in the URL.'],
            ]);
        }

        return $value;
    }

    private function authorizeSettings(Request $request): void
    {
        abort_unless($request->user()?->canAdminister() ?? false, 403);
    }
}
