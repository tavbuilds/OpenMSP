<?php

namespace App\Http\Controllers;

use App\Mcp\McpProtocol;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use JsonException;

class McpController extends Controller
{
    public function __invoke(Request $request, McpProtocol $protocol): JsonResponse|Response
    {
        if ($request->isMethod('OPTIONS')) {
            return $this->cors(response()->noContent());
        }

        if ($request->isMethod('DELETE')) {
            return $this->cors(response()->noContent());
        }

        if ($request->isMethod('GET')) {
            // Stateless server: no long-lived SSE session. Clients should POST JSON-RPC.
            return $this->cors(response()->json([
                'jsonrpc' => '2.0',
                'error' => [
                    'code' => -32000,
                    'message' => 'OpenMSP MCP is stateless Streamable HTTP. POST JSON-RPC to this URL.',
                ],
                'id' => null,
            ], Response::HTTP_METHOD_NOT_ALLOWED));
        }

        $raw = (string) $request->getContent();
        if (trim($raw) === '') {
            return $this->rpc([
                'jsonrpc' => '2.0',
                'id' => null,
                'error' => ['code' => -32600, 'message' => 'Invalid Request'],
            ], $request);
        }

        try {
            $payload = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return $this->rpc([
                'jsonrpc' => '2.0',
                'id' => null,
                'error' => ['code' => -32700, 'message' => 'Parse error'],
            ], $request);
        }

        if (! is_array($payload) || array_is_list($payload)) {
            // Batch not required; Grok/Cursor send single messages.
            if (is_array($payload) && array_is_list($payload)) {
                return $this->rpc([
                    'jsonrpc' => '2.0',
                    'id' => null,
                    'error' => ['code' => -32600, 'message' => 'Batch requests are not supported'],
                ], $request);
            }

            return $this->rpc([
                'jsonrpc' => '2.0',
                'id' => null,
                'error' => ['code' => -32600, 'message' => 'Invalid Request'],
            ], $request);
        }

        $user = $request->user();
        $rpc = $protocol->handle($payload, $user);

        if ($rpc === null) {
            return $this->cors(response()->noContent(Response::HTTP_ACCEPTED));
        }

        return $this->rpc($rpc, $request);
    }

    /**
     * @param  array<string, mixed>  $rpc
     */
    private function rpc(array $rpc, Request $request): JsonResponse|Response
    {
        $status = McpProtocol::httpStatusForRpc($rpc);
        $accept = $request->header('Accept', '');
        $wantsSse = str_contains($accept, 'text/event-stream')
            && ! str_contains($accept, 'application/json');

        if ($wantsSse) {
            $chunk = 'event: message'."\n".'data: '.json_encode($rpc, JSON_UNESCAPED_SLASHES)."\n\n";

            return $this->cors(response($chunk, $status, [
                'Content-Type' => 'text/event-stream',
                'Cache-Control' => 'no-cache',
                'MCP-Protocol-Version' => McpProtocol::PROTOCOL_VERSION,
            ]));
        }

        return $this->cors(response()->json($rpc, $status, [
            'MCP-Protocol-Version' => McpProtocol::PROTOCOL_VERSION,
        ]));
    }

    private function cors(Response|JsonResponse $response): Response|JsonResponse
    {
        return $response
            ->header('Access-Control-Allow-Origin', '*')
            ->header('Access-Control-Allow-Methods', 'GET, POST, DELETE, OPTIONS')
            ->header('Access-Control-Allow-Headers', 'Authorization, Content-Type, Accept, MCP-Protocol-Version, Mcp-Session-Id')
            ->header('Access-Control-Expose-Headers', 'MCP-Protocol-Version, Mcp-Session-Id');
    }
}
