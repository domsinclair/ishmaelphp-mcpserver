<?php

declare(strict_types=1);

namespace Ishmael\McpServer\Tests\Tools;

use PHPUnit\Framework\TestCase;
use Ishmael\McpServer\Tools\FeaturePackRegistryTool;
use Ishmael\McpServer\Project\ProjectContext;

class FeaturePackRegistryToolTest extends TestCase
{
    private string $tempFile;

    protected function setUp(): void
    {
        $this->tempFile = tempnam(sys_get_temp_dir(), 'registry_test');
    }

    protected function tearDown(): void
    {
        if (file_exists($this->tempFile)) {
            unlink($this->tempFile);
        }
    }

    public function testExecuteParsesJsonRegistry(): void
    {
        $json = json_encode([
            'jsonrpc' => '2.0',
            'id' => 1,
            'registryVersion' => '0.2',
            'result' => [
                'packs' => [
                    [
                        'name' => 'cms-lite',
                        'description' => 'Lightweight CMS features',
                        'version' => '1.2.0',
                        'license' => 'commercial',
                        'package' => 'acme-corp/cms-lite',
                        'vendor' => 'acme-corp',
                        'capabilities' => ['content', 'media'],
                        'download' => 'https://vtlsoftware.co.uk/packs/cms-lite-1.2.0.zip',
                        'tags' => [],
                        'category' => null
                    ]
                ]
            ]
        ]);

        file_put_contents($this->tempFile, $json);

        $fakeRoot = $this->createFakeProject(['registry_url' => str_replace('\\', '/', $this->tempFile)]);
        $context = $this->createMock(ProjectContext::class);
        $context->method('getRoot')->willReturn($fakeRoot);

        $tool = new FeaturePackRegistryTool($context);
        $result = $tool->execute([]);

        $this->assertArrayHasKey('features', $result);
        $this->assertCount(1, $result['features']);
        $this->assertEquals('cms-lite', $result['features'][0]['name']);
        $this->assertEquals('cms-lite', $result['features'][0]['title']);
        $this->assertEquals('Lightweight CMS features', $result['features'][0]['synopsis']);
        $this->assertEquals('acme-corp/cms-lite', $result['features'][0]['package']);
        $this->assertEquals('commercial', $result['features'][0]['tier']);
        $this->assertEquals('https://vtlsoftware.co.uk/packs/cms-lite-1.2.0.zip', $result['features'][0]['distribution']['url']);
        $this->assertEquals(['content', 'media'], $result['features'][0]['capabilities']);
        $this->assertEquals('1.2.0', $result['features'][0]['version']);
        $this->assertEquals('acme-corp', $result['features'][0]['author']['name']);

        $this->recursiveRmdir($fakeRoot);
    }

    public function testExecuteParsesXmlRegistryFallback(): void
    {
        $xml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<registry>
    <feature-pack>
        <id>blog</id>
        <vendor>example-author</vendor>
        <license>community</license>
        <version>1.1.0</version>
        <download>https://vtlsoftware.co.uk/packs/blog-1.1.0.zip</download>
        <capabilities>
            <capability id="content" />
        </capabilities>
    </feature-pack>
</registry>
XML;

        file_put_contents($this->tempFile, $xml);

        $fakeRoot = $this->createFakeProject(['registry_url' => str_replace('\\', '/', $this->tempFile)]);
        $context = $this->createMock(ProjectContext::class);
        $context->method('getRoot')->willReturn($fakeRoot);

        $tool = new FeaturePackRegistryTool($context);
        $result = $tool->execute([]);

        $this->assertArrayHasKey('features', $result);
        $this->assertCount(1, $result['features']);
        $this->assertEquals('blog', $result['features'][0]['name']);
        $this->assertEquals('blog', $result['features'][0]['title']);
        $this->assertEquals('', $result['features'][0]['synopsis']);
        $this->assertEquals('community', $result['features'][0]['tier']);
        $this->assertEquals(['content'], $result['features'][0]['capabilities']);
        $this->assertEquals('1.1.0', $result['features'][0]['version']);

        $this->recursiveRmdir($fakeRoot);
    }

