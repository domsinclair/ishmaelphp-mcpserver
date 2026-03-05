<?php

declare(strict_types=1);

namespace Ishmael\McpServer\Tools;

use Ishmael\McpServer\Contracts\Tool;
use Ishmael\McpServer\Project\ProjectContext;
use Ishmael\McpServer\Support\ModuleDependencyMapper;

/**
 * ish:architecture:map — Map relationships between modules and architectural zones.
 */
final class ArchitectureMapTool implements Tool
{
    private ProjectContext $context;

    public function __construct(ProjectContext $context)
    {
        $this->context = $context;
    }

    public function getName(): string
    {
        return 'ish:architecture:map';
    }

    public function getDescription(): string
    {
        return 'Returns a semantic map of the project architecture, including module dependencies and architectural health markers.';
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
                            'architecture' => [
                                'type' => 'object',
                                'properties' => [
                                    'outgoing_dependencies' => ['type' => 'integer'],
                                    'incoming_dependants' => ['type' => 'integer'],
                                    'is_god_module' => ['type' => 'boolean'],
                                    'suggestion' => ['type' => ['string', 'null']],
                                ],
                            ],
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
            return [
                'nodes' => [],
                'edges' => [],
                'error' => 'Architecture mapping failed: ' . $e->getMessage(),
            ];
        }
    }
}
