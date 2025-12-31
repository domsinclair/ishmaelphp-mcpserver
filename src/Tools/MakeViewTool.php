<?php

declare(strict_types=1);

namespace Ishmael\McpServer\Tools;

use Ishmael\McpServer\Contracts\Tool;
use Ishmael\McpServer\Project\ProjectContext;
use Ishmael\McpServer\Support\IshCliBridge;

/**
 * ish:make:view — Create a view.
 */
final class MakeViewTool implements Tool
{
    private ProjectContext $context;

    public function __construct(ProjectContext $context)
    {
        $this->context = $context;
    }

    public function getName(): string
    {
        return 'ish:make:view';
    }

    public function getDescription(): string
    {
        return 'Create a specific view file (e.g., blog/show).';
    }

    public function getInputSchema(): array
    {
        return [
            'type' => 'object',
            'additionalProperties' => false,
            'required' => ['module', 'path'],
            'properties' => [
                'module' => ['type' => 'string', 'description' => 'Target module name.'],
                'path' => ['type' => 'string', 'description' => 'View path (e.g., blog/show).'],
                'templates' => ['type' => ['string', 'null'], 'description' => 'Override template source directory.'],
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
        $module = $input['module'];
        $path = $input['path'];
        $options = [];
        if (isset($input['templates'])) {
            $options['templates'] = $input['templates'];
        }

        $bridge = new IshCliBridge($this->context);
        $result = $bridge->execute('make:view', $options, [$module, $path]);

        return [
            'success' => $result['success'],
            'output' => $result['output'],
            'error' => $result['error'],
            'files' => $result['files'],
        ];
    }
}
