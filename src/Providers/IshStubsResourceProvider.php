<?php

declare(strict_types=1);

namespace Ishmael\McpServer\Providers;

use Ishmael\McpServer\Contracts\ResourceProvider;
use Ishmael\McpServer\Project\PathSandbox;
use Ishmael\McpServer\Project\ProjectContext;
use Ishmael\McpServer\Support\StubDataCollector;

/**
 * Exposes framework stubs as resources (ish://stubs/{path}).
 */
final class IshStubsResourceProvider implements ResourceProvider
{
    private PathSandbox $sandbox;
    private string $coreRoot;
    private ?StubDataCollector $collector;

    public function __construct(PathSandbox $sandbox, string $coreRoot, ?ProjectContext $context = null)
    {
        $this->sandbox = $sandbox;
        $this->coreRoot = $coreRoot;
        $this->collector = $context ? new StubDataCollector($context) : null;
    }

    public function listResources(): array
    {
        $stubsDir = $this->coreRoot . DIRECTORY_SEPARATOR . 'Resources' . DIRECTORY_SEPARATOR . 'stubs';
        $items = [];

        // Virtual dynamic stubs
        if ($this->collector) {
            $items[] = [
                'uri' => 'ish://stubs/Project/Tables.php',
                'name' => 'Project Tables (Dynamic)',
                'description' => 'Dynamic stub containing constants for all database tables',
                'mimeType' => 'application/x-php',
            ];
            $items[] = [
                'uri' => 'ish://stubs/Project/Config.php',
                'name' => 'Project Config (Dynamic)',
                'description' => 'Dynamic stub containing constants for all available config keys',
                'mimeType' => 'application/x-php',
            ];
            $items[] = [
                'uri' => 'ish://stubs/Project/Conventions.php',
                'name' => 'Project Conventions (Dynamic)',
                'description' => 'Dynamic stub defining the expected structure of Models and Services based on Ishmael conventions',
                'mimeType' => 'application/x-php',
            ];
        }

        if (!is_dir($stubsDir)) {
            return $items;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($stubsDir, \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            /** @var \SplFileInfo $file */
            if ($file->isDir()) {
                continue;
            }

            $relPath = str_replace($stubsDir . DIRECTORY_SEPARATOR, '', $file->getPathname());
            $relPath = str_replace(DIRECTORY_SEPARATOR, '/', $relPath);

            $items[] = [
                'uri' => 'ish://stubs/' . $relPath,
                'name' => 'Stub: ' . $relPath,
                'description' => 'Ishmael framework template stub',
                'mimeType' => 'text/plain',
                'path' => $file->getPathname(), // Internal use for reading
            ];
        }

        return $items;
    }

    public function readResource(string $uri): ?string
    {
        if (!str_starts_with($uri, 'ish://stubs/')) {
            return null;
        }

        if ($this->collector) {
            if ($uri === 'ish://stubs/Project/Tables.php') {
                return $this->generateTablesStub();
            }
            if ($uri === 'ish://stubs/Project/Config.php') {
                return $this->generateConfigStub();
            }
            if ($uri === 'ish://stubs/Project/Conventions.php') {
                return $this->generateConventionsStub();
            }
        }

        $relPath = substr($uri, 12);
        $stubsDir = $this->coreRoot . DIRECTORY_SEPARATOR . 'Resources' . DIRECTORY_SEPARATOR . 'stubs';
        $fullPath = $stubsDir . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relPath);

        if (is_file($fullPath)) {
            return file_get_contents($fullPath);
        }

        return null;
    }

    private function generateTablesStub(): string
    {
        $tables = $this->collector ? $this->collector->getTables() : [];
        $php = "<?php\n\n/**\n * Auto-generated stub for project database tables.\n */\n\nclass Tables\n{\n";
        foreach ($tables as $table) {
            $constName = strtoupper(preg_replace('/[^a-zA-Z0-9]/', '_', $table));
            $php .= "    public const " . $constName . " = '" . $table . "';\n";
        }
        $php .= "}\n";
        return $php;
    }

    private function generateConfigStub(): string
    {
        $keys = $this->collector ? $this->collector->getConfigKeys() : [];
        $php = "<?php\n\n/**\n * Auto-generated stub for project configuration keys.\n */\n\nclass ConfigKeys\n{\n";
        foreach ($keys as $key) {
            $constName = strtoupper(preg_replace('/[^a-zA-Z0-9]/', '_', $key));
            $php .= "    public const " . $constName . " = '" . $key . "';\n";
        }
        $php .= "}\n";
        return $php;
    }

    private function generateConventionsStub(): string
    {
        return <<<'PHP'
<?php

/**
 * Auto-generated stub defining Ishmael framework conventions.
 * Use this as a reference for expected class structures and naming.
 */

namespace Ishmael\Conventions;

interface ModelConvention
{
    /**
     * Primary key naming convention.
     */
    public const PRIMARY_KEY_PATTERN = '{singular_table}_id';

    /**
     * Timestamp column names.
     */
    public const CREATED_AT = 'created_at';
    public const UPDATED_AT = 'updated_at';

    /**
     * Auditing column names.
     */
    public const CREATED_BY = 'created_by';
    public const UPDATED_BY = 'updated_by';

    /**
     * Soft delete column name.
     */
    public const DELETED_AT = 'deleted_at';
}

interface ControllerConvention
{
    /**
     * Expected suffix for all controller classes.
     */
    public const SUFFIX = 'Controller';
}

interface ServiceConvention
{
    /**
     * Expected suffix for all service classes.
     */
    public const SUFFIX = 'Service';
}

class DatabaseConventions
{
    public const DEFAULT_ENGINE = 'MySQL';
    public const TABLE_NAMING = 'snake_case_plural';
    public const PRIMARY_KEY_PATTERN = '{singular_table}_id';
    public const FOREIGN_KEY_PATTERN = '{singular_table}_id';
    public const CASE_POLICY = 'snake_case_only_for_tables';
}
PHP;
    }
}
