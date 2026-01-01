<?php

declare(strict_types=1);

namespace Ishmael\McpServer\Tools;

use Ishmael\McpServer\Contracts\Tool;
use Ishmael\McpServer\Project\ProjectContext;
use Ishmael\McpServer\Support\IshCliBridge;

/**
 * A generic tool that wraps an arbitrary Ishmael CLI command based on discovered metadata.
 */
class DynamicIshTool implements Tool
{
    private ProjectContext $context;
    private array $metadata;

    public function __construct(ProjectContext $context, array $metadata)
    {
        $this->context = $context;
        $this->metadata = $metadata;
    }

    public function getName(): string
    {
        return 'ish:' . ($this->metadata['name'] ?? 'unknown');
    }

    public function getDescription(): string
    {
        return $this->metadata['description'] ?? 'Ishmael CLI command.';
    }

    public function getInputSchema(): array
    {
        $properties = [];
        $required = [];

        $options = $this->metadata['options'] ?? [];
        foreach ($options as $opt) {
            $optName = ltrim($opt['name'], '-');
            $description = $opt['description'] ?? '';
            $accepts = $opt['accepts'] ?? null;
            $isOptional = $opt['optional'] ?? true;

            if ($accepts) {
                $properties[$optName] = [
                    'type' => 'string',
                    'description' => $description . " (expects: $accepts)"
                ];
            } else {
                $properties[$optName] = [
                    'type' => 'boolean',
                    'description' => $description
                ];
            }

            if (!$isOptional) {
                $required[] = $optName;
            }
        }

        // Also handle positional arguments from metadata if they exist
        $args = $this->metadata['arguments'] ?? [];
        foreach ($args as $arg) {
            $argName = $arg['name'] ?? null;
            if (!$argName) continue;
            
            $description = $arg['description'] ?? '';
            $isOptional = $arg['optional'] ?? true;
            
            $properties[$argName] = [
                'type' => 'string',
                'description' => $description
            ];
            
            if (!$isOptional) {
                $required[] = $argName;
            }
        }
        
        $schema = [
            'type' => 'object',
            'properties' => $properties,
            'additionalProperties' => true,
        ];

        if ($required !== []) {
            $schema['required'] = $required;
        }

        return $schema;
    }

    public function getOutputSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['success', 'output'],
            'properties' => [
                'success' => ['type' => 'boolean'],
                'output' => ['type' => 'string'],
                'error' => ['type' => ['string', 'null']],
                'files' => [
                    'type' => 'array',
                    'items' => ['type' => 'string']
                ]
            ],
        ];
    }

    public function execute(array $input): array
    {
        $bridge = new IshCliBridge($this->context);
        
        $commandName = $this->metadata['name'];
        $options = [];
        $arguments = [];

        foreach ($input as $key => $value) {
            if (is_int($key)) {
                $arguments[] = $value;
            } else {
                $options[$key] = $value;
            }
        }

        $result = $bridge->execute($commandName, $options, $arguments);

        return [
            'success' => $result['success'],
            'output' => $result['output'],
            'error' => $result['error'],
            'files' => $result['files'],
        ];
    }
}
