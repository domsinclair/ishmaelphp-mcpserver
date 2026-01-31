<?php

declare(strict_types=1);

namespace Ishmael\McpServer\Tests\Providers;

use Ishmael\McpServer\Providers\FrameworkIntrospectionProvider;
use Ishmael\McpServer\Project\ProjectContext;
use PHPUnit\Framework\TestCase;

final class FrameworkIntrospectionProviderTest extends TestCase
{
    public function testListResources(): void
    {
        $context = $this->createMock(ProjectContext::class);
        $provider = new FrameworkIntrospectionProvider($context);
        $resources = $provider->listResources();

        $this->assertCount(1, $resources);
        $this->assertEquals('ish://framework/introspection', $resources[0]['uri']);
    }

    public function testReadResource(): void
    {
        $context = $this->createMock(ProjectContext::class);
        $provider = new FrameworkIntrospectionProvider($context);
        $content = $provider->readResource('ish://framework/introspection');

        $this->assertNotNull($content);
        $data = json_decode($content, true);

        $this->assertArrayHasKey('middleware', $data);
        $this->assertArrayHasKey('helpers', $data);
        $this->assertArrayHasKey('attributes', $data);
        $this->assertArrayHasKey('container_bindings', $data);
        $this->assertArrayHasKey('route_constraints', $data);
        $this->assertArrayHasKey('core_contracts', $data);

        $this->assertCount(10, $data['middleware']['stack']);
        $this->assertArrayHasKey('base_path', $data['helpers']['functions']);
        $this->assertArrayHasKey('Ishmael\Core\Attributes\Auditable', $data['attributes']['attributes']);
    }

    public function testReadResourceReturnsNullForInvalidUri(): void
    {
        $context = $this->createMock(ProjectContext::class);
        $provider = new FrameworkIntrospectionProvider($context);
        $content = $provider->readResource('ish://invalid');

        $this->assertNull($content);
    }
}
