<?php

declare(strict_types=1);

namespace Ishmael\McpServer\Tools;

use Ishmael\McpServer\Contracts\Tool;
use Ishmael\McpServer\Project\ProjectContext;
use Ishmael\McpServer\Support\IshCliBridge;

/**
 * ish:capabilities:describe — Inspect a module's capabilities and their current availability.
 */
final class CapabilitiesDescribeTool implements Tool
{
    private ProjectContext $context;

    public function __construct(ProjectContext $context)
    {
        $this->context = $context;
    }

    public function getName(): string
    {
        return 'ish:capabilities:describe';
    }

    public function getDescription(): string
    {
        return 'Inspect a module\'s capabilities and their current availability (Available, Trial Required, or Licensed).';
    }

    public function getInputSchema(): array
    {
        return [
            'type' => 'object',
            'additionalProperties' => false,
            'required' => ['module'],
            'properties' => [
                'module' => [
                    'type' => 'string',
                    'description' => 'The name of the module to inspect.'
                ],
            ],
        ];
    }

    public function getOutputSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['success', 'capabilities'],
            'properties' => [
                'success' => ['type' => 'boolean'],
                'module' => ['type' => 'string'],
                'tier' => ['type' => 'string', 'description' => 'Module tier (community, commercial, or dual).'],
                'capabilities' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'id' => ['type' => 'string'],
                            'description' => ['type' => 'string'],
                            'tier' => ['type' => 'string', 'description' => 'community or premium'],
                            'status' => ['type' => 'string', 'description' => 'Available, Trial Required, or Licensed'],
                        ],
                    ],
                ],
                'output' => ['type' => 'string'],
                'error' => ['type' => ['string', 'null']],
            ],
        ];
    }

    public function execute(array $input): array
    {
        $module = $input['module'];
        $bridge = new IshCliBridge($this->context);
        
        // This relies on the framework's normalized manifest reporting
        $result = $bridge->execute('capabilities:describe', [], [$module]);

        if (!$result['success']) {
            return [
                'success' => false,
                'capabilities' => [],
                'error' => $result['error'] ?? 'Unknown error describing capabilities.',
                'output' => $result['output'],
            ];
        }

        // The CLI command is expected to return a JSON-encoded summary in the output
        // when called via the MCP bridge (which sets ISH_BOOTSTRAP_ONLY=1).
        // For now, we attempt to parse the output.
        $data = json_decode($result['output'], true);
        
        if (json_last_error() === JSON_ERROR_NONE && is_array($data)) {
            return array_merge(['success' => true, 'output' => $result['output']], $data);
        }

        // Fallback or legacy output parsing if not JSON
        return [
            'success' => true,
            'module' => $module,
            'capabilities' => [],
            'output' => $result['output'],
            'note' => 'Raw output returned; structured parsing failed.'
        ];
    }
}
