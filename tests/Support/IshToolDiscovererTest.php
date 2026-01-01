<?php

declare(strict_types=1);

namespace Ishmael\McpServer\Tests\Support;

use Ishmael\McpServer\Project\ProjectContext;
use Ishmael\McpServer\Support\IshToolDiscoverer;
use PHPUnit\Framework\TestCase;

final class IshToolDiscovererTest extends TestCase
{
    private string $tempRoot;

    protected function setUp(): void
    {
        $this->tempRoot = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'ish_discoverer_test_' . uniqid();
        mkdir($this->tempRoot, 0777, true);
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->tempRoot);
    }

    private function removeDirectory(string $dir): void
    {
        if (!is_dir($dir)) return;
        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            (is_dir("$dir/$file")) ? $this->removeDirectory("$dir/$file") : unlink("$dir/$file");
        }
        rmdir($dir);
    }

    public function testDiscoverReturnsToolsFromMetadata(): void
    {
        $storageDir = $this->tempRoot . DIRECTORY_SEPARATOR . 'storage';
        mkdir($storageDir, 0777, true);
        
        $metadata = [
            'generatedAt' => date('c'),
            'commands' => [
                [
                    'name' => 'custom:command',
                    'description' => 'A custom command',
                    'options' => [
                        ['name' => '--force', 'description' => 'Force action'],
                        ['name' => '--name', 'description' => 'Set name', 'accepts' => 'STRING'],
                    ],
                ],
            ],
        ];
        file_put_contents($storageDir . DIRECTORY_SEPARATOR . 'cli_commands.json', json_encode($metadata));

        $context = new ProjectContext($this->tempRoot, null, []);
        $discoverer = new IshToolDiscoverer($context);
        $tools = $discoverer->discover();

        $this->assertCount(1, $tools);
        $this->assertEquals('ish:custom:command', $tools[0]->getName());
        $this->assertEquals('A custom command', $tools[0]->getDescription());
        
        $schema = $tools[0]->getInputSchema();
        $this->assertArrayHasKey('force', $schema['properties']);
        $this->assertEquals('boolean', $schema['properties']['force']['type']);
        $this->assertArrayHasKey('name', $schema['properties']);
        $this->assertEquals('string', $schema['properties']['name']['type']);
    }

    public function testDiscoverSkipsDedicatedCommands(): void
    {
        $storageDir = $this->tempRoot . DIRECTORY_SEPARATOR . 'storage';
        mkdir($storageDir, 0777, true);
        
        $metadata = [
            'generatedAt' => date('c'),
            'commands' => [
                [
                    'name' => 'make:module',
                    'description' => 'Should be skipped',
                ],
                [
                    'name' => 'custom:command',
                    'description' => 'Should be included',
                ],
            ],
        ];
        file_put_contents($storageDir . DIRECTORY_SEPARATOR . 'cli_commands.json', json_encode($metadata));

        $context = new ProjectContext($this->tempRoot, null, []);
        $discoverer = new IshToolDiscoverer($context);
        $tools = $discoverer->discover();

        $this->assertCount(1, $tools);
        $this->assertEquals('ish:custom:command', $tools[0]->getName());
    }

    public function testDiscoverReturnsEmptyWhenNoMetadata(): void
    {
        $context = new ProjectContext($this->tempRoot, null, []);
        $discoverer = new IshToolDiscoverer($context);
        $tools = $discoverer->discover();

        $this->assertEmpty($tools);
    }
}
