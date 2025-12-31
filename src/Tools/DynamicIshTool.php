<?php

declare(strict_types=1);

namespace Ishmael\McpServer\Tools;

use Ishmael\McpServer\Contracts\Tool;
use Ishmael\McpServer\Project\ProjectContext;
use Ishmael\McpServer\Support\IshCliBridge;

/**
 * A generic tool that wraps an arbitrary Ishmael CLI command based on discovered metadata.
 */
final class DynamicIshTool implements Tool
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
        }

        // Also allow positional arguments if the description hints at them
        // For now we'll just support options as defined in the registry
        
        return [
            'type' => 'object',
            'properties' => $properties,
            'additionalProperties' => true, // Allow positional args or unknown options
        ];
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
