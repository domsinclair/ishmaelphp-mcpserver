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
        ];
    }

    public function readResource(string $uri): ?string
    {
        if ($uri !== 'ish://docs/ai-manifesto') {
            return null;
        }

        if (is_file($this->manifestoPath)) {
            return file_get_contents($this->manifestoPath);
        }

        return null;
    }
}
