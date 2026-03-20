<?php

declare(strict_types=1);

namespace Ishmael\McpServer\Tools;

use Ishmael\McpServer\Contracts\Tool;
use Ishmael\McpServer\Project\ProjectContext;
use ZipArchive;

final class FeaturePackUnpackTool implements Tool
{
    private ?ProjectContext $context;

    public function __construct(?ProjectContext $context = null)
    {
        $this->context = $context;
    }

    public function getName(): string
    {
        return 'ish:featurePack:unpack';
    }

    public function getDescription(): string
    {
        return 'Unpack a downloaded feature pack ZIP into the project Modules directory. Validates contents before extraction and reports the module name, composer package, and whether migrations are present.';
    }

    public function getInputSchema(): array
    {
        return [
            'type'                 => 'object',
            'additionalProperties' => false,
            'required'             => ['zip_path'],
            'properties'           => [
                'zip_path' => [
                    'type'        => 'string',
                    'description' => 'Absolute path to the downloaded feature pack ZIP file.',
                ],
                'modules_dir' => [
                    'type'        => 'string',
                    'description' => 'Destination Modules directory. Defaults to {project_root}/Modules.',
                ],
                'overwrite' => [
                    'type'        => 'boolean',
                    'description' => 'Overwrite an existing module directory of the same name. Defaults to false.',
                ],
            ],
        ];
    }

    public function getOutputSchema(): array
    {
        return [
            'type'       => 'object',
            'required'   => ['success'],
            'properties' => [
                'success'          => ['type' => 'boolean'],
                'module_name'      => ['type' => 'string', 'description' => 'The module directory name extracted from the pack.'],
                'composer_package' => ['type' => 'string', 'description' => 'The composer package name from the pack\'s composer.json.'],
                'composer_version' => ['type' => 'string', 'description' => 'The version declared in the pack\'s composer.json.'],
                'has_migrations'   => ['type' => 'boolean', 'description' => 'Whether the pack contains a database/migrations directory.'],
                'extract_path'     => ['type' => 'string', 'description' => 'Absolute path to the unpacked module directory.'],
                'errors'           => ['type' => 'array', 'items' => ['type' => 'string']],
                'warnings'         => ['type' => 'array', 'items' => ['type' => 'string']],
            ],
        ];
    }

