<?php

declare(strict_types=1);

namespace Ishmael\McpServer\Tools;

use Ishmael\McpServer\Contracts\Tool;
use Ishmael\McpServer\Project\ProjectContext;
use Ishmael\McpServer\Support\IshCliBridge;

/**
 * ish:make:module — Scaffold a new module.
 */
final class MakeModuleTool implements Tool
{
    private ProjectContext $context;

    public function __construct(ProjectContext $context)
    {
        $this->context = $context;
    }

    public function getName(): string
    {
        return 'ish:make:module';
    }

    public function getDescription(): string
    {
        return 'Scaffold a new module skeleton (controllers, models, views, routes.php, module.json).';
    }

    public function getInputSchema(): array
    {
        return [
            'type' => 'object',
            'additionalProperties' => false,
            'required' => ['name'],
            'properties' => [
                'name' => ['type' => 'string', 'description' => 'The name of the module (StudlyCase preferred).'],
                'api' => ['type' => 'boolean', 'description' => 'Hint API-style module (no session/CSRF route grouping).'],
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
        $name = $input['name'];
        $options = [];
        if (!empty($input['api'])) {
            $options['api'] = true;
        }
        if (isset($input['templates'])) {
            $options['templates'] = $input['templates'];
        }

        $bridge = new IshCliBridge($this->context);
        $result = $bridge->execute('make:module', $options, [$name]);

        return [
            'success' => $result['success'],
            'output' => $result['output'],
            'error' => $result['error'],
            'files' => $result['files'],
        ];
    }
}
