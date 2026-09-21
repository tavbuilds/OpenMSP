<?php

namespace App\Mcp;

use App\Models\User;
use App\Support\PlatformSettings;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Stateless MCP JSON-RPC (Streamable HTTP). Tools proxy the Agent API.
 */
final class McpProtocol
{
    public const PROTOCOL_VERSION = '2025-03-26';

    public function __construct(private readonly AgentApiGateway $gateway) {}

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>|null  Null means a JSON-RPC notification (no body).
     */
    public function handle(array $payload, User $user): ?array
    {
        $method = $payload['method'] ?? null;
        $id = $payload['id'] ?? null;
        $params = is_array($payload['params'] ?? null) ? $payload['params'] : [];

        if (! is_string($method) || $method === '') {
            return $this->error($id, -32600, 'Invalid Request');
        }

        $isNotification = ! array_key_exists('id', $payload);
        if ($isNotification) {
            return null;
        }

        try {
            $result = match ($method) {
                'initialize' => $this->initialize($params),
                'ping' => (object) [],
                'tools/list' => ['tools' => ToolCatalog::listForProtocol()],
                'tools/call' => $this->callTool($user, $params),
                'resources/list' => ['resources' => []],
                'prompts/list' => ['prompts' => []],
                default => throw new InvalidArgumentException('Method not found'),
            };
        } catch (InvalidArgumentException $e) {
            $code = $e->getMessage() === 'Method not found' ? -32601 : -32602;

            return $this->error($id, $code, $e->getMessage());
        } catch (Throwable $e) {
            report($e);

            return $this->error($id, -32603, 'Internal error');
        }

        return [
            'jsonrpc' => '2.0',
            'id' => $id,
            'result' => $result,
        ];
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    private function initialize(array $params): array
    {
        $requested = $params['protocolVersion'] ?? self::PROTOCOL_VERSION;
        $version = is_string($requested) && $requested !== '' ? $requested : self::PROTOCOL_VERSION;

        return [
            'protocolVersion' => $version,
            'capabilities' => [
                'tools' => ['listChanged' => false],
            ],
            'serverInfo' => [
                'name' => 'openmsp',
                'title' => PlatformSettings::name(),
                'version' => '1.0.0',
            ],
            'instructions' => 'OpenMSP agent tools for companies, contacts, contracts, catalog, vendors, purchase bundles, planned tasks, and dashboard metrics. Same RBAC as the Agent API.',
        ];
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array{content: list<array{type: string, text: string}>, isError?: bool}
     */
    private function callTool(User $user, array $params): array
    {
        $name = $params['name'] ?? null;
        $arguments = is_array($params['arguments'] ?? null) ? $params['arguments'] : [];

        if (! is_string($name) || $name === '') {
            throw new InvalidArgumentException('Tool name is required');
        }

        $tool = ToolCatalog::find($name);
        if ($tool === null) {
            throw new InvalidArgumentException("Unknown tool: {$name}");
        }

        $response = $this->gateway->call($user, $tool, $arguments);
        $text = json_encode($response['body'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if (! is_string($text)) {
            $text = (string) $response['body'];
        }

        $isError = $response['status'] >= 400;

        return [
            'content' => [
                [
                    'type' => 'text',
                    'text' => $isError
                        ? "HTTP {$response['status']}\n{$text}"
                        : $text,
                ],
            ],
            'isError' => $isError,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function error(mixed $id, int $code, string $message): array
    {
        return [
            'jsonrpc' => '2.0',
            'id' => $id,
            'error' => [
                'code' => $code,
                'message' => $message,
            ],
        ];
    }

    public static function httpStatusForRpc(?array $rpc): int
    {
        if ($rpc === null) {
            return Response::HTTP_ACCEPTED;
        }
        if (isset($rpc['error']['code']) && (int) $rpc['error']['code'] === -32700) {
            return Response::HTTP_BAD_REQUEST;
        }

        return Response::HTTP_OK;
    }
}
