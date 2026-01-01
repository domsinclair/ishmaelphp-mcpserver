<?php

declare(strict_types=1);

namespace Ishmael\McpServer\Providers;

use Ishmael\McpServer\Contracts\ResourceProvider;
use Ishmael\McpServer\Project\ProjectContext;

/**
 * Exposes AI-friendly context documentation (ish://docs/ai-context).
 */
final class AiContextResourceProvider implements ResourceProvider
{
    private ProjectContext $context;

    public function __construct(ProjectContext $context)
    {
        $this->context = $context;
    }

    public function listResources(): array
    {
        $root = $this->context->getRoot();
        if ($root === null) {
            return [];
        }

        $aiContextFile = $root . DIRECTORY_SEPARATOR . '.ai-context.md';
        if (!is_file($aiContextFile)) {
            return [];
        }

        return [
            [
                'uri' => 'ish://docs/ai-context',
                'name' => 'AI Context Guide',
                'description' => 'A condensed map of the framework architecture and preferred patterns for LLMs.',
                'mimeType' => 'text/markdown',
            ],
        ];
    }

    public function readResource(string $uri): ?string
    {
        if ($uri !== 'ish://docs/ai-context') {
            return null;
        }

        $root = $this->context->getRoot();
        if ($root === null) {
            return null;
        }

        $aiContextFile = $root . DIRECTORY_SEPARATOR . '.ai-context.md';
        if (!is_file($aiContextFile)) {
            return null;
        }

        return file_get_contents($aiContextFile);
    }
}
