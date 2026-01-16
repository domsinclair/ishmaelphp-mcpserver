<?php

declare(strict_types=1);

namespace Ishmael\McpServer\Tests\Providers;

use PHPUnit\Framework\TestCase;
use Ishmael\McpServer\Providers\DocsResourceProvider;
use Ishmael\McpServer\Project\PathSandbox;

class DocsResourceProviderTest extends TestCase
{
    private string $tempDir;
    private PathSandbox $sandbox;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'ish_mcp_test_' . uniqid();
        mkdir($this->tempDir, 0777, true);
        mkdir($this->tempDir . DIRECTORY_SEPARATOR . 'Docs', 0777, true);
        mkdir($this->tempDir . DIRECTORY_SEPARATOR . 'Modules' . DIRECTORY_SEPARATOR . 'Upload' . DIRECTORY_SEPARATOR . 'Docs', 0777, true);
        
        file_put_contents($this->tempDir . DIRECTORY_SEPARATOR . 'Docs' . DIRECTORY_SEPARATOR . 'main.md', '# Main Doc');
        file_put_contents($this->tempDir . DIRECTORY_SEPARATOR . 'Modules' . DIRECTORY_SEPARATOR . 'Upload' . DIRECTORY_SEPARATOR . 'Docs' . DIRECTORY_SEPARATOR . 'index.md', '# Upload Index');
        file_put_contents($this->tempDir . DIRECTORY_SEPARATOR . 'Modules' . DIRECTORY_SEPARATOR . 'Upload' . DIRECTORY_SEPARATOR . 'Docs' . DIRECTORY_SEPARATOR . 'setup.md', '# Upload Setup');

        $this->sandbox = new PathSandbox($this->tempDir);
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->tempDir);
    }

    private function removeDirectory(string $path): void
    {
        $files = array_diff(scandir($path), ['.', '..']);
        foreach ($files as $file) {
            (is_dir("$path/$file")) ? $this->removeDirectory("$path/$file") : unlink("$path/$file");
        }
        rmdir($path);
    }

    public function testListResourcesIncludesModuleDocs(): void
    {
        $provider = new DocsResourceProvider($this->sandbox, [
            $this->tempDir . DIRECTORY_SEPARATOR . 'Docs',
            $this->tempDir . DIRECTORY_SEPARATOR . 'Modules',
        ]);

        $resources = $provider->listResources();
        
        $uris = array_column($resources, 'uri');
        
        $this->assertContains('docs:main', $uris);
        $this->assertContains('docs:feature-packs/upload/index', $uris);
        $this->assertContains('docs:feature-packs/upload/setup', $uris);
    }

    public function testGenerateDocsIndexIncludesFeaturePacksCategory(): void
    {
        $provider = new DocsResourceProvider($this->sandbox, [
            $this->tempDir . DIRECTORY_SEPARATOR . 'Docs',
            $this->tempDir . DIRECTORY_SEPARATOR . 'Modules',
        ]);

        $json = $provider->readResource('ish://docs/index');
        $this->assertNotNull($json);
        
        $index = json_decode($json, true);
        
        $this->assertArrayHasKey('feature-packs', $index);
        $this->assertCount(2, $index['feature-packs']);
        
        $found = false;
        foreach ($index['feature-packs'] as $item) {
            if ($item['uri'] === 'docs:feature-packs/upload/index') {
                $found = true;
                $this->assertEquals('index.md', $item['name']);
            }
        }
        $this->assertTrue($found, 'Could not find docs:feature-packs/upload/index in index');
    }
}
