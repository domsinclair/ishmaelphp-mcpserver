<?php

declare(strict_types=1);

namespace Ishmael\McpServer\Tools;

use Ishmael\McpServer\Contracts\Tool;
use Ishmael\McpServer\Project\ProjectContext;

/**
 * ish:findUsages — Finds string-based references to a class FQN.
 */
final class FindUsagesTool implements Tool
{
    private ProjectContext $context;

    public function __construct(ProjectContext $context)
    {
        $this->context = $context;
    }

    public function getName(): string
    {
        return 'ish:findUsages';
    }

    public function getDescription(): string
    {
        return 'Finds string-based references to a class FQN (e.g., in module.json or routes.php).';
    }

    public function getInputSchema(): array
    {
        return [
            'type' => 'object',
            'additionalProperties' => false,
            'required' => ['fqn'],
            'properties' => [
                'fqn' => ['type' => 'string', 'description' => 'The fully-qualified class name to search for.'],
            ],
        ];
    }

    public function getOutputSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['usages'],
            'properties' => [
                'usages' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'required' => ['file', 'line', 'text'],
                        'properties' => [
                            'file' => ['type' => 'string'],
                            'line' => ['type' => 'integer'],
                            'text' => ['type' => 'string'],
                        ],
                    ],
                ],
            ],
        ];
    }

    public function execute(array $input): array
    {
        $fqn = $input['fqn'];
        $root = $this->context->getRoot();
        if ($root === null) {
            return ['usages' => []];
        }

        // Prepare search patterns
        // 1. Full FQN: Modules\Blog\Controllers\PostController
        // 2. Short name with @: PostController@index
        $parts = explode('\\', $fqn);
        $shortName = end($parts);
        
        $usages = [];
        
        // Scan Modules directory for module.json and routes.php
        $modulesPath = $root . DIRECTORY_SEPARATOR . 'Modules';
        if (is_dir($modulesPath)) {
            $this->scanDirectory($modulesPath, $fqn, $shortName, $usages, $root);
        }
        
        // Scan config directory
        $configPath = $root . DIRECTORY_SEPARATOR . 'config';
        if (is_dir($configPath)) {
            $this->scanDirectory($configPath, $fqn, $shortName, $usages, $root);
        }

        return ['usages' => $usages];
    }

    private function scanDirectory(string $dir, string $fqn, string $shortName, array &$usages, string $root): void
    {
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            /** @var \SplFileInfo $file */
            if ($file->isDir()) {
                continue;
            }

            $filename = $file->getFilename();
            
            // Only scan relevant files
            if ($filename !== 'module.json' && $filename !== 'routes.php' && !str_starts_with($file->getPathname(), $root . DIRECTORY_SEPARATOR . 'config')) {
                continue;
            }

            $content = file_get_contents($file->getPathname());
            if ($content === false) {
                continue;
            }

            $lines = explode("\n", $content);
            foreach ($lines as $index => $line) {
                $found = false;
                if (str_contains($line, $fqn)) {
                    $found = true;
                } elseif (str_contains($line, $shortName . '@')) {
                    $found = true;
                }

                if ($found) {
                    $usages[] = [
                        'file' => (string)realpath($file->getPathname()),
                        'line' => $index + 1,
                        'text' => trim($line),
                    ];
                }
            }
        }
    }
}
