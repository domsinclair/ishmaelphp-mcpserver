<?php

declare(strict_types=1);

namespace Ishmael\McpServer\Tools;

use Ishmael\McpServer\Contracts\Tool;
use Ishmael\McpServer\Project\ProjectContext;
use Ishmael\McpServer\Support\StackTraceMapper;

/**
 * ish:project:snapshot — Generate a comprehensive diagnostic report.
 */
final class ProjectSnapshotTool implements Tool
{
    private ProjectContext $context;

    public function __construct(ProjectContext $context)
    {
        $this->context = $context;
    }

    public function getName(): string
    {
        return 'ish:project:snapshot';
    }

    public function getDescription(): string
    {
        return 'Generate a diagnostic snapshot containing project info, environment validation, and recent errors.';
    }

    public function getInputSchema(): array
    {
        return [
            'type' => 'object',
            'additionalProperties' => false,
            'properties' => [],
        ];
    }

    public function getOutputSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['project', 'environment', 'logs', 'events'],
            'properties' => [
                'project' => [
                    'type' => 'object',
                    'required' => ['root', 'binaries'],
                    'properties' => [
                        'root' => ['type' => ['string', 'null']],
                        'binaries' => ['type' => 'object'],
                    ]
                ],
                'environment' => [
                    'type' => 'object',
                    'required' => ['validate', 'drift'],
                    'properties' => [
                        'validate' => ['type' => 'object'],
                        'drift' => ['type' => 'object'],
                    ]
                ],
                'logs' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'timestamp' => ['type' => ['string', 'null']],
                            'level' => ['type' => ['string', 'null']],
                            'message' => ['type' => 'string'],
                            'stack_trace' => ['type' => 'array'],
                        ]
                    ]
                ],
                'events' => [
                    'type' => 'object',
                    'properties' => [
                        'total_events' => ['type' => 'integer'],
                        'core_events' => ['type' => 'integer', 'description' => 'Number of framework-level core events.'],
                        'module_events' => ['type' => 'integer', 'description' => 'Number of domain-specific module events.'],
                        'total_listeners' => ['type' => 'integer'],
                    ]
                ],
            ],
        ];
    }

    public function execute(array $input): array
    {
        return [
            'project' => $this->getProjectInfo(),
            'environment' => $this->getEnvironmentInfo(),
            'logs' => $this->getRecentErrors(),
            'events' => $this->getEventMetrics(),
        ];
    }

    private function getEventMetrics(): array
    {
        $eventTool = new EventsListTool($this->context);
        $result = $eventTool->execute([]);
        
        $events = $result['events'] ?? [];
        $totalEvents = count($events);
        $coreEvents = 0;
        $moduleEvents = 0;
        $totalListeners = 0;

        foreach ($events as $event) {
            if (($event['source_type'] ?? '') === 'core') {
                $coreEvents++;
            } else {
                $moduleEvents++;
            }
            $totalListeners += count($event['listeners'] ?? []);
        }

        return [
            'total_events' => $totalEvents,
            'core_events' => $coreEvents,
            'module_events' => $moduleEvents,
            'total_listeners' => $totalListeners,
        ];
    }

    private function getProjectInfo(): array
    {
        return [
            'root' => $this->context->getRoot(),
            'binaries' => $this->context->getBinaries(),
        ];
    }

    private function getEnvironmentInfo(): array
    {
        $validateTool = new EnvValidateTool($this->context);
        $driftTool = new EnvDriftTool($this->context);

        return [
            'validate' => $validateTool->execute([]),
            'drift' => $driftTool->execute([]),
        ];
    }

    private function getRecentErrors(): array
    {
        $logTool = new LogTailTool($this->context);
        $result = $logTool->execute(['maxItems' => 10, 'level' => 'ERROR']);
        
        return array_map(function($event) {
            return [
                'timestamp' => $event['timestamp'],
                'level' => $event['level'],
                'message' => $event['message'],
                'stack_trace' => $event['stack_trace'],
            ];
        }, $result['events']);
    }
}
