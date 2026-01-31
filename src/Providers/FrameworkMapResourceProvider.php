<?php

declare(strict_types=1);

namespace Ishmael\McpServer\Providers;

use Ishmael\McpServer\Contracts\ResourceProvider;

final class FrameworkMapResourceProvider implements ResourceProvider
{
    private string $mapPath;

    public function __construct(string $mapPath)
    {
        $this->mapPath = $mapPath;
    }

    public function listResources(): array
    {
        return [
            [
                'uri' => 'ish://docs/framework-map',
                'name' => 'Ishmael Framework Map',
                'description' => 'Semantic structure map of the Ishmael framework and application zones (Found in Docs/Core/reference/framework-map.md).',
                'mimeType' => 'text/markdown',
            ]
        ];
    }

    public function readResource(string $uri): ?string
    {
        if ($uri === 'ish://docs/framework-map') {
            if (is_file($this->mapPath)) {
                return file_get_contents($this->mapPath);
            }
        }
        return null;
    }
}
