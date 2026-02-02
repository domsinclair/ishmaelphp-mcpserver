<?php

    declare(strict_types=1);

    namespace Ishmael\McpServer\Server;

    use Ishmael\McpServer\Config\Settings;
    use Ishmael\McpServer\Contracts\PromptProvider;
    use Ishmael\McpServer\Contracts\ResourceProvider;
    use Ishmael\McpServer\Protocol\StdioTransport;
    use Ishmael\McpServer\Support\CancellationRegistry;
    use Ishmael\McpServer\Support\ErrorEnvelope;
    use Ishmael\McpServer\Support\Telemetry;

    final class Server
    {
        private RequestRouter $router;

        private StdioTransport $transport;

        private ResourceProvider $resources;

        private PromptProvider $prompts;

        private Settings $settings;

        private Telemetry $telemetry;

        private CancellationRegistry $cancellations;

        public function __construct(
            RequestRouter $router,
            StdioTransport $transport,
            ResourceProvider $resources,
            PromptProvider $prompts,
            ?Settings $settings = null,
            ?Telemetry $telemetry = null,
            ?CancellationRegistry $cancellations = null
        ) {
            $this->router = $router;
            $this->transport = $transport;
            $this->resources = $resources;
            $this->prompts = $prompts;
            $this->settings = $settings ?? new Settings();
            $this->telemetry = $telemetry ?? new Telemetry($this->settings);
            $this->cancellations = $cancellations ?? new CancellationRegistry();
        }

        /**
         * Main loop: read JSON-RPC-like requests and respond.
         * Expected request shape: { "id": <string|int>, "method": "listTools|listResources|listPrompts|<tool>", "params": { ... } }
         */
        public function run(): void
        {
            while (true) {
                $start = (int)floor(microtime(true) * 1000);
                $message = $this->transport->read();

                if ($message === null) {
                    // EOF: exit loop
                    break;
                }

                if ($message === []) {
                    continue;
                }

                // If transport surfaced a standardized error envelope (e.g., parse error), forward as-is
                if (isset($message['error']) && isset($message['version']) && array_key_exists('id', $message)) {
                    $this->transport->write($message);
                    continue;
                }

                $id = $message['id'] ?? null;
                $method = (string)($message['method'] ?? '');
                $params = is_array($message['params'] ?? null) ? $message['params'] : [];

                if ($method === '') {
                    $meta = [ 'durationMs' => (int)floor(microtime(true) * 1000) - $start ];
                    $this->transport->write(ErrorEnvelope::error($id, -32600, 'Invalid Request', null, $meta));
                    continue;
                }

                // Cancellation handling
                if ($method === '$/cancelRequest') {
                    $toCancel = $params['id'] ?? null;
                    $ok = $toCancel !== null ? $this->cancellations->cancel($toCancel) : false;
                    $meta = [ 'durationMs' => (int)floor(microtime(true) * 1000) - $start ];
                    $payload = [ 'cancelled' => $ok, 'id' => $toCancel ];
                    $this->transport->write(ErrorEnvelope::success($id, $payload, $meta));
                    continue;
                }

                $this->cancellations->register($id);

                $response = null;
                switch ($method) {
                    case 'listTools':
                        $response = ['result' => [ 'tools' => $this->router->listTools() ]];
                        break;
                    case 'listResources':
                        $response = ['result' => [ 'resources' => $this->resources->listResources() ]];
                        break;
                    case 'listPrompts':
                        $response = ['result' => [ 'prompts' => $this->prompts->listPrompts() ]];
                        break;
                    case 'prompts/get':
                        $name = (string)($params['name'] ?? '');
                        $args = is_array($params['arguments'] ?? null) ? $params['arguments'] : [];
                        $prompt = $this->prompts->getPrompt($name, $args);
                        if ($prompt !== null) {
                            $response = [
                                'result' => [
                                    'description' => $prompt->getDescription(),
                                    'messages' => $prompt->getMessages()
                                ]
                            ];
                        } else {
                            $response = ['error' => [ 'code' => -32602, 'message' => "Prompt not found: {$name}" ]];
                        }
                        break;
                    case 'resources/read':
                        $uri = (string)($params['uri'] ?? '');
                        $this->transport->logError("Reading resource: $uri");
                        $content = $this->resources->readResource($uri);
                        if ($content !== null) {
                            $response = ['result' => [ 'contents' => [['uri' => $uri, 'text' => $content]] ]];
                        } else {
                            $this->transport->logError("Resource not found: $uri");
                            $response = ['error' => [ 'code' => -32602, 'message' => "Resource not found: {$uri}" ]];
                        }
                        break;
                    default:
                        $response = $this->router->dispatch($method, $params);
                }

                $duration = (int)floor(microtime(true) * 1000) - $start;
                $meta = [ 'durationMs' => $duration ];

                // Soft timeout enforcement: if tool exceeded configured timeout, return timeout error
                $timeoutMs = $this->settings->requestTimeoutMs;
                if ($timeoutMs > 0 && $duration > $timeoutMs) {
                    $this->telemetry->emit('timeout', [ 'method' => $method, 'durationMs' => $duration ]);
                    $this->transport->write(
                        ErrorEnvelope::error(
                            $id,
                            40800,
                            'Request timed out',
                            [ 'timeoutMs' => $timeoutMs, 'actualMs' => $duration ],
                            $meta
                        )
                    );
                    $this->cancellations->complete($id);
                    continue;
                }

                if (isset($response['error'])) {
                    $this->transport->write(
                        ErrorEnvelope::error(
                            $id,
                            (int)$response['error']['code'],
                            (string)$response['error']['message'],
                            $response['error']['details'] ?? null,
                            $meta
                        )
                    );
                } else {
                    $this->transport->write(ErrorEnvelope::success($id, $response['result'] ?? [], $meta));
                }
                $this->cancellations->complete($id);
            }
        }
    }
