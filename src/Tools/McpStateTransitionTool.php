<?php
declare(strict_types=1);
namespace Ishmael\McpServer\Tools;
use Ishmael\McpServer\Contracts\Tool;
use Ishmael\McpServer\Server\ProjectStateManager;
/**
 * ish:mcp:transition - Advance or revert project orchestration state.
 */
final class McpStateTransitionTool implements Tool
{
    private ProjectStateManager $state;
    public function __construct(?ProjectStateManager $state = null)
    {
        $this->state = $state ?? new ProjectStateManager();
    }
    public function getName(): string
    {
        return 'ish:mcp:transition';
    }
    public function getDescription(): string
    {
        return 'Transition the project orchestration state to a new stage.';
    }
    public function getInputSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['state'],
            'properties' => [
                'state' => [ 'type' => 'string' ],
            ],
        ];
    }
    public function getOutputSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['success', 'currentState'],
            'properties' => [
                'success' => [ 'type' => 'boolean' ],
                'currentState' => [ 'type' => 'string' ],
                'error' => [ 'type' => 'string' ],
            ],
        ];
    }
    public function execute(array $input): array
    {
        $target = (string)($input['state'] ?? '');
        $success = $this->state->transition($target);
        return [
            'success' => $success,
            'currentState' => $this->state->getState(),
            'error' => $success ? null : "Illegal transition to '$target' from '{$this->state->getState()}'",
        ];
    }
}
