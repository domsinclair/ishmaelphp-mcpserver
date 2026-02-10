<?php

declare(strict_types=1);

use Ishmael\McpServer\Server\ProjectStateManager;
use PHPUnit\Framework\TestCase;

final class ProjectStateManagerTest extends TestCase
{
    private function makeTempDir(): string
    {
        $base = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'ishmael_mcp_state_test_' . bin2hex(random_bytes(4));
        mkdir($base, 0777, true);
        return $base;
    }

    public function test_defaults_and_persistence(): void
    {
        $root = $this->makeTempDir();
        $mgr = new ProjectStateManager($root);
        $this->assertSame(ProjectStateManager::INIT, $mgr->getState());
        $this->assertSame(ProjectStateManager::MODE_QUICK, $mgr->getMode());

        // Change mode and persist
        $mgr->setMode(ProjectStateManager::MODE_STANDARD);
        $this->assertSame(ProjectStateManager::MODE_STANDARD, $mgr->getMode());

        // New instance picks up persisted mode
        $mgr2 = new ProjectStateManager($root);
        $this->assertSame(ProjectStateManager::MODE_STANDARD, $mgr2->getMode());
        $this->assertSame(ProjectStateManager::INIT, $mgr2->getState());

        // Transition flow happy path
        $this->assertTrue($mgr2->transition(ProjectStateManager::ANALYSIS_COMPLETE));
        $this->assertSame(ProjectStateManager::ANALYSIS_COMPLETE, $mgr2->getState());
        $this->assertContains(ProjectStateManager::INIT, $mgr2->getLockedStages());

        // Illegal transition rejected
        $this->assertFalse($mgr2->transition(ProjectStateManager::IMPLEMENTATION_IN_PROGRESS));

        // Legal transition
        $this->assertTrue($mgr2->transition(ProjectStateManager::ARCHITECTURE_COMPLETE));
        $this->assertSame(ProjectStateManager::ARCHITECTURE_COMPLETE, $mgr2->getState());

        // Accept path resets to INIT
        $this->assertTrue($mgr2->transition(ProjectStateManager::IMPLEMENTATION_IN_PROGRESS));
        $this->assertTrue($mgr2->transition(ProjectStateManager::IMPLEMENTATION_COMPLETE));
        $this->assertTrue($mgr2->transition(ProjectStateManager::REVIEW_COMPLETE));
        $this->assertTrue($mgr2->transition(ProjectStateManager::ACCEPTED));
        $this->assertSame(ProjectStateManager::INIT, $mgr2->getState());
        $this->assertSame([], $mgr2->getLockedStages());
    }
}
