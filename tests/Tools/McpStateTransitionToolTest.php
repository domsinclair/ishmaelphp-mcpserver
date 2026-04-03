<?php

declare(strict_types=1);

namespace Ishmael\McpServer\Tests\Tools;

use Ishmael\McpServer\Server\ProjectStateManager;
use Ishmael\McpServer\Tools\McpStateTransitionTool;
use PHPUnit\Framework\TestCase;

final class McpStateTransitionToolTest extends TestCase
{
    private function makeTempDir(): string
    {
        $base = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'ish_transition_test_' . bin2hex(random_bytes(4));
        mkdir($base, 0777, true);
        return $base;
    }

    public function testExecuteTransitionsState(): void
    {
        $root = $this->makeTempDir();
        $state = new ProjectStateManager($root);
        // STRICT mode requires capabilities describe for this transition
        $state->logToolInvocation('ish:capabilities:describe');
        $tool = new McpStateTransitionTool($state);

        $resp = $tool->execute(['state' => ProjectStateManager::ANALYSIS_COMPLETE]);
        $this->assertTrue($resp['success']);
        $this->assertSame(ProjectStateManager::ANALYSIS_COMPLETE, $resp['currentState']);
        $this->assertSame(ProjectStateManager::ANALYSIS_COMPLETE, $state->getState());
    }

    public function testStrictModeBlocksAnalysisWithoutCapabilities(): void
    {
        $root = $this->makeTempDir();
        $state = new ProjectStateManager($root);
        $state->setMode(ProjectStateManager::MODE_STRICT);
        $tool = new McpStateTransitionTool($state);

        $resp = $tool->execute(['state' => ProjectStateManager::ANALYSIS_COMPLETE]);
        $this->assertFalse($resp['success']);
        $this->assertStringContainsString('STRICT MODE: Transition to ANALYSIS_COMPLETE blocked', $resp['error']);
    }

    public function testStrictModeBlocksReviewWithoutValidation(): void
    {
        $root = $this->makeTempDir();
        $state = new ProjectStateManager($root);
        $state->setMode(ProjectStateManager::MODE_STRICT);
        $state->logToolInvocation('ish:capabilities:describe');
        $state->transition(ProjectStateManager::ANALYSIS_COMPLETE);
        $state->transition(ProjectStateManager::ARCHITECTURE_COMPLETE);
        $state->transition(ProjectStateManager::IMPLEMENTATION_IN_PROGRESS);
        $state->transition(ProjectStateManager::IMPLEMENTATION_COMPLETE);

        $tool = new McpStateTransitionTool($state);

        // Try to transition to REVIEW_COMPLETE without validation
        $resp = $tool->execute(['state' => ProjectStateManager::REVIEW_COMPLETE]);
        $this->assertFalse($resp['success']);
        $this->assertStringContainsString('STRICT MODE: Transition to REVIEW_COMPLETE blocked', $resp['error']);
    }

    public function testStrictModeAllowsReviewAfterSuccessfulEnvValidation(): void
    {
        $root = $this->makeTempDir();
        $state = new ProjectStateManager($root);
        $state->setMode(ProjectStateManager::MODE_STRICT);
        $state->logToolInvocation('ish:capabilities:describe');
        $state->transition(ProjectStateManager::ANALYSIS_COMPLETE);
        $state->transition(ProjectStateManager::ARCHITECTURE_COMPLETE);
        $state->transition(ProjectStateManager::IMPLEMENTATION_IN_PROGRESS);
        $state->transition(ProjectStateManager::IMPLEMENTATION_COMPLETE);

        // Log successful validation
        $state->logToolInvocation('ish:env:validate', [], ['violations' => []]);

        $tool = new McpStateTransitionTool($state);
        $resp = $tool->execute(['state' => ProjectStateManager::REVIEW_COMPLETE]);
        $this->assertTrue($resp['success']);
        $this->assertSame(ProjectStateManager::REVIEW_COMPLETE, $state->getState());
    }

    public function testStrictModeAllowsReviewAfterSuccessfulFeaturePackValidation(): void
    {
        $root = $this->makeTempDir();
        $state = new ProjectStateManager($root);
        $state->setMode(ProjectStateManager::MODE_STRICT);
        $state->logToolInvocation('ish:capabilities:describe');
        $state->transition(ProjectStateManager::ANALYSIS_COMPLETE);
        $state->transition(ProjectStateManager::ARCHITECTURE_COMPLETE);
        $state->transition(ProjectStateManager::IMPLEMENTATION_IN_PROGRESS);
        $state->transition(ProjectStateManager::IMPLEMENTATION_COMPLETE);

        // Log successful validation
        $state->logToolInvocation('ish:featurePack:validate', ['module' => 'Test'], ['canPublish' => true]);

        $tool = new McpStateTransitionTool($state);
        $resp = $tool->execute(['state' => ProjectStateManager::REVIEW_COMPLETE]);
        $this->assertTrue($resp['success']);
        $this->assertSame(ProjectStateManager::REVIEW_COMPLETE, $state->getState());
    }

    public function testFreeformModeDoesNotBlockReview(): void
    {
        $root = $this->makeTempDir();
        $state = new ProjectStateManager($root);
        $state->setMode(ProjectStateManager::MODE_FREEFORM);
        $state->transition(ProjectStateManager::ANALYSIS_COMPLETE);
        $state->transition(ProjectStateManager::ARCHITECTURE_COMPLETE);
        $state->transition(ProjectStateManager::IMPLEMENTATION_IN_PROGRESS);
        $state->transition(ProjectStateManager::IMPLEMENTATION_COMPLETE);

        $tool = new McpStateTransitionTool($state);

        // Should NOT block in FREEFORM even without validation
        $resp = $tool->execute(['state' => ProjectStateManager::REVIEW_COMPLETE]);
        $this->assertTrue($resp['success']);
        $this->assertSame(ProjectStateManager::REVIEW_COMPLETE, $state->getState());
    }
}
