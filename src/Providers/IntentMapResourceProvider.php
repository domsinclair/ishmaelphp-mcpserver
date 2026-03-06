<?php

declare(strict_types=1);

namespace Ishmael\McpServer\Providers;

use Ishmael\McpServer\Contracts\ResourceProvider;

final class IntentMapResourceProvider implements ResourceProvider
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
                'uri' => 'ish://intent-map',
                'name' => 'Ishmael Intent Map',
                'description' => 'Registry of canonical user intents, glossary of terms, and behavior contracts for the Ishmael MCP server.',
                'mimeType' => 'application/json',
            ],
            [
                'uri' => 'ish://docs/intent-map',
                'name' => 'Ishmael Intent Map (Alias)',
                'description' => 'Backward-compatible alias for ish://intent-map.',
                'mimeType' => 'application/json',
            ]
        ];
    }

    public function readResource(string $uri): ?string
    {
        // Canonical URI and backward-compatible alias both resolve to the same resource
        if ($uri === 'ish://intent-map' || $uri === 'ish://docs/intent-map') {
            if (is_file($this->mapPath)) {
                return file_get_contents($this->mapPath);
            }
        }
        return null;
    }
}
