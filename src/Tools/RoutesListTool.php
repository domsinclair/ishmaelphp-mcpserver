<?php

declare(strict_types=1);

namespace Ishmael\McpServer\Tools;

use Ishmael\McpServer\Contracts\Tool;
use Ishmael\McpServer\Project\ProjectContext;
use Ishmael\McpServer\Support\RouteCollector;

final class RoutesListTool implements Tool
{
    private ProjectContext $context;

    public function __construct(ProjectContext $context)
    {
        $this->context = $context;
    }

    public function getName(): string
    {
        return 'ish:listRoutes';
    }

    public function getDescription(): string
    {
        return 'List application HTTP routes (method, path, handler) with optional filter and pagination.';
    }

    public function getInputSchema(): array
    {
        return [
            'type' => 'object',
            'additionalProperties' => false,
            'properties' => [
                'filter' => ['type' => ['string', 'null']],
                'limit' => ['type' => 'integer'],
                'offset' => ['type' => 'integer'],
            ],
        ];
    }

    public function getOutputSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['routes'],
            'properties' => [
                'routes' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'required' => ['method', 'path', 'handler'],
                        'properties' => [
                            'method' => ['type' => 'string'],
                            'path' => ['type' => 'string'],
                            'handler' => ['type' => 'string'],
                        ],
                        'additionalProperties' => false,
                    ],
                ],
                'total' => ['type' => 'integer'],
                'offset' => ['type' => 'integer'],
                'limit' => ['type' => 'integer'],
                'truncated' => ['type' => 'boolean'],
            ],
        ];
    }

    public function execute(array $input): array
    {
        try {
            $collector = new RouteCollector($this->context, true);
            $routes = $collector->collect();

            // Apply filtering
            $filter = isset($input['filter']) && is_string($input['filter']) ? $input['filter'] : null;
            if ($filter !== null && $filter !== '') {
                $q = mb_strtolower($filter);
                $routes = array_values(array_filter($routes, function($r) use ($q) {
                    return mb_stripos($r['path'] . ' ' . $r['handler'], $q) !== false;
                }));
            }

            // Deterministic sort: path then method
            usort($routes, function($a, $b) {
                return [$a['path'], $a['method']] <=> [$b['path'], $b['method']];
            });

            $total = count($routes);
            $limit = max(1, (int)($input['limit'] ?? 2000));
            $offset = max(0, (int)($input['offset'] ?? 0));

            $slice = array_slice($routes, $offset, $limit);
            $truncated = ($offset + $limit) < $total;

            return [
                'routes' => $slice,
                'total' => $total,
                'limit' => $limit,
                'offset' => $offset,
                'truncated' => $truncated,
            ];
        } catch (\Throwable $e) {
            // Log to stderr and return a structured error that the Server can wrap
            fwrite(STDERR, "[RoutesListTool] Fatal error: " . $e->getMessage() . "\n");
            return [
                'error' => [
                    'code' => 500,
                    'message' => 'Route enumeration failed: ' . $e->getMessage(),
                ],
            ];
        }
    }
}
