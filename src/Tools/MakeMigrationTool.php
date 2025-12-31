<?php

declare(strict_types=1);

namespace Ishmael\McpServer\Tools;

use Ishmael\McpServer\Contracts\Tool;
use Ishmael\McpServer\Project\ProjectContext;
use Ishmael\McpServer\Support\IshCliBridge;

/**
 * ish:make:migration — Create a migration.
 */
final class MakeMigrationTool implements Tool
{
    private ProjectContext $context;

    public function __construct(ProjectContext $context)
    {
        $this->context = $context;
    }

    public function getName(): string
    {
        return 'ish:make:migration';
    }

    public function getDescription(): string
    {
        return 'Create a timestamped migration file.';
    }

    public function getInputSchema(): array
    {
        return [
            'type' => 'object',
            'additionalProperties' => false,
            'required' => ['name'],
            'properties' => [
                'name' => ['type' => 'string', 'description' => 'Migration name.'],
                'module' => ['type' => ['string', 'null'], 'description' => 'Target module name.'],
            ],
        ];
    }

    public function getOutputSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['success', 'output', 'files'],
            'properties' => [
                'success' => ['type' => 'boolean'],
                'output' => ['type' => 'string'],
                'error' => ['type' => ['string', 'null']],
                'files' => [
                    'type' => 'array',
                    'items' => ['type' => 'string'],
                    'description' => 'Absolute paths of created files.'
                ],
            ],
        ];
    }

    public function execute(array $input): array
    {
        $name = $input['name'];
        $options = [];
        if (isset($input['module'])) {
            $options['module'] = $input['module'];
        }

        $bridge = new IshCliBridge($this->context);
        $result = $bridge->execute('make:migration', $options, [$name]);

        return [
            'success' => $result['success'],
            'output' => $result['output'],
            'error' => $result['error'],
            'files' => $result['files'],
        ];
    }
}
