<?php

declare(strict_types=1);

namespace Ishmael\McpServer\Support;

use Ishmael\McpServer\Project\ProjectContext;

/**
 * Analyzes the project for refactoring opportunities, focusing on shared logic
 * and cross-module coupling to suggest base modules or shared interfaces.
 */
final class RefactoringAdvisor
{
    private ProjectContext $context;
    private ClassMetadataScanner $scanner;

    public function __construct(ProjectContext $context)
    {
        $this->context = $context;
        $this->scanner = new ClassMetadataScanner();
    }

    /**
     * @return array{
     *   opportunities: array<int, array{type: string, description: string, modules: string[], impact: string}>,
     *   coupling: array<string, array{score: int, reason: string}>
     * }
     */
    public function analyze(): array
    {
        $root = $this->context->getRoot();
        if ($root === null) {
            return ['opportunities' => [], 'coupling' => []];
        }

        $modulesPath = $root . DIRECTORY_SEPARATOR . 'Modules';
        if (!is_dir($modulesPath)) {
            return ['opportunities' => [], 'coupling' => []];
        }

        $moduleDirs = array_filter(glob($modulesPath . DIRECTORY_SEPARATOR . '*'), 'is_dir');
        $moduleMetadata = [];

        foreach ($moduleDirs as $dir) {
            $name = basename($dir);
            $moduleMetadata[$name] = $this->scanner->scan($dir, 'Modules\\' . $name);
        }

        $opportunities = [];
        $this->detectSharedLogic($moduleMetadata, $opportunities);
        
        $coupling = $this->analyzeCoupling($moduleMetadata);

        return [
            'opportunities' => $opportunities,
            'coupling' => $coupling,
        ];
    }

    /**
     * @param array<string, array<string, mixed>> $metadata
     * @param array<int, mixed> $opportunities
     */
    private function detectSharedLogic(array $metadata, array &$opportunities): void
    {
        $methodSignatures = [];

        foreach ($metadata as $moduleName => $classes) {
            foreach ($classes as $className => $data) {
                if (($data['role'] ?? '') !== 'service') {
                    continue;
                }

                foreach ($data['methods'] ?? [] as $methodName => $methodData) {
                    // Create a fingerprint for the method: name + return type
                    // In a more advanced version, we'd look at param types too.
                    $fingerprint = $methodName . ':' . ($methodData['returnType'] ?? 'mixed');
                    $methodSignatures[$fingerprint][] = [
                        'module' => $moduleName,
                        'class' => $className,
                        'method' => $methodName
                    ];
                }
            }
        }

        foreach ($methodSignatures as $fingerprint => $occurrences) {
            $modules = array_unique(array_column($occurrences, 'module'));
            if (count($modules) >= 2) {
                [$methodName, $returnType] = explode(':', $fingerprint);
                
                $opportunities[] = [
                    'type' => 'shared_capability',
                    'description' => "Multiple modules implement '{$methodName}' returning '{$returnType}'. Consider a shared interface or base module.",
                    'modules' => $modules,
                    'impact' => 'medium'
                ];
            }
        }
    }

    /**
     * @param array<string, array<string, mixed>> $metadata
     * @return array<string, array{score: int, reason: string}>
     */
    private function analyzeCoupling(array $metadata): array
    {
        $coupling = [];
        $root = $this->context->getRoot();

        foreach ($metadata as $moduleName => $classes) {
            $externalDependencies = [];
            foreach ($classes as $className => $data) {
                foreach ($data['methods'] ?? [] as $methodData) {
                    foreach ($methodData['parameters'] ?? [] as $param) {
                        $type = $param['type'] ?? '';
                        if (str_starts_with($type, 'Modules\\')) {
                            $targetModule = explode('\\', $type)[1];
                            if ($targetModule !== $moduleName) {
                                $externalDependencies[$targetModule] = ($externalDependencies[$targetModule] ?? 0) + 1;
                            }
                        }
                    }
                }
            }

            foreach ($externalDependencies as $target => $count) {
                $reason = "Direct dependency count ({$count}) between modules.";
                $isPremium = false;

                // Check if the target module is a premium module
                if ($root !== null) {
                    $manifestPath = $root . DIRECTORY_SEPARATOR . 'Modules' . DIRECTORY_SEPARATOR . $target . DIRECTORY_SEPARATOR . 'module.json';
                    if (is_file($manifestPath)) {
                        $manifest = json_decode(file_get_contents($manifestPath), true);
                        if (isset($manifest['tier']) && in_array($manifest['tier'], ['commercial', 'dual'])) {
                            $isPremium = true;
                            $reason .= " WARNING: '{$target}' is a PREMIUM module and requires a license/trial for some capabilities.";
                        }
                    }
                }

                if ($count > 3 || $isPremium) {
                    $coupling[$moduleName . ' -> ' . $target] = [
                        'score' => $count,
                        'reason' => $reason . ($count > 3 ? " Consider if these should be merged or use a shared base." : "")
                    ];
                }
            }
        }

        return $coupling;
    }
}
