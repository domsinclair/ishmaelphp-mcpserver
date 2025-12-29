<?php

declare(strict_types=1);

namespace Ishmael\McpServer\Support;

use Ishmael\McpServer\Project\ProjectContext;
use Ishmael\McpServer\Support\RouterProbe;

/**
 * Discovers and parses Ishmael routes from Modules directory.
 */
final class RouteCollector
{
    private ProjectContext $context;

    public function __construct(ProjectContext $context)
    {
        $this->context = $context;
    }

    /**
     * @return array<int, array{method: string, path: string, handler: string}>
     */
    public function collect(): array
    {
        $root = $this->context->getRoot();
        if ($root === null) {
            return [];
        }

        $modulesPath = $root . DIRECTORY_SEPARATOR . 'Modules';
        if (!is_dir($modulesPath)) {
            return [];
        }

        $routes = [];
        $modules = array_filter(glob($modulesPath . DIRECTORY_SEPARATOR . '*'), 'is_dir');

        foreach ($modules as $moduleDir) {
            $moduleName = basename($moduleDir);
            $routesFile = $moduleDir . DIRECTORY_SEPARATOR . 'routes.php';

            if (!is_file($routesFile)) {
                continue;
            }

            $moduleRoutes = $this->parseRoutesFile($routesFile, $moduleName);
            $routes = array_merge($routes, $moduleRoutes);
        }

        return $routes;
    }

    /**
     * @return array<int, array{method: string, path: string, handler: string}>
     */
    private function parseRoutesFile(string $file, string $moduleName): array
    {
        // Use a clean environment to include the file
        try {
            $result = (static function($file) {
                return include $file;
            })($file);
        } catch (\Throwable $e) {
            fwrite(STDERR, "[RouteCollector] Error including {$file}: " . $e->getMessage() . "\n");
            throw $e;
        }

        if (is_array($result)) {
            return $this->processLegacyArray($result, $moduleName);
        }

        if ($result instanceof \Closure) {
            return $this->processFluentClosure($result, $moduleName);
        }

        return [];
    }

    private function processLegacyArray(array $data, string $moduleName): array
    {
        $out = [];
        foreach ($data as $pattern => $handler) {
            $path = (string)$pattern;
            $h = $handler;
            if (is_array($handler)) {
                $h = $handler['handler'] ?? 'unknown';
            }

            $out[] = [
                'method' => 'ANY', // Legacy arrays don't specify method in keys usually
                'path' => '/' . ltrim($path, '/'),
                'handler' => $this->normalizeHandler($h, $moduleName),
            ];
        }
        return $out;
    }

    private function processFluentClosure(\Closure $closure, string $moduleName): array
    {
        try {
            $probe = RouterProbe::make($moduleName);
            if (method_exists(\Ishmael\Core\Router::class, 'setActive')) {
                \Ishmael\Core\Router::setActive($probe);
            }
            $closure($probe);
            return $probe->collected;
        } catch (\Throwable $e) {
            fwrite(STDERR, "[RouteCollector] Error executing closure for {$moduleName}: " . $e->getMessage() . "\n");
            throw $e;
        }
    }

    private function normalizeHandler($handler, string $moduleName): string
    {
        if (is_string($handler)) {
            return $handler;
        }
        if (is_array($handler) && count($handler) >= 2) {
            $class = is_object($handler[0]) ? get_class($handler[0]) : $handler[0];
            return $class . '@' . $handler[1];
        }
        if ($handler instanceof \Closure) {
            return 'Closure';
        }
        return 'unknown';
    }
}
