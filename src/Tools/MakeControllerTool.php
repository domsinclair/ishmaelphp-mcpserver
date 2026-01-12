<?php

declare(strict_types=1);

namespace Ishmael\McpServer\Tools;

use Ishmael\McpServer\Contracts\Tool;
use Ishmael\McpServer\Project\ProjectContext;
use Ishmael\McpServer\Support\IshCliBridge;

/**
 * ish:make:controller — Create a controller.
 */
final class MakeControllerTool implements Tool
{
    private ProjectContext $context;

    public function __construct(ProjectContext $context)
    {
        $this->context = $context;
    }

    public function getName(): string
    {
        return 'ish:make:controller';
    }

    public function getDescription(): string
    {
        return 'Create a controller class inside a module. Automatically uses Request::file() and Response::download() for file operations if applicable.';
    }

    public function getInputSchema(): array
    {
        return [
            'type' => 'object',
            'additionalProperties' => false,
            'required' => ['module', 'name'],
            'properties' => [
                'module' => ['type' => 'string', 'description' => 'Target module name.'],
                'name' => ['type' => 'string', 'description' => 'Controller name.'],
                'invokable' => ['type' => 'boolean', 'description' => 'Generate an invokable controller.'],
                'api' => ['type' => 'boolean', 'description' => 'Generate an API controller (standard JSON responses and HTTP status codes).'],
                'templates' => ['type' => ['string', 'null'], 'description' => 'Override template source directory.'],
                'preview' => ['type' => 'boolean', 'description' => 'Preview the generated code without writing to disk.'],
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
                'preview' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'path' => ['type' => 'string'],
                            'content' => ['type' => 'string'],
                        ]
                    ],
                    'description' => 'Generated file contents (if preview was requested).'
                ],
            ],
        ];
    }

    public function execute(array $input): array
    {
        $module = $input['module'];
        $name = $input['name'];
        $options = [];
        if (!empty($input['invokable'])) {
            $options['invokable'] = true;
        }
        if (!empty($input['api'])) {
            $options['api'] = true;
        }
        if (isset($input['templates'])) {
            $options['templates'] = $input['templates'];
        }
        if (!empty($input['preview'])) {
            $options['preview'] = true;
        }

        $bridge = new IshCliBridge($this->context);
        $result = $bridge->execute('make:controller', $options, [$module, $name]);

        return [
            'success' => $result['success'],
            'output' => $result['output'],
            'error' => $result['error'],
            'files' => $result['files'],
            'preview' => $result['preview'] ?? [],
        ];
    }
}
