<?php

declare(strict_types=1);

namespace Ishmael\McpServer\Providers;

use Ishmael\McpServer\Contracts\ResourceProvider;
use Ishmael\McpServer\Project\ProjectContext;

/**
 * Exposes a CLI command metadata as a resource (ish://cli/commands).
 */
final class IshCliMetadataResourceProvider implements ResourceProvider
{
    private ProjectContext $context;

    public function __construct(ProjectContext $context)
    {
        $this->context = $context;
    }

    public function listResources(): array
    {
        return [
            [
                'uri' => 'ish://cli/commands',
                'name' => 'Ishmael CLI Commands',
                'description' => 'List of available Ishmael CLI commands with descriptions and options',
                'mimeType' => 'application/json',
            ],
        ];
    }

    public function readResource(string $uri): ?string
    {
        if ($uri !== 'ish://cli/commands') {
            return null;
        }

        $root = $this->context->getRoot();
        if ($root === null) {
            return json_encode(['error' => 'Project root not found']);
        }

        $metadataPath = $root . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'cli_commands.json';
        if (!is_file($metadataPath)) {
            return json_encode(['error' => 'CLI commands metadata not found. Run any ish command to generate it.']);
        }

        return file_get_contents($metadataPath);
    }
}
