<?php

declare(strict_types=1);

namespace Ishmael\McpServer\Tests\Tools;

use Ishmael\McpServer\Project\ProjectContext;
use Ishmael\McpServer\Tools\DynamicIshTool;
use PHPUnit\Framework\TestCase;

final class DynamicIshToolTest extends TestCase
{
    public function testGetInputSchemaWithRequiredOptionsAndArguments(): void
    {
        $context = new ProjectContext('/tmp', null, []);
        $metadata = [
            'name' => 'test:cmd',
            'description' => 'Test command',
            'options' => [
                ['name' => '--opt1', 'description' => 'Opt 1', 'optional' => false],
                ['name' => '--opt2', 'description' => 'Opt 2', 'accepts' => 'STRING', 'optional' => true],
            ],
            'arguments' => [
                ['name' => 'arg1', 'description' => 'Arg 1', 'optional' => false],
                ['name' => 'arg2', 'description' => 'Arg 2', 'optional' => true],
            ],
        ];

        $tool = new DynamicIshTool($context, $metadata);
        $schema = $tool->getInputSchema();

        $this->assertEquals('object', $schema['type']);
        $this->assertArrayHasKey('opt1', $schema['properties']);
        $this->assertEquals('boolean', $schema['properties']['opt1']['type']);
        $this->assertArrayHasKey('opt2', $schema['properties']);
        $this->assertEquals('string', $schema['properties']['opt2']['type']);
        $this->assertArrayHasKey('arg1', $schema['properties']);
        $this->assertEquals('string', $schema['properties']['arg1']['type']);
        $this->assertArrayHasKey('arg2', $schema['properties']);
        $this->assertEquals('string', $schema['properties']['arg2']['type']);

        $this->assertContains('opt1', $schema['required']);
        $this->assertContains('arg1', $schema['required']);
        $this->assertNotContains('opt2', $schema['required']);
        $this->assertNotContains('arg2', $schema['required']);
    }
}
