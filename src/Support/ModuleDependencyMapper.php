<?php

declare(strict_types=1);

namespace Ishmael\McpServer\Support;

use Ishmael\McpServer\Project\ProjectContext;

/**
 * Maps relationships between modules by scanning manifests and service registrations.
 */
final class ModuleDependencyMapper
{
    private ProjectContext $context;

    public function __construct(ProjectContext $context)
    {
        $this->context = $context;
    }

    /**
     * @return array{
     *   nodes: array<int, array{id: string, path: string, version: string, env: string, architecture?: array}>,
     *   edges: array<int, array{from: string, to: string, type: string}>
     * }
     */
    public function map(): array
    {
        $root = $this->context->getRoot();
        if ($root === null) {
            return ['nodes' => [], 'edges' => []];
        }

        $modulesPath = $root . DIRECTORY_SEPARATOR . 'Modules';
        if (!is_dir($modulesPath)) {
            return ['nodes' => [], 'edges' => []];
        }

        $nodes = [];
        $edges = [];
        $modules = array_filter(glob($modulesPath . DIRECTORY_SEPARATOR . '*'), 'is_dir');

        foreach ($modules as $moduleDir) {
            $moduleName = basename($moduleDir);
            $manifest = $this->loadManifest($moduleDir);

            $nodes[] = [
                'id' => $moduleName,
                'path' => realpath($moduleDir) ?: $moduleDir,
                'version' => (string)($manifest['version'] ?? '0.0.0'),
                'env' => (string)($manifest['env'] ?? 'shared'),
            ];

            // 1. Explicit dependencies from manifest
            $deps = (array)($manifest['dependencies'] ?? []);
            foreach ($deps as $dep) {
                $edges[] = [
                    'from' => $moduleName,
                    'to' => $dep,
                    'type' => 'explicit',
                ];
            }

            // 2. Service registrations (potential cross-module dependencies)
            $services = (array)($manifest['services'] ?? []);
            foreach ($services as $serviceId => $implementation) {
                if (is_string($implementation)) {
                    $depModule = $this->inferModuleFromClass($implementation);
                    if ($depModule && $depModule !== $moduleName) {
                        $edges[] = [
                            'from' => $moduleName,
                            'to' => $depModule,
                            'type' => 'service',
                        ];
                    }
                }
            }
        }

        // De-duplicate edges
        $uniqueEdges = [];
        foreach ($edges as $edge) {
            $key = "{$edge['from']}->{$edge['to']}:{$edge['type']}";
            $uniqueEdges[$key] = $edge;
        }
        $finalEdges = array_values($uniqueEdges);

        // Architectural analysis: Identify "God Modules" or high coupling
        $incomingCount = [];
        $outgoingCount = [];
        foreach ($finalEdges as $edge) {
            $outgoingCount[$edge['from']] = ($outgoingCount[$edge['from']] ?? 0) + 1;
            $incomingCount[$edge['to']] = ($incomingCount[$edge['to']] ?? 0) + 1;
        }

        foreach ($nodes as &$node) {
            $id = $node['id'];
            $out = $outgoingCount[$id] ?? 0;
            $in = $incomingCount[$id] ?? 0;

            $node['architecture'] = [
                'outgoing_dependencies' => $out,
                'incoming_dependants' => $in,
                'is_god_module' => ($out > 5 || $in > 5),
                'suggestion' => ($out > 5) ? 'High outgoing dependencies. Consider splitting this module.' : (($in > 5) ? 'High usage. Consider making this a Core feature or a shared Feature Pack.' : null)
            ];
        }

        return [
            'nodes' => $nodes,
            'edges' => $finalEdges,
        ];
    }

    private function loadManifest(string $moduleDir): array
    {
        $phpManifest = $moduleDir . DIRECTORY_SEPARATOR . 'module.php';
        $jsonManifest = $moduleDir . DIRECTORY_SEPARATOR . 'module.json';

        if (is_file($phpManifest)) {
            try {
                $data = (static function($file) {
                    return include $file;
                })($phpManifest);
                return is_array($data) ? $data : [];
            } catch (\Throwable $e) {
                return [];
            }
        }

        if (is_file($jsonManifest)) {
            $raw = file_get_contents($jsonManifest);
            $data = $raw !== false ? json_decode($raw, true) : null;
            return is_array($data) ? $data : [];
        }

        return [];
    }

    private function inferModuleFromClass(string $class): ?string
    {
        // Ishmael convention: Modules\<ModuleName>\...
        if (preg_match('/^Modules\\\\([^\\\\]+)\\\\/', $class, $matches)) {
            return $matches[1];
        }
        return null;
    }
}
