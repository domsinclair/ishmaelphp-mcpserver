<?php

declare(strict_types=1);

namespace Ishmael\McpServer\Support;

/**
 * Scans PHP files in a directory to extract metadata about classes, 
 * focusing on public methods and properties.
 */
final class ClassMetadataScanner
{
    /**
     * @param string $directory The directory to scan.
     * @param string $namespacePrefix The namespace prefix corresponding to this directory (e.g. 'Modules\Home\').
     * @return array<string, array{methods: string[], properties: string[]}>
     */
    public function scan(string $directory, string $namespacePrefix): array
    {
        if (!is_dir($directory)) {
            return [];
        }

        $metadata = [];
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($directory));
        
        foreach ($iterator as $file) {
            if ($file->isDir() || $file->getExtension() !== 'php') {
                continue;
            }

            // Skip directories that are known to contain non-class PHP files (like Views)
            $filePath = $file->getRealPath();
            if (str_contains($filePath, DIRECTORY_SEPARATOR . 'Views' . DIRECTORY_SEPARATOR)) {
                continue;
            }

            $className = $this->getClassName($filePath, $directory, $namespacePrefix);
            
            if ($className && class_exists($className)) {
                $role = $this->detectRole($filePath);
                $metadata[$className] = array_merge(['role' => $role], $this->extractMetadata($className));
            }
        }

        return $metadata;
    }

    private function detectRole(string $filePath): string
    {
        if (str_contains($filePath, DIRECTORY_SEPARATOR . 'Controllers' . DIRECTORY_SEPARATOR)) {
            return 'controller';
        }
        if (str_contains($filePath, DIRECTORY_SEPARATOR . 'Models' . DIRECTORY_SEPARATOR)) {
            return 'model';
        }
        if (str_contains($filePath, DIRECTORY_SEPARATOR . 'Services' . DIRECTORY_SEPARATOR)) {
            return 'service';
        }
        if (str_contains($filePath, DIRECTORY_SEPARATOR . 'Database' . DIRECTORY_SEPARATOR . 'Migrations' . DIRECTORY_SEPARATOR)) {
            return 'migration';
        }
        if (str_contains($filePath, DIRECTORY_SEPARATOR . 'Database' . DIRECTORY_SEPARATOR . 'Seeders' . DIRECTORY_SEPARATOR)) {
            return 'seeder';
        }
        return 'class';
    }

    private function getClassName(string $filePath, string $baseDir, string $namespacePrefix): ?string
    {
        $relativePath = str_replace(realpath($baseDir), '', realpath($filePath));
        $relativePath = ltrim($relativePath, DIRECTORY_SEPARATOR);
        $className = str_replace([DIRECTORY_SEPARATOR, '.php'], ['\\', ''], $relativePath);
        
        return rtrim($namespacePrefix, '\\') . '\\' . $className;
    }

    /**
     * @param string $className
     * @return array{methods: array<string, array{parameters: array, returnType: ?string}>, properties: string[]}
     */
    private function extractMetadata(string $className): array
    {
        try {
            $reflection = new \ReflectionClass($className);
            
            $methods = [];
            foreach ($reflection->getMethods(\ReflectionMethod::IS_PUBLIC) as $method) {
                if ($method->isConstructor() || $method->isDestructor()) {
                    continue;
                }

                $params = [];
                foreach ($method->getParameters() as $param) {
                    $params[] = [
                        'name' => $param->getName(),
                        'type' => $param->hasType() ? $this->formatType($param->getType()) : null,
                        'optional' => $param->isOptional(),
                    ];
                }

                $methods[$method->getName()] = [
                    'parameters' => $params,
                    'returnType' => $method->hasReturnType() ? $this->formatType($method->getReturnType()) : null,
                ];
            }

            $properties = [];
            foreach ($reflection->getProperties(\ReflectionProperty::IS_PUBLIC) as $property) {
                $properties[] = $property->getName();
            }

            return [
                'methods' => $methods,
                'properties' => $properties,
            ];
        } catch (\ReflectionException $e) {
            return [
                'methods' => [],
                'properties' => [],
            ];
        }
    }

    private function formatType(?\ReflectionType $type): ?string
    {
        if ($type instanceof \ReflectionNamedType) {
            return $type->getName();
        }
        if ($type instanceof \ReflectionUnionType) {
            return implode('|', array_map(fn($t) => $t->getName(), $type->getTypes()));
        }
        if ($type instanceof \ReflectionIntersectionType) {
            return implode('&', array_map(fn($t) => $t->getName(), $type->getTypes()));
        }
        return (string)$type;
    }
}
