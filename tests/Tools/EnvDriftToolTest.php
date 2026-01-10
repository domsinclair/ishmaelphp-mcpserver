<?php

declare(strict_types=1);

namespace Ishmael\McpServer\Tests\Tools;

use Ishmael\McpServer\Project\ProjectContext;
use Ishmael\McpServer\Tools\EnvDriftTool;
use PHPUnit\Framework\TestCase;

final class EnvDriftToolTest extends TestCase
{
    private string $tempRoot;

    protected function setUp(): void
    {
        $this->tempRoot = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'ish_mcp_env_drift_test_' . uniqid();
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

    public function testDetectsDriftWhenKeysAreMissingInEnv(): void
    {
        file_put_contents($this->tempRoot . DIRECTORY_SEPARATOR . '.env', "KEY1=val1\n");
        file_put_contents($this->tempRoot . DIRECTORY_SEPARATOR . '.env.example', "KEY1=val1\nKEY2=val2\n");

        $context = new ProjectContext($this->tempRoot, null, []);
        $tool = new EnvDriftTool($context);
        $result = $tool->execute([]);

        $this->assertTrue($result['drift_detected']);
        $this->assertContains('KEY2', $result['missing_in_env']);
        $this->assertEmpty($result['missing_in_example']);
    }

    public function testDetectsDriftWhenKeysAreMissingInExample(): void
    {
        file_put_contents($this->tempRoot . DIRECTORY_SEPARATOR . '.env', "KEY1=val1\nKEY2=val2\n");
        file_put_contents($this->tempRoot . DIRECTORY_SEPARATOR . '.env.example', "KEY1=val1\n");

        $context = new ProjectContext($this->tempRoot, null, []);
        $tool = new EnvDriftTool($context);
        $result = $tool->execute([]);

        $this->assertTrue($result['drift_detected']);
        $this->assertEmpty($result['missing_in_env']);
        $this->assertContains('KEY2', $result['missing_in_example']);
    }

    public function testNoDriftWhenFilesMatch(): void
    {
        file_put_contents($this->tempRoot . DIRECTORY_SEPARATOR . '.env', "KEY1=val1\n# comment\nKEY2=val2\n");
        file_put_contents($this->tempRoot . DIRECTORY_SEPARATOR . '.env.example', "KEY1=\nKEY2=\n");

        $context = new ProjectContext($this->tempRoot, null, []);
        $tool = new EnvDriftTool($context);
        $result = $tool->execute([]);

        $this->assertFalse($result['drift_detected']);
        $this->assertEmpty($result['missing_in_env']);
        $this->assertEmpty($result['missing_in_example']);
    }
}
