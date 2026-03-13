<?php

declare(strict_types=1);

namespace Ishmael\McpServer\Tools;

use Ishmael\McpServer\Contracts\Tool;
use Ishmael\McpServer\Project\ProjectContext;

final class FeaturePackValidateTool implements Tool
{
    private ProjectContext $context;

    public function __construct(ProjectContext $context)
    {
        $this->context = $context;
    }

    public function getName(): string
    {
        return 'ish:featurePack:validate';
    }

    public function getDescription(): string
    {
        return 'Validates a module for feature pack publication, checking all required fields and files.';
    }

    public function getInputSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['module'],
            'properties' => [
                'module' => [
                    'type' => 'string',
                    'description' => 'The name of the module to validate.'
                ],
            ],
        ];
    }

    public function getOutputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'canPublish' => ['type' => 'boolean'],
                'errors' => ['type' => 'array', 'items' => ['type' => 'string']],
                'warnings' => ['type' => 'array', 'items' => ['type' => 'string']],
                'info' => ['type' => 'array', 'items' => ['type' => 'string']],
                'missingContextFile' => ['type' => 'boolean'],
            ],
        ];
    }

    public function execute(array $input): array
    {
        $moduleName = (string)$input['module'];
        $root = $this->context->getRoot();
        $moduleDir = $root . DIRECTORY_SEPARATOR . 'Modules' . DIRECTORY_SEPARATOR . $moduleName;

        $errors = [];
        $warnings = [];
        $info = [];
        $missingContextFile = false;

        // Check 1: Module directory exists
        if (!is_dir($moduleDir)) {
            return [
                'canPublish' => false,
                'errors' => ["Module directory not found: Modules/{$moduleName}"],
                'warnings' => [],
                'info' => [],
                'missingContextFile' => false,
            ];
        }

        // Check 2: module.php exists
        $manifestPath = $moduleDir . DIRECTORY_SEPARATOR . 'module.php';
        if (!is_file($manifestPath)) {
            $errors[] = "module.php not found in module directory.";
            return [
                'canPublish' => false,
                'errors' => $errors,
                'warnings' => $warnings,
                'info' => $info,
                'missingContextFile' => false,
            ];
        }

        // Load manifest
        $manifest = include $manifestPath;
        if (!is_array($manifest)) {
            $errors[] = "module.php must return an array.";
            return [
                'canPublish' => false,
                'errors' => $errors,
                'warnings' => $warnings,
                'info' => $info,
                'missingContextFile' => false,
            ];
        }

        // Check 3: export array defined
        if (empty($manifest['export'])) {
            $errors[] = "module.php must define an 'export' array listing files/folders to include.";
        } else {
            // Check 9: All export items exist
            foreach ($manifest['export'] as $item) {
                $itemPath = $moduleDir . DIRECTORY_SEPARATOR . $item;
                if (!file_exists($itemPath)) {
                    $errors[] = "Export item not found: {$item}";
                }
            }
        }

        // Check 4: title field
        if (empty($manifest['title'])) {
            $warnings[] = "Missing 'title' in module.php. Will default to module name.";
        }

        // Check 5: category field
        if (empty($manifest['category'])) {
            $warnings[] = "Missing 'category' in module.php. Will default to 'General'.";
        }

        // Check 6: capabilities array
        if (empty($manifest['capabilities'])) {
            $errors[] = "module.php must define at least one capability.";
        }

        // Check 7: author block
        if (empty($manifest['author']) || empty($manifest['author']['name'])) {
            $warnings[] = "Missing 'author' in module.php. Will show 'Unknown Author' in registry.";
        }

        // Check 8: .ish-context.md
        $contextPath = $moduleDir . DIRECTORY_SEPARATOR . '.ish-context.md';
        if (!is_file($contextPath)) {
            $info[] = "Consider adding .ish-context.md for AI-ready modules.";
            $missingContextFile = true;
        }

        // Check 10: version field
        if (empty($manifest['version'])) {
            $warnings[] = "Missing 'version' in module.php. Recommended for registry tracking.";
        }

        // Additional info
        if (empty($errors)) {
            $info[] = "Module '{$moduleName}' is ready for publication.";
        }

        return [
            'canPublish' => empty($errors),
            'errors' => $errors,
            'warnings' => $warnings,
            'info' => $info,
            'missingContextFile' => $missingContextFile,
        ];
    }
}
