<?php

declare(strict_types=1);

namespace Ishmael\McpServer\Tools;

use Ishmael\McpServer\Contracts\Tool;
use Ishmael\McpServer\Project\ProjectContext;
use Ishmael\McpServer\Support\IshCliBridge;

/**
 * ish:events:list — List all registered events and their listeners.
 */
final class EventsListTool implements Tool
{
    private ProjectContext $context;

    public function __construct(ProjectContext $context)
    {
        $this->context = $context;
    }

    public function getName(): string
    {
        return 'ish:events:list';
    }

    public function getDescription(): string
    {
        return 'List all registered events, their descriptions, payloads, and registered listeners across all modules.';
    }

    public function getInputSchema(): array
    {
        return [
            'type' => 'object',
            'additionalProperties' => false,
            'properties' => [
                'json' => ['type' => 'boolean', 'description' => 'Return raw JSON output from the framework.', 'default' => true],
            ],
        ];
    }

    public function getOutputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'success' => ['type' => 'boolean'],
                'events' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'event' => ['type' => 'string'],
                            'description' => ['type' => 'string'],
                            'payload' => ['type' => 'object'],
                            'listeners' => [
                                'type' => 'array',
                                'items' => [
                                    'type' => 'object',
                                    'properties' => [
                                        'module' => ['type' => 'string'],
                                        'listener' => ['type' => 'string'],
                                    ]
                                ]
                            ],
                            'emitted_by' => ['type' => 'array', 'items' => ['type' => 'string']],
                            'source_type' => ['type' => 'string', 'description' => 'Where this event emanates from (core or module).'],
                        ]
                    ]
                ],
                'error' => ['type' => ['string', 'null']],
            ],
        ];
    }

    public function execute(array $input): array
    {
        $bridge = new IshCliBridge($this->context);
        $result = $bridge->execute('events:list', ['json' => true]);

        if (!$result['success']) {
            return [
                'success' => false,
                'events' => [],
                'error' => $result['error'] ?? 'Failed to execute events:list',
            ];
        }

        $data = json_decode($result['output'], true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return [
                'success' => false,
                'events' => [],
                'error' => 'Failed to parse JSON output: ' . json_last_error_msg(),
            ];
        }

        return [
            'success' => true,
            'events' => $data['events'] ?? [],
            'error' => null,
        ];
    }
}
