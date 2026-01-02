<?php

declare(strict_types=1);

namespace Ishmael\McpServer\Tests\Providers;

use Ishmael\McpServer\Project\PathSandbox;
use Ishmael\McpServer\Project\ProjectContext;
use Ishmael\McpServer\Providers\IshStubsResourceProvider;
use PHPUnit\Framework\TestCase;

final class IshStubsResourceProviderTest extends TestCase
{
    private string $tempRoot;

    protected function setUp(): void
    {
        $this->tempRoot = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'ish_stubs_test_' . uniqid();
        mkdir($this->tempRoot, 0777, true);
        mkdir($this->tempRoot . '/config', 0777, true);
        mkdir($this->tempRoot . '/vendor', 0777, true);

        // Mock config
        file_put_contents($this->tempRoot . '/config/app.php', '<?php return ["name" => "Test App", "nested" => ["key" => "val"]];');
        
        // Mock database config for SQLite
        file_put_contents($this->tempRoot . '/config/database.php', '<?php return ["driver" => "sqlite", "database" => ":memory:"];');

        // Mock Ishmael Core if needed (StubDataCollector will try to load it)
        if (!class_exists('Ishmael\Core\Database')) {
            eval('namespace Ishmael\Core { 
                class Database { 
                    public static function init($cfg) {} 
                    public static function adapter() { return new class { }; }
                    public static function query($q, $p) { 
                        return new class { 
                            public function fetchAll() { 
                                return [["name" => "users"], ["name" => "posts"]]; 
                            } 
                        }; 
                    }
                } 
            }');
        }
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

    public function testListsDynamicStubs(): void
    {
        $sandbox = new PathSandbox($this->tempRoot);
        $context = new ProjectContext($this->tempRoot, $sandbox, []);
        $provider = new IshStubsResourceProvider($sandbox, $this->tempRoot, $context);

        $resources = $provider->listResources();
        
        $uris = array_column($resources, 'uri');
        $this->assertContains('ish://stubs/Project/Tables.php', $uris);
        $this->assertContains('ish://stubs/Project/Config.php', $uris);
    }

    public function testGeneratesTablesStub(): void
    {
        $sandbox = new PathSandbox($this->tempRoot);
        $context = new ProjectContext($this->tempRoot, $sandbox, []);
        $provider = new IshStubsResourceProvider($sandbox, $this->tempRoot, $context);

        $content = $provider->readResource('ish://stubs/Project/Tables.php');
        
        $this->assertStringContainsString('class Tables', $content);
        $this->assertStringContainsString("public const USERS = 'users';", $content);
        $this->assertStringContainsString("public const POSTS = 'posts';", $content);
    }

    public function testGeneratesConfigStub(): void
    {
        $sandbox = new PathSandbox($this->tempRoot);
        $context = new ProjectContext($this->tempRoot, $sandbox, []);
        $provider = new IshStubsResourceProvider($sandbox, $this->tempRoot, $context);

        $content = $provider->readResource('ish://stubs/Project/Config.php');
        
        $this->assertStringContainsString('class ConfigKeys', $content);
        $this->assertStringContainsString("public const APP_NAME = 'app.name';", $content);
        $this->assertStringContainsString("public const APP_NESTED_KEY = 'app.nested.key';", $content);
    }
}
