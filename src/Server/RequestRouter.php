<?php

    declare(strict_types=1);

    namespace Ishmael\McpServer\Server;

    use Ishmael\McpServer\Config\Settings;
    use Ishmael\McpServer\Contracts\Tool;
    use Ishmael\McpServer\Support\JsonSchemaValidator;
    use Ishmael\McpServer\Support\RateLimiter;
    use Ishmael\McpServer\Support\ResultCache;
    use Ishmael\McpServer\Support\Telemetry;

    final class RequestRouter
    {
        /** @var array<string, Tool> */

        private array $tools = [];

        private ?ProjectStateManager $stateManager = null;

        private Settings $settings;

        private RateLimiter $rateLimiter;

        private ResultCache $cache;

        private Telemetry $telemetry;



        public function __construct(?Settings $settings = null, ?RateLimiter $rateLimiter = null, ?ResultCache $cache = null, ?Telemetry $telemetry = null, ?ProjectStateManager $stateManager = null)
        {

            $this->settings = $settings ?? new Settings();

            $this->rateLimiter = $rateLimiter ?? new RateLimiter($this->settings);

            $this->cache = $cache ?? new ResultCache($this->settings);

            $this->telemetry = $telemetry ?? new Telemetry($this->settings);
            $this->stateManager = $stateManager;
        }



        /** Register a tool by its getName() identifier. */

        public function registerTool(Tool $tool): void
        {
            $name = $tool->getName();

            // If it's already registered, we might want to prefer Dedicated tools 
            // over DynamicIshTool instances.
            if (isset($this->tools[$name])) {
                if ($tool instanceof \Ishmael\McpServer\Tools\DynamicIshTool) {
                    // Don't overwrite an existing (likely dedicated) tool with a dynamic one
                    return;
                }
            }

            $this->tools[$name] = $tool;
        }



        /** Return discovery listing of tools. */

        public function listTools(): array
        {

            $items = [];

            foreach ($this->tools as $tool) {
                $items[] = [

                    'name' => $tool->getName(),

                    'description' => $tool->getDescription(),

                    'inputSchema' => $tool->getInputSchema(),

                    'outputSchema' => $tool->getOutputSchema(),

                ];
            }

            return $items;
        }



        /** Dispatch a request by method name with params. Performs rate limiting, input/output validation, caching, and telemetry. */

        public function dispatch(string $method, array $params = []): array
        {

            $t0 = (int)floor(microtime(true) * 1000);

            // Rate limiting (global + per method)

            if (($rl = $this->rateLimiter->check($method)) !== null) {
                $this->telemetry->emit('rate_limited', [ 'method' => $method, 'code' => $rl['code'] ]);

                return [ 'error' => $rl ];
            }



            if (!isset($this->tools[$method])) {
                return [

                    'error' => [

                        'code' => -32601,

                        'message' => 'Method not found: ' . $method,

                    ],

                ];
            }

            $tool = $this->tools[$method];

            // Phase 2: Optional state gating for role-specific tools
            if ($this->stateManager !== null && method_exists($tool, 'allowedStates')) {
                try {
                    /** @var list<string> $allowed */
                    $allowed = $tool->allowedStates();
                } catch (\Throwable $e) {
                    $allowed = [];
                }
                if ($allowed !== []) {
                    $current = $this->stateManager->getState();
                    if (!in_array($current, $allowed, true)) {
                        return [
                            'error' => [
                                'code' => 40302,
                                'message' => 'Tool not allowed in current state: ' . $current,
                            ],
                        ];
                    }
                }
            }



            // Validate input

            $validator = new JsonSchemaValidator();

            $inputSchema = $tool->getInputSchema();

            $inputErrors = $validator->validate($params, $inputSchema);

            if ($inputErrors !== []) {
                return [

                    'error' => [

                        'code' => 40001,

                        'message' => 'Input validation failed',

                        'details' => [ 'errors' => $inputErrors ],

                    ],

                ];
            }



            // Cache check

            $cacheKey = $method . '|' . md5(json_encode($params));

            $cached = $this->cache->get($method, $cacheKey);

            if ($cached !== null) {
                $this->telemetry->emit('cache_hit', [ 'method' => $method ]);

                return [ 'result' => $cached ];
            }



            // Execute tool
            try {
                $result = $tool->execute($params);
            } catch (\Throwable $e) {
                return [
                    'error' => [
                        'code' => 50000,
                        'message' => 'Internal server error during tool execution: ' . $e->getMessage(),
                    ],
                ];
            }

            if (isset($result['error'])) {
                return [ 'error' => $result['error'] ];
            }



            // Validate output

            $outputSchema = $tool->getOutputSchema();

            $outputErrors = $validator->validate($result, $outputSchema);

            if ($outputErrors !== []) {
                return [

                    'error' => [

                        'code' => 50001,

                        'message' => 'Output validation failed',

                        'details' => [ 'errors' => $outputErrors ],

                    ],

                ];
            }



            // Save to cache (if enabled for this method)

            $this->cache->put($method, $cacheKey, $result);



            $this->telemetry->emit('tool_executed', [

                'method' => $method,

                'durationMs' => (int)floor(microtime(true) * 1000) - $t0,

            ]);



            return [ 'result' => $result ];
        }
    }
