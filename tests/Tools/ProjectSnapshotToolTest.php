<?php

declare(strict_types=1);

namespace Ishmael\McpServer\Tests\Tools;

use Ishmael\McpServer\Project\ProjectContext;
use Ishmael\McpServer\Tools\ProjectSnapshotTool;
use PHPUnit\Framework\TestCase;

final class ProjectSnapshotToolTest extends TestCase
{
    private string $tempRoot;

    protected function setUp(): void
    {
        $this->tempRoot = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'ish_mcp_snapshot_test_' . uniqid();
        mkdir($this->tempRoot, 0777, true);
        mkdir($this->tempRoot . DIRECTORY_SEPARATOR . 'storage', 0777, true);
        mkdir($this->tempRoot . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'logs', 0777, true);
        file_put_contents($this->tempRoot . DIRECTORY_SEPARATOR . '.env', "APP_KEY=secret\n");
        file_put_contents($this->tempRoot . DIRECTORY_SEPARATOR . '.env.example', "APP_KEY=\nDB_DATABASE=\n");
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

    public function testExecuteReturnsFullSnapshot(): void
    {
        $logFile = $this->tempRoot . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'logs' . DIRECTORY_SEPARATOR . 'app.log';
        // Match the pattern: #0 /path/to/file.php(123): function()
        file_put_contents($logFile, "[2026-01-10T14:00:00] app.ERROR: Critical failure\n#0 " . $this->tempRoot . DIRECTORY_SEPARATOR . "index.php(10): crash()\n");

        $context = new ProjectContext($this->tempRoot, null, ['ish' => 'bin/ish']);
        $tool = new ProjectSnapshotTool($context);

        $result = $tool->execute([]);

        $this->assertArrayHasKey('project', $result);
        $this->assertArrayHasKey('environment', $result);
        $this->assertArrayHasKey('logs', $result);

        $this->assertEquals($this->tempRoot, $result['project']['root']);
        
        $this->assertArrayHasKey('validate', $result['environment']);
        $this->assertArrayHasKey('drift', $result['environment']);

        $this->assertNotEmpty($result['logs']);
        $this->assertEquals("Critical failure", $result['logs'][0]['message']);
        $this->assertCount(1, $result['logs'][0]['stack_trace']);
        $this->assertEquals("index.php", $result['logs'][0]['stack_trace'][0]['file']);
    }
}
