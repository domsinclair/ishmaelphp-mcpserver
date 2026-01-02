<?php

declare(strict_types=1);

namespace Ishmael\McpServer\Tests\Providers;

use Ishmael\McpServer\Providers\IshAiManifestoResourceProvider;
use PHPUnit\Framework\TestCase;

final class IshAiManifestoResourceProviderTest extends TestCase
{
    private string $tempFile;

    protected function setUp(): void
    {
        $this->tempFile = tempnam(sys_get_temp_dir(), 'ai-manifesto');
        file_put_contents($this->tempFile, '# Test Manifesto');
    }

    protected function tearDown(): void
    {
        if (file_exists($this->tempFile)) {
            unlink($this->tempFile);
        }
    }

    public function testListResources(): void
    {
        $provider = new IshAiManifestoResourceProvider($this->tempFile);
        $resources = $provider->listResources();

        $this->assertCount(1, $resources);
        $this->assertEquals('ish://docs/ai-manifesto', $resources[0]['uri']);
        $this->assertEquals('text/markdown', $resources[0]['mimeType']);
    }

    public function testReadResource(): void
    {
        $provider = new IshAiManifestoResourceProvider($this->tempFile);
        $content = $provider->readResource('ish://docs/ai-manifesto');

        $this->assertEquals('# Test Manifesto', $content);
    }

    public function testReadResourceReturnsNullForInvalidUri(): void
    {
        $provider = new IshAiManifestoResourceProvider($this->tempFile);
        $content = $provider->readResource('ish://docs/invalid');

        $this->assertNull($content);
    }
}
