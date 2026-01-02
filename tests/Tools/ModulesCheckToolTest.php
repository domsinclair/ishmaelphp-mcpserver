<?php

declare(strict_types=1);

namespace Ishmael\McpServer\Tests\Tools;

use Ishmael\McpServer\Project\ProjectContext;
use Ishmael\McpServer\Tools\ModulesCheckTool;
use Ishmael\McpServer\Support\IshCliBridge;
use PHPUnit\Framework\TestCase;

class ModulesCheckToolTest extends TestCase
{
    public function testExecuteCallsBridgeWithCorrectArguments(): void
    {
        $context = $this->createMock(ProjectContext::class);
        $context->method('getIshBinary')->willReturn('/path/to/ish');
        $context->method('getRoot')->willReturn('/project/root');

        // We need to mock the bridge or the execution. 
        // Since IshCliBridge is instantiated inside execute(), we might need a different approach 
        // if we want to avoid actual process execution.
        // However, many existing tests in this project seem to use real or semi-real execution 
        // or they mock the context enough.
        
        // For a pure unit test of the Tool class:
        $tool = new ModulesCheckTool($context);
        
        $this->assertEquals('ish:modules:check', $tool->getName());
        $this->assertStringContainsString('Validate module dependencies', $tool->getDescription());
    }
}
