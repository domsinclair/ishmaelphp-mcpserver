<?php

declare(strict_types=1);

namespace Ishmael\McpServer\Tests\Support;

use Ishmael\McpServer\Project\ProjectContext;
use Ishmael\McpServer\Support\ModuleDependencyMapper;
use PHPUnit\Framework\TestCase;

final class ModuleDependencyMapperTest extends TestCase
{
    private string $tempRoot;

    protected function setUp(): void
    {
        $this->tempRoot = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'ish_mcp_dep_test_' . uniqid();
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

    public function testMapDiscoversExplicitDependencies(): void
    {
        $this->createModule('Auth', [
            'dependencies' => ['Core', 'Database']
        ]);
        $this->createModule('Core', []);
        $this->createModule('Database', []);

        $context = new ProjectContext($this->tempRoot, null, []);
        $mapper = new ModuleDependencyMapper($context);
        $result = $mapper->map();

        $this->assertCount(3, $result['nodes']);
        
        $authEdges = array_filter($result['edges'], fn($e) => $e['from'] === 'Auth');
        $this->assertCount(2, $authEdges);
        
        $targets = array_column($authEdges, 'to');
        $this->assertContains('Core', $targets);
        $this->assertContains('Database', $targets);
        $this->assertEquals('explicit', $authEdges[array_search('Core', $targets)]['type']);
    }

    public function testMapInfersServiceDependencies(): void
    {
        $this->createModule('User', [
            'services' => [
                'user_repo' => 'Modules\User\Repositories\UserRepository',
                'external_logger' => 'Modules\Log\Services\CloudLogger'
            ]
        ]);
        $this->createModule('Log', []);

        $context = new ProjectContext($this->tempRoot, null, []);
        $mapper = new ModuleDependencyMapper($context);
        $result = $mapper->map();

        $userEdges = array_filter($result['edges'], fn($e) => $e['from'] === 'User');
        $this->assertCount(1, $userEdges);
        
        $edge = reset($userEdges);
        $this->assertEquals('Log', $edge['to']);
        $this->assertEquals('service', $edge['type']);
    }

    public function testMapFlagsGodModules(): void
    {
        $this->createModule('God', [
            'dependencies' => ['A', 'B', 'C', 'D', 'E', 'F']
        ]);
        foreach (['A', 'B', 'C', 'D', 'E', 'F'] as $m) {
            $this->createModule($m, []);
        }

        $context = new ProjectContext($this->tempRoot, null, []);
        $mapper = new ModuleDependencyMapper($context);
        $result = $mapper->map();

        $godNode = null;
        foreach ($result['nodes'] as $node) {
            if ($node['id'] === 'God') {
                $godNode = $node;
                break;
            }
        }

        $this->assertNotNull($godNode);
        $this->assertTrue($godNode['architecture']['is_god_module']);
        $this->assertStringContainsString('splitting', $godNode['architecture']['suggestion']);
    }

    private function createModule(string $name, array $manifest): void
    {
        $path = $this->tempRoot . DIRECTORY_SEPARATOR . 'Modules' . DIRECTORY_SEPARATOR . $name;
        mkdir($path, 0777, true);
        
        $content = "<?php\nreturn " . var_export($manifest, true) . ";";
        file_put_contents($path . DIRECTORY_SEPARATOR . 'module.php', $content);
    }
}
