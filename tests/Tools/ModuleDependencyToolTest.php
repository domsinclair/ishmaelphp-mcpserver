<?php

declare(strict_types=1);

namespace Ishmael\McpServer\Tests\Tools;

use Ishmael\McpServer\Project\ProjectContext;
use Ishmael\McpServer\Tools\ModuleDependencyTool;
use PHPUnit\Framework\TestCase;

final class ModuleDependencyToolTest extends TestCase
{
    private string $tempRoot;

    protected function setUp(): void
    {
        $this->tempRoot = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'ish_mcp_tool_dep_' . uniqid();
        mkdir($this->tempRoot, 0777, true);
        mkdir($this->tempRoot . DIRECTORY_SEPARATOR . 'Modules', 0777, true);
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

    public function testExecuteReturnsGraph(): void
    {
        $this->createModule('A', ['dependencies' => ['B']]);
        $this->createModule('B', []);

        $context = new ProjectContext($this->tempRoot, null, []);
        $tool = new ModuleDependencyTool($context);
        $result = $tool->execute([]);

        $this->assertArrayHasKey('nodes', $result);
        $this->assertArrayHasKey('edges', $result);
        $this->assertCount(2, $result['nodes']);
        $this->assertCount(1, $result['edges']);
        
        $this->assertEquals('A', $result['edges'][0]['from']);
        $this->assertEquals('B', $result['edges'][0]['to']);
    }

    private function createModule(string $name, array $manifest): void
    {
        $path = $this->tempRoot . DIRECTORY_SEPARATOR . 'Modules' . DIRECTORY_SEPARATOR . $name;
        mkdir($path, 0777, true);
        
        $content = "<?php\nreturn " . var_export($manifest, true) . ";";
        file_put_contents($path . DIRECTORY_SEPARATOR . 'module.php', $content);
    }
}
