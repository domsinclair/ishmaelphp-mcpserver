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

        // STRICT mode validation gates
        if ($this->state->getMode() === ProjectStateManager::MODE_STRICT) {
            $current = $this->state->getState();

            // Transitioning FROM INIT TO ANALYSIS_COMPLETE
            if ($current === ProjectStateManager::INIT && $target === ProjectStateManager::ANALYSIS_COMPLETE) {
                if (!$this->hasQueriedCapabilities()) {
                    return [
                        'success' => false,
                        'currentState' => $current,
                        'error' => "STRICT MODE: Transition to ANALYSIS_COMPLETE blocked. You MUST call `ish:capabilities:describe` to understand project capabilities before proceeding.",
                    ];
                }
            }

            // Transitioning FROM implementation COMPLETE or IN_PROGRESS TO review
            if (($current === ProjectStateManager::IMPLEMENTATION_COMPLETE || $current === ProjectStateManager::IMPLEMENTATION_IN_PROGRESS)
                && $target === ProjectStateManager::REVIEW_COMPLETE
            ) {
                if (!$this->hasSuccessfulValidation()) {
                    return [
                        'success' => false,
                        'currentState' => $current,
                        'error' => "STRICT MODE: Transition to REVIEW_COMPLETE blocked. You MUST run and pass `ish:env:validate` or `ish:featurePack:validate` before completing implementation.",
                    ];
                }
            }
        }

        $success = $this->state->transition($target);
        return [
            'success' => $success,
            'currentState' => $this->state->getState(),
            'error' => $success ? null : "Illegal transition to '$target' from '{$this->state->getState()}'",
        ];
    }

    private function hasSuccessfulValidation(): bool
    {
        $log = $this->state->getToolInvocationLog();
        foreach (array_reverse($log) as $entry) {
            $tool = $entry['tool'] ?? '';
            $result = $entry['result'] ?? [];

            if ($tool === 'ish:env:validate') {
                // If violations is empty, it's a success
                if (isset($result['violations']) && empty($result['violations'])) {
                    return true;
                }
            }

            if ($tool === 'ish:featurePack:validate') {
                // If canPublish is true, it's a success
                if (!empty($result['canPublish'])) {
                    return true;
                }
            }
        }
        return false;
    }

    private function hasQueriedCapabilities(): bool
    {
        $log = $this->state->getToolInvocationLog();
        foreach ($log as $entry) {
            if (($entry['tool'] ?? '') === 'ish:capabilities:describe') {
                return true;
            }
        }
        return false;
    }
}
