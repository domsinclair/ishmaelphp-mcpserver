<?php

declare(strict_types=1);

namespace Ishmael\McpServer\Tools;

use Ishmael\McpServer\Contracts\Tool;
use Ishmael\McpServer\Project\ProjectContext;
use Ishmael\McpServer\Support\ModuleDependencyMapper;

/**
 * ish:modules:list — List all modules and their basic metadata.
 */
final class ModulesListTool implements Tool
{
    private ProjectContext $context;

    public function __construct(ProjectContext $context)
    {
        $this->context = $context;
    }

    public function getName(): string
    {
        return 'ish:modules:list';
    }

    public function getDescription(): string
    {
        return 'List all modules in the project with their versions, paths, and basic architectural metadata.';
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
            'required' => ['modules'],
            'properties' => [
                'modules' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'required' => ['id', 'path', 'version'],
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
            ],
        ];
    }

    public function execute(array $input): array
    {
        try {
            $mapper = new ModuleDependencyMapper($this->context);
            $map = $mapper->map();
            
            return [
                'modules' => $map['nodes'],
            ];
        } catch (\Throwable $e) {
            return [
                'modules' => [],
                'error' => 'Failed to list modules: ' . $e->getMessage(),
            ];
        }
    }
}
