<?php

namespace App\Mcp;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use InvalidArgumentException;

/**
 * Runs Agent API controllers in-process so MCP tools share validation and RBAC.
 */
final class AgentApiGateway
{
    /**
     * @param  array<string, mixed>  $arguments
     * @return array{status: int, body: mixed}
     */
    public function call(User $user, array $tool, array $arguments): array
    {
        $args = $this->compact($arguments);
        $path = $this->expandPath($tool['path'], $args);
        $method = strtoupper((string) $tool['method']);

        $query = [];
        $body = [];
        if (in_array($method, ['GET', 'DELETE', 'HEAD'], true)) {
            $query = $args;
        } else {
            $body = $args;
        }

        $uri = '/api/v1'.$path;
        if ($query !== []) {
            $uri .= '?'.http_build_query($this->stringify($query));
        }

        $content = $body === [] ? null : json_encode($body, JSON_THROW_ON_ERROR);
        $sub = Request::create($uri, $method, [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
        ], $content);
        $sub->headers->set('Accept', 'application/json');
        $sub->headers->set('Content-Type', 'application/json');
        $sub->setUserResolver(fn () => $user);

        $guard = Auth::guard('sanctum');
        $previous = $guard->user();
        $previousRequest = app()->bound('request') ? app('request') : null;
        $guard->setUser($user);
        app()->instance('request', $sub);

        try {
            $response = app('router')->dispatch($sub);
        } finally {
            if ($previous instanceof User) {
                $guard->setUser($previous);
            } else {
                $guard->forgetUser();
            }
            if ($previousRequest instanceof Request) {
                app()->instance('request', $previousRequest);
            }
        }

        $raw = $response->getContent();
        $decoded = is_string($raw) && $raw !== '' ? json_decode($raw, true) : null;

        return [
            'status' => $response->getStatusCode(),
            'body' => $decoded ?? $raw,
        ];
    }

    /**
     * @param  array<string, mixed>  $args
     */
    private function expandPath(string $template, array &$args): string
    {
        return (string) preg_replace_callback('/\{(\w+)\}/', function (array $match) use (&$args): string {
            $key = $match[1];
            if (! array_key_exists($key, $args) || $args[$key] === null || $args[$key] === '') {
                throw new InvalidArgumentException("Missing required argument {$key}.");
            }
            $value = $args[$key];
            unset($args[$key]);

            return rawurlencode((string) $value);
        }, $template);
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function compact(array $arguments): array
    {
        $out = [];
        foreach ($arguments as $key => $value) {
            if ($value === null) {
                continue;
            }
            $out[$key] = $value;
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $query
     * @return array<string, mixed>
     */
    private function stringify(array $query): array
    {
        foreach ($query as $key => $value) {
            if (is_bool($value)) {
                $query[$key] = $value ? '1' : '0';
            }
        }

        return $query;
    }
}
