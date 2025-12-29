<?php

declare(strict_types=1);

namespace Ishmael\McpServer\Support;

use Ishmael\McpServer\Project\ProjectContext;

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
            return [];
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
        $collector = new class($moduleName) {
            public array $collected = [];
            private string $moduleName;
            private array $groupStack = [];

            public function __construct(string $moduleName) {
                $this->moduleName = $moduleName;
            }

            public function get(string $path, $handler) { return $this->add(['GET'], $path, $handler); }
            public function post(string $path, $handler) { return $this->add(['POST'], $path, $handler); }
            public function put(string $path, $handler) { return $this->add(['PUT'], $path, $handler); }
            public function patch(string $path, $handler) { return $this->add(['PATCH'], $path, $handler); }
            public function delete(string $path, $handler) { return $this->add(['DELETE'], $path, $handler); }
            public function any(string $path, $handler) {
                return $this->add(['GET','POST','PUT','PATCH','DELETE','OPTIONS','HEAD'], $path, $handler);
            }

            public function group(array $options, callable $callback): void
            {
                $prefix = $options['prefix'] ?? '';
                $currentPrefix = end($this->groupStack) ?: '';
                $newPrefix = rtrim($currentPrefix, '/') . '/' . ltrim($prefix, '/');
                $this->groupStack[] = $newPrefix;
                $callback($this);
                array_pop($this->groupStack);
            }

            public function add(array $methods, string $path, $handler): self
            {
                $currentPrefix = end($this->groupStack) ?: '';
                $fullPath = rtrim($currentPrefix, '/') . '/' . ltrim($path, '/');
                foreach ($methods as $method) {
                    $this->collected[] = [
                        'method' => $method,
                        'path' => '/' . ltrim($fullPath, '/'),
                        'handler' => $this->normalizeHandler($handler, $this->moduleName),
                    ];
                }
                return $this;
            }

            public function name(string $name): self { return $this; }
            public function middleware(array $mw): self { return $this; }

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
        };

        try {
            $closure($collector);
        } catch (\Throwable $e) {
            fwrite(STDERR, "[RouteCollector] Error executing closure for {$moduleName}: " . $e->getMessage() . "\n");
        }

        return $collector->collected;
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
