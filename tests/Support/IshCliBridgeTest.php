<?php

declare(strict_types=1);

namespace Ishmael\McpServer\Tests\Support;

use Ishmael\McpServer\Project\ProjectContext;
use Ishmael\McpServer\Support\IshCliBridge;
use PHPUnit\Framework\TestCase;

final class IshCliBridgeTest extends TestCase
{
    private string $tempRoot;
    private string $binDir;
    private string $ishPath;

    protected function setUp(): void
    {
        $this->tempRoot = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'ish_bridge_test_' . uniqid();
        $this->binDir = $this->tempRoot . DIRECTORY_SEPARATOR . 'bin';
        mkdir($this->binDir, 0777, true);
        $this->ishPath = $this->binDir . DIRECTORY_SEPARATOR . 'ish';
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

    public function testExecuteReturnsSuccessAndParsesCreatedFiles(): void
    {
        $script = <<<'PHP'
<?php
echo "Module scaffolded at: /tmp/Modules/Test\n";
echo "Created: /tmp/Modules/Test/routes.php\n";
echo "Created: /tmp/Modules/Test/Controllers/TestController.php (discoverable)\n";
exit(0);
PHP;
        file_put_contents($this->ishPath, $script);

        // Mock created files so file_exists returns true in the bridge
        $base = $this->tempRoot . DIRECTORY_SEPARATOR . 'Modules' . DIRECTORY_SEPARATOR . 'Test';
        mkdir($base . DIRECTORY_SEPARATOR . 'Controllers', 0777, true);
        $routesFile = $base . DIRECTORY_SEPARATOR . 'routes.php';
        $controllerFile = $base . DIRECTORY_SEPARATOR . 'Controllers' . DIRECTORY_SEPARATOR . 'TestController.php';
        file_put_contents($routesFile, '');
        file_put_contents($controllerFile, '');

        // Update script with real paths
        $script = '<?php ' .
                  'echo "Module scaffolded at: ' . addslashes($base) . '\n"; ' .
                  'echo "Created: ' . addslashes($routesFile) . '\n"; ' .
                  'echo "Created: ' . addslashes($controllerFile) . ' (discoverable)\n"; ' .
                  'exit(0);';
        file_put_contents($this->ishPath, $script);

        $context = new ProjectContext($this->tempRoot, null, ['ish' => $this->ishPath]);
        $bridge = new IshCliBridge($context);
        $result = $bridge->execute('make:module', ['name' => 'Test']);

        // Debug: print output if it fails
        if (count($result['files']) !== 3) {
            echo "Output: " . $result['output'] . "\n";
            echo "Files: " . print_r($result['files'], true) . "\n";
        }

        $this->assertTrue($result['success']);
        $this->assertCount(3, $result['files']);
        $this->assertContains(realpath($base), $result['files']);
        $this->assertContains(realpath($routesFile), $result['files']);
        $this->assertContains(realpath($controllerFile), $result['files']);
    }

    public function testExecuteParsesVariousCreatedPatterns(): void
    {
        $base = $this->tempRoot . DIRECTORY_SEPARATOR . 'Modules' . DIRECTORY_SEPARATOR . 'Blog';
        mkdir($base . DIRECTORY_SEPARATOR . 'Controllers', 0777, true);
        $ctrl = $base . DIRECTORY_SEPARATOR . 'Controllers' . DIRECTORY_SEPARATOR . 'PostController.php';
        file_put_contents($ctrl, '');

        $script = '<?php ' .
            'echo "Controller created at: ' . addslashes($ctrl) . '\n"; ' .
            'echo "Resource scaffolded: Blog/Post\n"; ' .
            'exit(0);';
        file_put_contents($this->ishPath, $script);

        $context = new ProjectContext($this->tempRoot, null, ['ish' => $this->ishPath]);
        $bridge = new IshCliBridge($context);
        $result = $bridge->execute('make:controller');

        $this->assertTrue($result['success']);
        $this->assertCount(2, $result['files']);
        $this->assertContains(realpath($ctrl), $result['files']);
        $this->assertContains(realpath($base), $result['files']);
    }

    public function testExecuteReturnsFailureOnError(): void
    {
        $script = <<<'PHP'
<?php
fwrite(STDERR, "Error: something went wrong\n");
exit(1);
PHP;
        file_put_contents($this->ishPath, $script);

        $context = new ProjectContext($this->tempRoot, null, ['ish' => $this->ishPath]);
        $bridge = new IshCliBridge($context);
        $result = $bridge->execute('invalid:command');

        $this->assertFalse($result['success']);
        $this->assertEquals('Error: something went wrong', $result['error']);
    }

    public function testExecuteHandlesMissingBinary(): void
    {
        $context = new ProjectContext($this->tempRoot, null, []);
        $bridge = new IshCliBridge($context);
        $result = $bridge->execute('any');

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('ish binary not found', $result['error']);
    }
}
