<?php

declare(strict_types=1);

namespace Ishmael\McpServer\Providers;

use Ishmael\McpServer\Contracts\ResourceProvider;

/**
 * Exposes the Ishmael AI Manifesto (The Prime Directive) at ish://docs/ai-manifesto.
 */
final class IshAiManifestoResourceProvider implements ResourceProvider
{
    private string $manifestoPath;

    public function __construct(string $manifestoPath)
    {
        $this->manifestoPath = $manifestoPath;
    }

    public function listResources(): array
    {
        return [
            [
                'uri' => 'ish://docs/ai-manifesto',
                'name' => 'Ishmael AI Manifesto',
                'description' => 'Core philosophy, patterns, and safety protocols for AI assistants (The Prime Directive)',
                'mimeType' => 'text/markdown',
            ],
            [
                'uri' => 'ish://docs/feature-pack-manifesto',
                'name' => 'Feature Pack Analysis Manifesto',
                'description' => 'Instructions for AI agents on how to analyze and document Feature Packs (Modules)',
                'mimeType' => 'text/markdown',
            ],
        ];
    }

    public function readResource(string $uri): ?string
    {
        if ($uri === 'ish://docs/ai-manifesto') {
            if (is_file($this->manifestoPath)) {
                return file_get_contents($this->manifestoPath);
            }
        }

        if ($uri === 'ish://docs/feature-pack-manifesto') {
            $path = dirname($this->manifestoPath) . DIRECTORY_SEPARATOR . 'feature-pack-analysis-manifesto.md';
            if (is_file($path)) {
                return file_get_contents($path);
            }
        }

        return null;
    }
}
