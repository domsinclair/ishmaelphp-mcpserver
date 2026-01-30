<?php

declare(strict_types=1);

namespace Ishmael\McpServer\Tests\Tools;

use PHPUnit\Framework\TestCase;
use Ishmael\McpServer\Tools\FeaturePackListTool;
use Ishmael\McpServer\Project\ProjectContext;
use Ishmael\McpServer\Project\PathSandbox;

class FeaturePackListToolTest extends TestCase
{
    private string $tempDir;
    private string $tempRegistry;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'list_test_' . uniqid();
        mkdir($this->tempDir, 0777, true);
        $this->tempRegistry = tempnam(sys_get_temp_dir(), 'registry_test');
    }

    protected function tearDown(): void
    {
        $this->recursiveRmdir($this->tempDir);
        if (file_exists($this->tempRegistry)) {
            unlink($this->tempRegistry);
        }
    }

    public function testExecuteMergesRegistryWithAuthorInfo(): void
    {
        $json = json_encode([
            'jsonrpc' => '2.0',
            'id' => 1,
            'registryVersion' => '0.4',
            'result' => [
                'packs' => [
                    [
                        'slug' => 'cms-lite',
                        'title' => 'CMS Lite',
                        'description' => 'Lightweight CMS',
                        'category' => 'content',
                        'license_type' => 'commercial',
                        'license_enforcement' => 'required',
                        'vendor' => [
                            'name' => 'acme-corp',
                            'email' => 'support@acme-corp.com',
                            'url' => 'https://acme-corp.com'
                        ],
                        'version' => '1.2.0',
                        'capabilities' => ['content'],
                        'download' => 'https://example.com/cms.zip',
                        'score' => 10
                    ]
                ]
            ]
        ]);
        file_put_contents($this->tempRegistry, $json);

        $fakeRoot = $this->createFakeProject(['registry_url' => str_replace('\\', '/', $this->tempRegistry)]);
        $sandbox = new PathSandbox($fakeRoot);
        
        $context = $this->createMock(ProjectContext::class);
        $context->method('getRoot')->willReturn($fakeRoot);
        $context->method('getSandbox')->willReturn($sandbox);

        $tool = new FeaturePackListTool($context);
        $result = $tool->execute([]);

        $this->assertArrayHasKey('packs', $result);
        $found = false;
        foreach ($result['packs'] as $pack) {
            if ($pack['name'] === 'cms-lite') {
                $found = true;
                $this->assertEquals('acme-corp', $pack['author']['name']);
                $this->assertEquals('support@acme-corp.com', $pack['author']['email']);
                $this->assertEquals('https://acme-corp.com', $pack['author']['url']);
            }
        }
        $this->assertTrue($found, 'cms-lite pack not found in results');

        $this->recursiveRmdir($fakeRoot);
    }

    private function createFakeProject(array $config): string
    {
        $fakeRoot = $this->tempDir . DIRECTORY_SEPARATOR . 'project';
        mkdir($fakeRoot . DIRECTORY_SEPARATOR . 'config', 0777, true);
        
        $configContent = "<?php return " . var_export($config, true) . ";";
        file_put_contents($fakeRoot . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'app.php', $configContent);
        
        return $fakeRoot;
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
}
