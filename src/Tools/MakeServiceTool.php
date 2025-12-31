<?php

declare(strict_types=1);

namespace Ishmael\McpServer\Tools;

use Ishmael\McpServer\Contracts\Tool;
use Ishmael\McpServer\Project\ProjectContext;
use Ishmael\McpServer\Support\IshCliBridge;

/**
 * ish:make:service — Create a service.
 */
final class MakeServiceTool implements Tool
{
    private ProjectContext $context;

    public function __construct(ProjectContext $context)
    {
        $this->context = $context;
    }

    public function getName(): string
    {
        return 'ish:make:service';
    }

    public function getDescription(): string
    {
        return 'Create a service class inside a module.';
    }

    public function getInputSchema(): array
    {
        return [
            'type' => 'object',
            'additionalProperties' => false,
            'required' => ['module', 'name'],
            'properties' => [
                'module' => ['type' => 'string', 'description' => 'Target module name.'],
                'name' => ['type' => 'string', 'description' => 'Service name.'],
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
        $name = $input['name'];
        $options = [];
        if (isset($input['templates'])) {
            $options['templates'] = $input['templates'];
        }

        $bridge = new IshCliBridge($this->context);
        $result = $bridge->execute('make:service', $options, [$module, $name]);

        return [
            'success' => $result['success'],
            'output' => $result['output'],
            'error' => $result['error'],
            'files' => $result['files'],
        ];
    }
}
