<?php
declare(strict_types=1);

namespace Ishmael\McpServer\Tests\Tools;

use PHPUnit\Framework\TestCase;
use Ishmael\McpServer\Tools\FeaturePackPublishTool;
use Ishmael\McpServer\Project\ProjectContext;
use Ishmael\McpServer\Support\IshCliBridge;
use PHPUnit\Framework\MockObject\MockObject;

class FeaturePackPublishToolTest extends TestCase
{
    private MockObject $context;
    private MockObject $cli;
    private FeaturePackPublishTool $tool;
    private string $tempDir;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'publish_test_' . uniqid();
        mkdir($this->tempDir, 0777, true);

        $this->context = $this->createMock(ProjectContext::class);
        
        // Use a mock that disables browser opening and token listening by default
        $this->tool = $this->getMockBuilder(FeaturePackPublishTool::class)
            ->setConstructorArgs([$this->context])
            ->onlyMethods(['openBrowser', 'listenForToken', 'uploadToRegistry'])
            ->getMock();
        
        $this->tool->method('openBrowser');
        // Do not set defaults for others, we define them in tests that reach them.
        // If they are called without an expectation they return null by default.

        // Use reflection to inject the mocked CLI bridge
        $reflection = new \ReflectionObject($this->tool);
        while ($reflection && !$reflection->hasProperty('cli')) {
            $reflection = $reflection->getParentClass();
        }
        $this->cli = $this->createMock(IshCliBridge::class);
        if ($reflection) {
            $cliProperty = $reflection->getProperty('cli');
            $cliProperty->setAccessible(true);
            $cliProperty->setValue($this->tool, $this->cli);
        }
    }

    protected function tearDown(): void
    {
        if (is_dir($this->tempDir)) {
            $this->recursiveRmdir($this->tempDir);
        }
    }

    private function recursiveRmdir(string $dir): void
    {
        if (!file_exists($dir)) return;
        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            (is_dir("$dir/$file")) ? $this->recursiveRmdir("$dir/$file") : unlink("$dir/$file");
        }
        rmdir($dir);
    }

    public function testGetName(): void
    {
        $this->assertEquals('ish:featurePack:publish', $this->tool->getName());
    }

    public function testExecuteFailsWithoutRoot(): void
    {
        $this->context->method('getRoot')->willReturn(null);
        
        $result = $this->tool->execute(['module_name' => 'Blog']);
        
        $this->assertFalse($result['success']);
        $this->assertStringContainsString('Project root not found', $result['message']);
    }

    public function testExecuteFailsOnPackFailure(): void
    {
        $this->context->method('getRoot')->willReturn($this->tempDir);
        
        $this->cli->method('execute')
             ->willReturn(['success' => false, 'error' => 'Pack command failed']);
        
        $result = $this->tool->execute(['module_name' => 'Blog']);
        
        $this->assertFalse($result['success']);
        $this->assertStringContainsString('Failed to generate feature pack artifacts', $result['message']);
        $this->assertEquals('Pack command failed', $result['error']);
    }

    public function testExecuteFailsWhenRegistryJsonIsMissing(): void
    {
        $this->context->method('getRoot')->willReturn($this->tempDir);
        
        $this->cli->method('execute')
             ->willReturn(['success' => true]);
        
        // No registry.json file created in tempDir/dist/
        
        $result = $this->tool->execute(['module_name' => 'Blog']);
        
        $this->assertFalse($result['success']);
        $this->assertStringContainsString('registry.json was not generated', $result['message']);
    }

    public function testExecuteFailsWhenMandatoryFieldsAreMissingInRegistryJson(): void
    {
        $this->context->method('getRoot')->willReturn($this->tempDir);
        
        $distDir = $this->tempDir . DIRECTORY_SEPARATOR . 'dist';
        if (!is_dir($distDir)) mkdir($distDir, 0777, true);
        $registryJsonPath = $distDir . DIRECTORY_SEPARATOR . 'registry.json';

        $this->cli->method('execute')
             ->willReturnCallback(function() use ($registryJsonPath) {
                 file_put_contents($registryJsonPath, json_encode(['title' => 'Blog']));
                 return ['success' => true];
             });
        
        $result = $this->tool->execute(['module_name' => 'Blog']);
        
        $this->assertFalse($result['success']);
        $this->assertStringContainsString('Metadata verification failed', $result['message']);
        $this->assertStringContainsString('category', $result['message']);
    }

    public function testExecuteFailsOnAuthTimeout(): void
    {
        $this->context->method('getRoot')->willReturn($this->tempDir);
        
        $distDir = $this->tempDir . DIRECTORY_SEPARATOR . 'dist';
        if (!is_dir($distDir)) mkdir($distDir, 0777, true);
        $registryJsonPath = $distDir . DIRECTORY_SEPARATOR . 'registry.json';

        $this->cli->method('execute')
             ->willReturnCallback(function() use ($registryJsonPath) {
                 file_put_contents($registryJsonPath, json_encode([
                     'title' => 'Blog',
                     'category' => 'content',
                     'capabilities' => ['blog']
                 ]));
                 return ['success' => true];
             });

        $this->tool->expects($this->once())
             ->method('listenForToken')
             ->willReturn(null); // Simulate timeout
             
        $result = $this->tool->execute(['module_name' => 'Blog']);
        
        $this->assertFalse($result['success']);
        $this->assertStringContainsString('Authentication failed or timed out', $result['message']);
    }

    public function testExecuteSuccess(): void
    {
        $this->context->method('getRoot')->willReturn($this->tempDir);
        
        $distDir = $this->tempDir . DIRECTORY_SEPARATOR . 'dist';
        if (!is_dir($distDir)) mkdir($distDir, 0777, true);
        $registryJsonPath = $distDir . DIRECTORY_SEPARATOR . 'registry.json';
        $zipPath = $distDir . DIRECTORY_SEPARATOR . 'blog.zip';
        file_put_contents($zipPath, 'fake zip content');

        $this->cli->method('execute')
             ->willReturnCallback(function() use ($registryJsonPath) {
                 file_put_contents($registryJsonPath, json_encode([
                     'title' => 'Blog',
                     'category' => 'content',
                     'capabilities' => ['blog']
                 ]));
                 return ['success' => true];
             });

        $this->tool->method('listenForToken')
             ->willReturn('fake-token');
             
        $this->tool->method('uploadToRegistry')
             ->willReturn([
                 'success' => true,
                 'tier' => 'A',
                 'data' => ['id' => 1]
             ]);
             
        $result = $this->tool->execute(['module_name' => 'Blog']);
        
        $this->assertTrue($result['success']);
        $this->assertStringContainsString('Successfully published', $result['message']);
        $this->assertStringContainsString('Hardware Verified', $result['message']);
        $this->assertEquals('Hardware Verified', $result['status']);
        
        // Check cleanup
        $this->assertFileDoesNotExist($registryJsonPath);
        $this->assertFileDoesNotExist($zipPath);
    }
}
