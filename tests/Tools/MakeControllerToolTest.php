<?php

declare(strict_types=1);

namespace Ishmael\McpServer\Tests\Tools;

use Ishmael\McpServer\Project\ProjectContext;
use Ishmael\McpServer\Tools\MakeControllerTool;
use PHPUnit\Framework\TestCase;

final class MakeControllerToolTest extends TestCase
{
    public function testGetInputSchemaIncludesApiFlag(): void
    {
        $context = $this->createMock(ProjectContext::class);
        $tool = new MakeControllerTool($context);
        $schema = $tool->getInputSchema();

        $this->assertArrayHasKey('api', $schema['properties']);
        $this->assertEquals('boolean', $schema['properties']['api']['type']);
        $this->assertStringContainsString('API controller', $schema['properties']['api']['description']);
    }

    public function testGetName(): void
    {
        $context = $this->createMock(ProjectContext::class);
        $tool = new MakeControllerTool($context);
        $this->assertEquals('ish:make:controller', $tool->getName());
    }
}
