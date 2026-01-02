<?php

declare(strict_types=1);

namespace Ishmael\McpServer\Tools;

use Ishmael\McpServer\Contracts\Tool;
use Ishmael\McpServer\Project\ProjectContext;
use Ishmael\McpServer\Support\IshCliBridge;

/**
 * ish:modules:check — Validate the dependency graph and detect shadow dependencies.
 */
final class ModulesCheckTool implements Tool
{
    private ProjectContext $context;

    public function __construct(ProjectContext $context)
    {
        $this->context = $context;
    }

    public function getName(): string
    {
        return 'ish:modules:check';
    }

    public function getDescription(): string
    {
        return 'Validate module dependencies, detect circular dependencies, and find shadow dependencies (missing manifest declarations).';
    }

    public function getInputSchema(): array
    {
        return [
            'type' => 'object',
            'additionalProperties' => false,
            'properties' => [
                'module' => ['type' => 'string', 'description' => 'Specific module to check (optional).'],
            ],
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
            ],
        ];
    }

    public function execute(array $input): array
    {
        $bridge = new IshCliBridge($this->context);
        $arguments = [];
        if (!empty($input['module'])) {
            $arguments[] = $input['module'];
        }

        $result = $bridge->execute('modules:check', [], $arguments);

        return [
            'success' => $result['success'],
            'output' => $result['output'],
            'error' => $result['error'],
        ];
    }
}