    public function execute(array $input): array
    {
        $zipPath = trim($input['zip_path'] ?? '');

        if ($zipPath === '' || !is_file($zipPath)) {
            return ['success' => false, 'errors' => ["ZIP file not found: $zipPath"], 'warnings' => []];
        }

        $modulesDir = $this->resolveModulesDir($input['modules_dir'] ?? null);
        if ($modulesDir === null) {
            return ['success' => false, 'errors' => ['Could not determine Modules directory. Pass modules_dir explicitly or run from an Ishmael project root.'], 'warnings' => []];
        }

        $overwrite = (bool)($input['overwrite'] ?? false);

        $zip = new ZipArchive();
        $opened = $zip->open($zipPath);
        if ($opened !== true) {
            return ['success' => false, 'errors' => ['Could not open ZIP archive (ZipArchive error code ' . $opened . ').'], 'warnings' => []];
        }

        $inspection = $this->inspect($zip);
        $zip->close();

        if (!empty($inspection['errors'])) {
            return ['success' => false, 'errors' => $inspection['errors'], 'warnings' => $inspection['warnings']];
        }

        $moduleName = $inspection['module_name'];
        $extractTo  = rtrim($modulesDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $moduleName;

        if (is_dir($extractTo) && !$overwrite) {
            return [
                'success'  => false,
                'errors'   => ["Module directory already exists: $extractTo. Pass overwrite=true to replace it."],
                'warnings' => $inspection['warnings'],
            ];
        }

        if (is_dir($extractTo) && $overwrite) {
            $this->removeDir($extractTo);
        }

        if (!is_dir($modulesDir) && !mkdir($modulesDir, 0755, true)) {
            return ['success' => false, 'errors' => ["Could not create Modules directory: $modulesDir"], 'warnings' => $inspection['warnings']];
        }

        $zip2 = new ZipArchive();
        $zip2->open($zipPath);

        $prefix = $inspection['prefix'];

        for ($i = 0; $i < $zip2->numFiles; $i++) {
            $name = $zip2->getNameIndex($i);

            if ($prefix !== '' && !str_starts_with($name, $prefix)) {
                continue;
            }

            $relative = $prefix !== '' ? substr($name, strlen($prefix)) : $name;

            if ($relative === '' || $relative === '/') {
                continue;
            }

            $target = $extractTo . DIRECTORY_SEPARATOR . ltrim(str_replace('/', DIRECTORY_SEPARATOR, $relative), DIRECTORY_SEPARATOR);

            if (str_ends_with($name, '/')) {
                if (!is_dir($target)) {
                    mkdir($target, 0755, true);
                }
                continue;
            }

            $targetDir = dirname($target);
            if (!is_dir($targetDir)) {
                mkdir($targetDir, 0755, true);
            }

            $stream = $zip2->getStream($name);
            if ($stream !== false) {
                file_put_contents($target, stream_get_contents($stream));
                fclose($stream);
            }
        }

        $zip2->close();

        return [
            'success'          => true,
            'module_name'      => $moduleName,
            'composer_package' => $inspection['composer_package'],
            'composer_version' => $inspection['composer_version'],
            'has_migrations'   => $inspection['has_migrations'],
            'extract_path'     => $extractTo,
            'errors'           => [],
            'warnings'         => $inspection['warnings'],
        ];
    }

    /**
     * Inspect the ZIP without extracting it.
     *
     * @return array{module_name: string, prefix: string, composer_package: string, composer_version: string, has_migrations: bool, errors: string[], warnings: string[]}
     */
    public function inspect(ZipArchive $zip): array
    {
        $errors   = [];
        $warnings = [];
        $names    = [];

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $names[] = $zip->getNameIndex($i);
        }

        // Detect whether the archive has a single top-level directory wrapper
        // (common when zipping a folder) or files directly at root level.
        $topDirs = [];
        foreach ($names as $n) {
            $parts = explode('/', $n, 2);
            if ($parts[0] !== '') {
                $topDirs[$parts[0]] = true;
            }
        }

        $prefix     = '';
        $moduleName = '';

        if (count($topDirs) === 1) {
            $candidate = array_key_first($topDirs);
            // Treat as wrapper if module.php lives inside it
            $hasMod = in_array($candidate . '/module.php', $names, true)
                   || in_array($candidate . '/composer.json', $names, true);
            if ($hasMod) {
                $prefix     = $candidate . '/';
                $moduleName = $candidate;
            }
        }

        // Find module.php at expected location
        $modulePHP = $prefix . 'module.php';
        if (!in_array($modulePHP, $names, true)) {
            $errors[] = "module.php not found in archive (looked for $modulePHP).";
        }

        // Find composer.json
        $composerJson = $prefix . 'composer.json';
        $composerPackage = '';
        $composerVersion = '';

        if (!in_array($composerJson, $names, true)) {
            $errors[] = "composer.json not found in archive (looked for $composerJson).";
        } else {
            $content = $zip->getFromName($composerJson);
            $data    = $content !== false ? json_decode($content, true) : null;

            if (!is_array($data)) {
                $errors[] = 'composer.json in archive is not valid JSON.';
            } else {
                $composerPackage = (string)($data['name'] ?? '');
                $composerVersion = (string)($data['version'] ?? '');

                if ($composerPackage === '') {
                    $warnings[] = 'composer.json does not declare a "name" field.';
                }

                if ($moduleName === '' && isset($data['name'])) {
                    $parts      = explode('/', $data['name'], 2);
                    $moduleName = $parts[1] ?? $parts[0];
                    // Convert kebab-case to PascalCase for the module directory name
                    $moduleName = str_replace('-', '', ucwords($moduleName, '-'));
                }
            }
        }

        if ($moduleName === '') {
            $errors[] = 'Could not determine module name from archive structure or composer.json.';
        }

        // Detect migrations directory
        $hasMigrations = false;
        foreach ($names as $n) {
            if (str_contains($n, 'database/migrations/') || str_contains($n, 'Database/Migrations/')) {
                $hasMigrations = true;
                break;
            }
        }

        return [
            'module_name'      => $moduleName,
            'prefix'           => $prefix,
            'composer_package' => $composerPackage,
            'composer_version' => $composerVersion,
            'has_migrations'   => $hasMigrations,
            'errors'           => $errors,
            'warnings'         => $warnings,
        ];
    }

    private function resolveModulesDir(?string $override): ?string
    {
        if ($override !== null && $override !== '') {
            return $override;
        }

        if ($this->context !== null && $this->context->getRoot() !== null) {
            return $this->context->getRoot() . DIRECTORY_SEPARATOR . 'Modules';
        }

        return null;
    }

    private function removeDir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $items = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($items as $item) {
            $item->isDir() ? rmdir($item->getRealPath()) : unlink($item->getRealPath());
        }

        rmdir($dir);
    }
}
