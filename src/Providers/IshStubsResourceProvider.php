<?php

declare(strict_types=1);

namespace Ishmael\McpServer\Providers;

use Ishmael\McpServer\Contracts\ResourceProvider;
use Ishmael\McpServer\Project\PathSandbox;

/**
 * Exposes framework stubs as resources (ish://stubs/{path}).
 */
final class IshStubsResourceProvider implements ResourceProvider
{
    private PathSandbox $sandbox;
    private string $coreRoot;

    public function __construct(PathSandbox $sandbox, string $coreRoot)
    {
        $this->sandbox = $sandbox;
        $this->coreRoot = $coreRoot;
    }

    public function listResources(): array
    {
        $stubsDir = $this->coreRoot . DIRECTORY_SEPARATOR . 'Resources' . DIRECTORY_SEPARATOR . 'stubs';
        $items = [];

        if (!is_dir($stubsDir)) {
            return $items;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($stubsDir, \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            /** @var \SplFileInfo $file */
            if ($file->isDir()) {
                continue;
            }

            $relPath = str_replace($stubsDir . DIRECTORY_SEPARATOR, '', $file->getPathname());
            $relPath = str_replace(DIRECTORY_SEPARATOR, '/', $relPath);

            $items[] = [
                'uri' => 'ish://stubs/' . $relPath,
                'name' => 'Stub: ' . $relPath,
                'description' => 'Ishmael framework template stub',
                'mimeType' => 'text/plain',
                'path' => $file->getPathname(), // Internal use for reading
            ];
        }

        return $items;
    }

    public function readResource(string $uri): ?string
    {
        if (!str_starts_with($uri, 'ish://stubs/')) {
            return null;
        }

        $relPath = substr($uri, 12);
        $stubsDir = $this->coreRoot . DIRECTORY_SEPARATOR . 'Resources' . DIRECTORY_SEPARATOR . 'stubs';
        $fullPath = $stubsDir . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relPath);

        if (is_file($fullPath)) {
            return file_get_contents($fullPath);
        }

        return null;
    }
}
