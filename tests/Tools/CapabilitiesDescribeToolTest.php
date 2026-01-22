<?php

declare(strict_types=1);

namespace Ishmael\McpServer\Tests\Tools;

use Ishmael\McpServer\Project\ProjectContext;
use Ishmael\McpServer\Tools\CapabilitiesDescribeTool;
use PHPUnit\Framework\TestCase;

class CapabilitiesDescribeToolTest extends TestCase
{
    public function testMetadata(): void
    {
        $context = $this->createMock(ProjectContext::class);
        $tool = new CapabilitiesDescribeTool($context);

        $this->assertEquals('ish:capabilities:describe', $tool->getName());
        $this->assertStringContainsString('Inspect a module\'s capabilities', $tool->getDescription());
        
        $schema = $tool->getInputSchema();
        $this->assertArrayHasKey('module', $schema['properties']);
    }

    public function testExecuteAgainstReferenceApp(): void
    {
        $referenceAppRoot = 'D:\JetBrainsProjects\PhpStorm\my-app';
        if (!is_dir($referenceAppRoot)) {
            $this->markTestSkipped('Reference app not found at ' . $referenceAppRoot);
        }

        $context = ProjectContext::discover($referenceAppRoot);
        $tool = new CapabilitiesDescribeTool($context);

        // Try to describe a known module in the starter app, e.g., 'Home'
        $result = $tool->execute(['module' => 'Home']);

        // Even if the CLI command doesn't exist yet in the reference app's vendor,
        // the tool should handle it gracefully or report what happened.
        $this->assertIsArray($result);
        $this->assertArrayHasKey('success', $result);
    }
}
