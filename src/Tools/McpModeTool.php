<?php

declare(strict_types=1);

namespace Ishmael\McpServer\Tools;

use Ishmael\McpServer\Contracts\Tool;
use Ishmael\McpServer\Server\ProjectStateManager;

/**
 * ish:mcp:mode — Switch or query orchestration mode (quick|standard).
 *
 * Behavior:
 * - If input contains { mode }, validates and sets it, returning the new mode.
 * - Always returns current mode and orchestration state snapshot.
 */
final class McpModeTool implements Tool
{
    private ProjectStateManager $state;

    public function __construct(?ProjectStateManager $state = null)
    {
        $this->state = $state ?? new ProjectStateManager();
    }

    public function getName(): string
    {
        return 'ish:mcp:mode';
    }

    public function getDescription(): string
    {
        return 'Get or set the orchestration mode (quick|standard) and return current state.';
    }

    public function getInputSchema(): array
    {
        return [
            'type' => 'object',
            'additionalProperties' => false,
            'properties' => [
                'mode' => [ 'type' => 'string', 'enum' => [ProjectStateManager::MODE_QUICK, ProjectStateManager::MODE_STANDARD] ],
            ],
        ];
    }

    public function getOutputSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['mode', 'state'],
            'properties' => [
                'mode' => [ 'type' => 'string', 'enum' => [ProjectStateManager::MODE_QUICK, ProjectStateManager::MODE_STANDARD] ],
                'state' => [ 'type' => 'string' ],
                'locked' => [ 'type' => 'array', 'items' => [ 'type' => 'string' ] ],
                'stateFile' => [ 'type' => 'string' ],
            ],
        ];
    }

    public function execute(array $input): array
    {
        if (isset($input['mode']) && is_string($input['mode'])) {
            $this->state->setMode($input['mode']);
        }

        return [
            'mode' => $this->state->getMode(),
            'state' => $this->state->getState(),
            'locked' => $this->state->getLockedStages(),
            'stateFile' => $this->state->getStateFile(),
        ];
    }
}
