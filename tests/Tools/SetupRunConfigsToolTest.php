<?php

declare(strict_types=1);

namespace Ishmael\McpServer\Tests\Tools;

use Ishmael\McpServer\Project\ProjectContext;
use Ishmael\McpServer\Tools\SetupRunConfigsTool;
use PHPUnit\Framework\TestCase;

class SetupRunConfigsToolTest extends TestCase
{
    private string $tempRoot;

    protected function setUp(): void
    {
        $this->tempRoot = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'ishmael_test_' . uniqid();
        mkdir($this->tempRoot, 0777, true);
        mkdir($this->tempRoot . DIRECTORY_SEPARATOR . 'bin', 0777, true);
        file_put_contents($this->tempRoot . DIRECTORY_SEPARATOR . 'bin' . DIRECTORY_SEPARATOR . 'ish', '');
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->tempRoot);
    }

    private function removeDirectory(string $path): void
    {
        $files = array_diff(scandir($path), ['.', '..']);
        foreach ($files as $file) {
            $fullPath = $path . DIRECTORY_SEPARATOR . $file;
            is_dir($fullPath) ? $this->removeDirectory($fullPath) : unlink($fullPath);
        }
        rmdir($path);
    }

    public function testSetupVSCode(): void
    {
        $context = $this->createMock(ProjectContext::class);
        $context->method('getRoot')->willReturn($this->tempRoot);
        $context->method('getIshBinary')->willReturn($this->tempRoot . DIRECTORY_SEPARATOR . 'bin' . DIRECTORY_SEPARATOR . 'ish');

        $tool = new SetupRunConfigsTool($context);
        $result = $tool->execute(['ide' => 'vscode']);

        $this->assertTrue($result['success']);
        $this->assertFileExists($this->tempRoot . DIRECTORY_SEPARATOR . '.vscode' . DIRECTORY_SEPARATOR . 'launch.json');
        $this->assertFileExists($this->tempRoot . DIRECTORY_SEPARATOR . '.vscode' . DIRECTORY_SEPARATOR . 'tasks.json');
        $this->assertFileExists($this->tempRoot . DIRECTORY_SEPARATOR . '.vscode' . DIRECTORY_SEPARATOR . 'extensions.json');
        $this->assertFileExists($this->tempRoot . DIRECTORY_SEPARATOR . 'jetbrains-mcp.json');

        // Verify jetbrains-mcp.json
        $mcpJson = json_decode(file_get_contents($this->tempRoot . DIRECTORY_SEPARATOR . 'jetbrains-mcp.json'), true);
        $this->assertArrayHasKey('mcpServers', $mcpJson);
        $this->assertArrayHasKey('ishmael', $mcpJson['mcpServers']);

        // Verify extensions.json
        $extensionsJson = json_decode(file_get_contents($this->tempRoot . DIRECTORY_SEPARATOR . '.vscode' . DIRECTORY_SEPARATOR . 'extensions.json'), true);
        $this->assertContains('xdebug.php-debug', $extensionsJson['recommendations']);

        // Verify launch.json
        $launchJson = json_decode(file_get_contents($this->tempRoot . DIRECTORY_SEPARATOR . '.vscode' . DIRECTORY_SEPARATOR . 'launch.json'), true);
        $this->assertEquals('0.2.0', $launchJson['version']);
        $this->assertCount(3, $launchJson['configurations']);
        $this->assertEquals('Ish: Help', $launchJson['configurations'][0]['name']);
        $this->assertEquals('${workspaceFolder}/bin/ish', $launchJson['configurations'][0]['program']);

        // Verify tasks.json
        $tasksJson = json_decode(file_get_contents($this->tempRoot . DIRECTORY_SEPARATOR . '.vscode' . DIRECTORY_SEPARATOR . 'tasks.json'), true);
        $this->assertEquals('2.0.0', $tasksJson['version']);
        $this->assertCount(3, $tasksJson['tasks']);
        $this->assertEquals('Ish: Migrate', $tasksJson['tasks'][0]['label']);
        $this->assertEquals('php bin/ish migrate', $tasksJson['tasks'][0]['command']);
    }

    public function testSetupVSCodeOverwrite(): void
    {
        $context = $this->createMock(ProjectContext::class);
        $context->method('getRoot')->willReturn($this->tempRoot);
        $context->method('getIshBinary')->willReturn($this->tempRoot . DIRECTORY_SEPARATOR . 'bin' . DIRECTORY_SEPARATOR . 'ish');

        mkdir($this->tempRoot . DIRECTORY_SEPARATOR . '.vscode', 0777, true);
        $initialJson = [
            'version' => '0.2.0',
            'configurations' => [
                [
                    'name' => 'Ish: Help',
                    'type' => 'php',
                    'request' => 'launch',
                    'program' => 'OLD_PATH',
                    'args' => ['help']
                ]
            ]
        ];
        file_put_contents($this->tempRoot . DIRECTORY_SEPARATOR . '.vscode' . DIRECTORY_SEPARATOR . 'launch.json', json_encode($initialJson));

        $tool = new SetupRunConfigsTool($context);
        
        // Without overwrite
        $result = $tool->execute(['ide' => 'vscode', 'overwrite' => false]);
        $json = json_decode(file_get_contents($this->tempRoot . DIRECTORY_SEPARATOR . '.vscode' . DIRECTORY_SEPARATOR . 'launch.json'), true);
        $this->assertEquals('OLD_PATH', $json['configurations'][0]['program']);

        // With overwrite
        $result = $tool->execute(['ide' => 'vscode', 'overwrite' => true]);
        $json = json_decode(file_get_contents($this->tempRoot . DIRECTORY_SEPARATOR . '.vscode' . DIRECTORY_SEPARATOR . 'launch.json'), true);
        $this->assertEquals('${workspaceFolder}/bin/ish', $json['configurations'][0]['program']);
    }
}
