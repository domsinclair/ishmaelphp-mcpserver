<?php

declare(strict_types=1);

namespace Ishmael\McpServer\Providers;

use Ishmael\McpServer\Contracts\ResourceProvider;
use Ishmael\McpServer\Support\FrameworkMapParser;

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
                'description' => 'Semantic structure map of the Ishmael framework and application zones (JSON representation).',
                'mimeType' => 'application/json',
            ]
        ];
    }

    public function readResource(string $uri): ?string
    {
        if ($uri !== 'ish://docs/framework-map') {
            return null;
        }

        if (!is_file($this->mapPath)) {
            return json_encode(['error' => 'Framework map file not found.']);
        }

        $content = file_get_contents($this->mapPath);
        return json_encode(FrameworkMapParser::parse($content), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }
}
