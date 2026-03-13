<?php

declare(strict_types=1);

namespace Ishmael\McpServer\Tools;

use Ishmael\McpServer\Contracts\Tool;
use Ishmael\McpServer\Project\ProjectContext;

final class FeaturePackGenerateContextTool implements Tool
{
    private ProjectContext $context;

    public function __construct(ProjectContext $context)
    {
        $this->context = $context;
    }

    public function getName(): string
    {
        return 'ish:featurePack:generateContext';
    }

    public function getDescription(): string
    {
        return 'Generates a .ish-context.md file for a module based on its manifest and structure.';
    }

    public function getInputSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['module'],
            'properties' => [
                'module' => [
                    'type' => 'string',
                    'description' => 'The name of the module.'
                ],
            ],
        ];
    }

    public function getOutputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'success' => ['type' => 'boolean'],
                'path' => ['type' => 'string'],
                'error' => ['type' => 'string'],
            ],
        ];
    }

    public function execute(array $input): array
    {
        $moduleName = (string)$input['module'];
        $root = $this->context->getRoot();
        $moduleDir = $root . DIRECTORY_SEPARATOR . 'Modules' . DIRECTORY_SEPARATOR . $moduleName;
        $contextPath = $moduleDir . DIRECTORY_SEPARATOR . '.ish-context.md';

        if (!is_dir($moduleDir)) {
            return [
                'success' => false,
                'path' => '',
                'error' => "Module directory not found: Modules/{$moduleName}",
            ];
        }

        $manifestPath = $moduleDir . DIRECTORY_SEPARATOR . 'module.php';
        $manifest = is_file($manifestPath) ? include $manifestPath : [];

        // Gather module info
        $name = $manifest['name'] ?? $moduleName;
        $title = $manifest['title'] ?? $moduleName;
        $description = $manifest['description'] ?? $manifest['synopsis'] ?? 'No description provided.';
        $capabilities = $manifest['capabilities'] ?? [];
        $emits = $manifest['emits'] ?? [];
        $services = $manifest['services'] ?? [];

        // Scan for key files
        $controllers = $this->scanDir($moduleDir . DIRECTORY_SEPARATOR . 'Controllers', '.php');
        $models = $this->scanDir($moduleDir . DIRECTORY_SEPARATOR . 'Models', '.php');
        $serviceFiles = $this->scanDir($moduleDir . DIRECTORY_SEPARATOR . 'Services', '.php');

        // Build markdown content
        $md = "# {$title} Module Context\n\n";
        $md .= "## Purpose\n{$description}\n\n";

        // Architecture section
        $md .= "## Architecture\n";
        if (!empty($serviceFiles)) {
            foreach ($serviceFiles as $svc) {
                $svcName = pathinfo($svc, PATHINFO_FILENAME);
                $md .= "- **{$svcName}**: Service class for business logic\n";
            }
        }
        if (!empty($emits)) {
            $md .= "- **Events**: " . implode(', ', array_map(fn($e) => basename(str_replace('\\', '/', $e)), array_keys($emits))) . "\n";
        }
        $md .= "\n";

        // Key Files
        $md .= "## Key Files\n";
        foreach ($controllers as $ctrl) {
            $md .= "- `Controllers/{$ctrl}` - HTTP controller\n";
        }
        foreach ($models as $model) {
            $md .= "- `Models/{$model}` - Eloquent model\n";
        }
        foreach ($serviceFiles as $svc) {
            $md .= "- `Services/{$svc}` - Service class\n";
        }
        if (is_file($moduleDir . DIRECTORY_SEPARATOR . 'Config' . DIRECTORY_SEPARATOR . strtolower($name) . '.php')) {
            $md .= "- `Config/" . strtolower($name) . ".php` - Module configuration\n";
        }
        $md .= "\n";

        // Capabilities
        if (!empty($capabilities)) {
            $md .= "## Capabilities\n";
            foreach ($capabilities as $cap) {
                $md .= "- `{$cap}`\n";
            }
            $md .= "\n";
        }

        // Events / Extension Points
        if (!empty($emits)) {
            $md .= "## Extension Points (Events)\n";
            foreach ($emits as $eventClass => $eventInfo) {
                $eventName = basename(str_replace('\\', '/', $eventClass));
                $desc = $eventInfo['description'] ?? '';
                $md .= "- **{$eventName}**: {$desc}\n";
            }
            $md .= "\n";
        }

        // Dependencies
        $deps = $manifest['dependencies'] ?? [];
        $md .= "## Dependencies\n";
        if (empty($deps)) {
            $md .= "None (standalone module)\n";
        } else {
            foreach ($deps as $dep) {
                $md .= "- {$dep}\n";
            }
        }

        // Write file
        if (file_put_contents($contextPath, $md) === false) {
            return [
                'success' => false,
                'path' => '',
                'error' => "Failed to write .ish-context.md",
            ];
        }

        return [
            'success' => true,
            'path' => $contextPath,
            'error' => null,
        ];
    }

    private function scanDir(string $dir, string $extension): array
    {
        if (!is_dir($dir)) {
            return [];
        }
        $files = [];
        foreach (scandir($dir) as $file) {
            if (str_ends_with($file, $extension)) {
                $files[] = $file;
            }
        }
        return $files;
    }
}