    public function testExecuteReturnsErrorOnInvalidJson(): void
    {
        file_put_contents($this->tempFile, "not a json and not a xml");

        $fakeRoot = $this->createFakeProject(['registry_url' => str_replace('\\', '/', $this->tempFile)]);
        $context = $this->createMock(ProjectContext::class);
        $context->method('getRoot')->willReturn($fakeRoot);

        $tool = new FeaturePackRegistryTool($context);
        $result = $tool->execute([]);

        $this->assertArrayHasKey('error', $result);
        $this->assertStringContainsString('JSON Decode Error', $result['error']);
        $this->assertStringContainsString('Raw response starts with: not a json', $result['error']);

        $this->recursiveRmdir($fakeRoot);
    }

    public function testExecuteParsesJsonRegistryV04(): void
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
                        'description' => 'Lightweight CMS features',
                        'category' => 'content',
                        'license_type' => 'commercial',
                        'license_enforcement' => 'required',
                        'vendor' => [
                            'id' => 2,
                            'name' => 'acme-corp',
                            'email' => 'support@acme-corp.com',
                            'url' => 'https://acme-corp.com'
                        ],
                        'version' => '1.2.0',
                        'capabilities' => ['content', 'admin-ui'],
                        'download' => 'https://vtlsoftware.co.uk/packs/cms-lite-1.2.0.zip',
                        'tags' => [],
                        'score' => 10
                    ],
                    [
                        'slug' => 'cms-lite',
                        'title' => 'CMS Lite',
                        'description' => 'Lightweight CMS features',
                        'category' => 'content',
                        'license_type' => 'commercial',
                        'license_enforcement' => 'required',
                        'vendor' => [
                            'id' => 2,
                            'name' => 'acme-corp',
                            'email' => 'support@acme-corp.com',
                            'url' => 'https://acme-corp.com'
                        ],
                        'version' => '1.0.0',
                        'capabilities' => ['content', 'admin-ui'],
                        'download' => 'https://vtlsoftware.co.uk/packs/cms-lite-1.0.0.zip',
                        'tags' => [],
                        'score' => 5
                    ],
                    [
                        'slug' => 'analytics-lite',
                        'title' => 'Analytics Lite',
                        'description' => 'Basic metrics',
                        'category' => 'analytics',
                        'license_type' => 'community',
                        'license_enforcement' => 'none',
                        'vendor' => [
                            'id' => 3,
                            'name' => 'datawise',
                            'email' => 'contact@datawise.io',
                            'url' => 'https://datawise.io'
                        ],
                        'version' => '1.0.0',
                        'capabilities' => ['analytics'],
                        'download' => 'https://vtlsoftware.co.uk/packs/analytics-1.0.0.zip',
                        'tags' => [],
                        'score' => 50
                    ]
                ]
            ]
        ]);

        file_put_contents($this->tempFile, $json);

        $fakeRoot = $this->createFakeProject(['registry_url' => str_replace('\\', '/', $this->tempFile)]);
        $context = $this->createMock(ProjectContext::class);
        $context->method('getRoot')->willReturn($fakeRoot);

        $tool = new FeaturePackRegistryTool($context);
        // Pass some context to check URL building (though it's hard to verify here without mocking file_get_contents)
        $result = $tool->execute(['project_type' => 'blog', 'ui_required' => true]);

        $this->assertArrayHasKey('features', $result);
        $this->assertCount(3, $result['features']);
        
        // Verify sorting (highest score first)
        $this->assertEquals('analytics-lite', $result['features'][0]['name']);
        $this->assertEquals(50, $result['features'][0]['score']);
        
        $this->assertEquals('cms-lite', $result['features'][1]['name']);
        $this->assertEquals('1.2.0', $result['features'][1]['version']);

        $this->assertEquals('cms-lite', $result['features'][2]['name']);
        $this->assertEquals('1.0.0', $result['features'][2]['version']);

        $this->recursiveRmdir($fakeRoot);
    }

    public function testExecuteReturnsDetailedErrorOnConnectionFailure(): void
    {
        $fakeRoot = $this->createFakeProject(['registry_url' => 'http://non-existent-domain-vtl.test/registry.json']);
        $context = $this->createMock(ProjectContext::class);
        $context->method('getRoot')->willReturn($fakeRoot);

        $tool = new FeaturePackRegistryTool($context);
        $result = $tool->execute([]);

        $this->assertArrayHasKey('error', $result);
        $this->assertStringContainsString('Could not fetch registry', $result['error']);
        $this->assertStringContainsString('Resolved IP:', $result['error']);

        $this->recursiveRmdir($fakeRoot);
    }

    private function createFakeProject(array $config): string
    {
        $fakeRoot = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'ishmael_test_' . uniqid();
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
