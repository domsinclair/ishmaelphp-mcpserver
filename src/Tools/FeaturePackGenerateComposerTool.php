<?php

declare(strict_types=1);

namespace Ishmael\McpServer\Tools;

use Ishmael\McpServer\Contracts\Tool;
use Ishmael\McpServer\Project\ProjectContext;

final class FeaturePackGenerateComposerTool implements Tool
{
    private ProjectContext $context;

    public function __construct(ProjectContext $context)
    {
        $this->context = $context;
    }

    public function getName(): string
    {
        return 'ish:featurePack:generateComposer';
    }

    public function getDescription(): string
    {
        return 'Generates a composer.json file for a module based on its manifest.';
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
        $composerPath = $moduleDir . DIRECTORY_SEPARATOR . 'composer.json';

        if (!is_dir($moduleDir)) {
            return [
                'success' => false,
                'path' => '',
                'error' => "Module directory not found: Modules/{$moduleName}",
            ];
        }

        $manifestPath = $moduleDir . DIRECTORY_SEPARATOR . 'module.php';
        $manifest = is_file($manifestPath) ? include $manifestPath : [];

        // Extract info from manifest
        $name = $manifest['name'] ?? strtolower($moduleName);
        $title = $manifest['title'] ?? $moduleName;
        $description = $manifest['description'] ?? $manifest['synopsis'] ?? 'An Ishmael feature pack module.';
        $version = $manifest['version'] ?? '1.0.0';
        $author = $manifest['author'] ?? [];
        $license = $manifest['license'] ?? 'proprietary';
        $dependencies = $manifest['dependencies'] ?? [];

        // Build vendor/package name
        $vendorName = strtolower($author['vendor'] ?? 'ishmael-vendor');
        $packageName = strtolower(preg_replace('/[^a-zA-Z0-9-]/', '-', $name));
        $fullPackageName = "{$vendorName}/{$packageName}";

        // Build composer.json structure
        $composer = [
            'name' => $fullPackageName,
            'description' => $description,
            'type' => 'ishmael-module',
            'version' => $version,
            'license' => $license,
            'keywords' => ['ishmael', 'feature-pack', 'module'],
            'require' => [
                'php' => '>=8.1',
            ],
            'autoload' => [
                'psr-4' => [
                    "Modules\\{$moduleName}\\" => '',
                ],
            ],
            'extra' => [
                'ishmael' => [
                    'module' => $moduleName,
                    'capabilities' => $manifest['capabilities'] ?? [],
                ],
            ],
        ];

        // Add author if available
        if (!empty($author['name'])) {
            $authorEntry = ['name' => $author['name']];
            if (!empty($author['email'])) {
                $authorEntry['email'] = $author['email'];
            }
            if (!empty($author['homepage'])) {
                $authorEntry['homepage'] = $author['homepage'];
            }
            $composer['authors'] = [$authorEntry];
        }

        // Add module dependencies as suggest (they're Ishmael modules, not Composer packages)
        if (!empty($dependencies)) {
            $composer['extra']['ishmael']['dependencies'] = $dependencies;
        }

        // Encode with pretty print
        $json = json_encode($composer, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            return [
                'success' => false,
                'path' => '',
                'error' => 'Failed to encode composer.json',
            ];
        }

        // Write file
        if (file_put_contents($composerPath, $json . "\n") === false) {
            return [
                'success' => false,
                'path' => '',
                'error' => 'Failed to write composer.json',
            ];
        }

        return [
            'success' => true,
            'path' => $composerPath,
            'error' => null,
        ];
    }
}
