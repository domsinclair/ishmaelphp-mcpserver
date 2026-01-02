<?php

declare(strict_types=1);

namespace Ishmael\McpServer\Tools;

use Ishmael\McpServer\Contracts\Tool;
use Ishmael\McpServer\Project\ProjectContext;
use Ishmael\McpServer\Support\ModuleDependencyMapper;

/**
 * ish:modules:dependencies — Map relationships between modules.
 */
final class ModuleDependencyTool implements Tool
{
    private ProjectContext $context;

    public function __construct(ProjectContext $context)
    {
        $this->context = $context;
    }

    public function getName(): string
    {
        return 'ish:modules:dependencies';
    }

    public function getDescription(): string
    {
        return 'Map relationships between modules by scanning manifests and service registrations.';
    }

    public function getInputSchema(): array
    {
        return [
            'type' => 'object',
            'additionalProperties' => false,
            'properties' => [],
        ];
    }

    public function getOutputSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['nodes', 'edges'],
            'properties' => [
                'nodes' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'required' => ['id', 'path'],
                        'properties' => [
                            'id' => ['type' => 'string'],
                            'path' => ['type' => 'string'],
                            'version' => ['type' => 'string'],
                            'env' => ['type' => 'string'],
                        ],
                    ],
                ],
                'edges' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'required' => ['from', 'to', 'type'],
                        'properties' => [
                            'from' => ['type' => 'string'],
                            'to' => ['type' => 'string'],
                            'type' => ['type' => 'string', 'enum' => ['explicit', 'service']],
                        ],
                    ],
                ],
            ],
        ];
    }

    public function execute(array $input): array
    {
        try {
            $mapper = new ModuleDependencyMapper($this->context);
            return $mapper->map();
        } catch (\Throwable $e) {
            fwrite(STDERR, "[ModuleDependencyTool] Fatal error: " . $e->getMessage() . "\n");
            return [
                'error' => [
                    'code' => 500,
                    'message' => 'Dependency mapping failed: ' . $e->getMessage(),
                ],
            ];
        }
    }
}
