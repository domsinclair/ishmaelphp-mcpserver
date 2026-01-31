<?php

declare(strict_types=1);

namespace Ishmael\McpServer\Tests\Providers;

use Ishmael\McpServer\Providers\FrameworkMapResourceProvider;
use PHPUnit\Framework\TestCase;

final class FrameworkMapResourceProviderTest extends TestCase
{
    private string $tempFile;

    protected function setUp(): void
    {
        $this->tempFile = tempnam(sys_get_temp_dir(), 'framework-map');
        file_put_contents($this->tempFile, '# Test Framework Map');
    }

    protected function tearDown(): void
    {
        if (file_exists($this->tempFile)) {
            unlink($this->tempFile);
        }
    }

    public function testListResources(): void
    {
        $provider = new FrameworkMapResourceProvider($this->tempFile);
        $resources = $provider->listResources();

        $this->assertCount(1, $resources);
        $this->assertEquals('ish://docs/framework-map', $resources[0]['uri']);
        $this->assertEquals('Ishmael Framework Map', $resources[0]['name']);
        $this->assertEquals('text/markdown', $resources[0]['mimeType']);
    }

    public function testReadResource(): void
    {
        $provider = new FrameworkMapResourceProvider($this->tempFile);
        $content = $provider->readResource('ish://docs/framework-map');

        $this->assertEquals('# Test Framework Map', $content);
    }

    public function testReadResourceReturnsNullForInvalidUri(): void
    {
        $provider = new FrameworkMapResourceProvider($this->tempFile);
        $content = $provider->readResource('ish://docs/invalid');

        $this->assertNull($content);
    }
}
