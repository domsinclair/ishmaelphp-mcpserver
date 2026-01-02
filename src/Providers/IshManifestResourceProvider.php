<?php

declare(strict_types=1);

namespace Ishmael\McpServer\Providers;

use Ishmael\McpServer\Contracts\ResourceProvider;
use Ishmael\McpServer\Project\ProjectContext;
use Ishmael\McpServer\Support\ClassMetadataScanner;

/**
 * Exposes a JSON representation of all detected modules and their metadata (ish://config/manifest).
 */
final class IshManifestResourceProvider implements ResourceProvider
{
    private ProjectContext $context;
    private ClassMetadataScanner $scanner;

    public function __construct(ProjectContext $context)
    {
        $this->context = $context;
        $this->scanner = new ClassMetadataScanner();
    }

    public function listResources(): array
    {
        return [
            [
                'uri' => 'ish://config/manifest',
                'name' => 'Ishmael Module Manifest',
                'description' => 'JSON representation of all detected modules and their metadata',
                'mimeType' => 'application/json',
            ],
        ];
    }

    /**
     * This method is called by the Server/ResourceProvider infrastructure when a specific URI is requested.
     */
    public function readResource(string $uri): ?string
    {
        if ($uri !== 'ish://config/manifest') {
            return null;
        }

        $root = $this->context->getRoot();
        if ($root === null) {
            return json_encode(['error' => 'Project root not found']);
        }

        // We use ISH_BOOTSTRAP_ONLY to avoid full app boot
        if (!defined('ISH_BOOTSTRAP_ONLY')) {
            define('ISH_BOOTSTRAP_ONLY', true);
        }
        if (!defined('ISH_APP_BASE')) {
            define('ISH_APP_BASE', $root);
        }

        $autoload = $root . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php';
        if (!is_file($autoload)) {
             return json_encode(['error' => 'Autoloader not found at ' . $autoload]);
        }

        require_once $autoload;

        if (!class_exists('Ishmael\Core\ModuleManager')) {
            return json_encode(['error' => 'Ishmael\Core\ModuleManager class not found.']);
        }

        $modulesPath = $root . DIRECTORY_SEPARATOR . 'Modules';
        \Ishmael\Core\ModuleManager::discover($modulesPath, [
            'appEnv' => getenv('APP_ENV') ?: 'development',
        ]);

        $modules = \Ishmael\Core\ModuleManager::$modules;

        // Debugging
        foreach ($modules as $name => &$moduleData) {
            $modulePath = $moduleData['path'] ?? null;
            if ($modulePath && is_dir($modulePath)) {
                $moduleData['classes'] = $this->scanner->scan($modulePath, 'Modules\\' . $name);
            }
        }

        return json_encode($modules, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }
}
