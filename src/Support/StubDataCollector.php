<?php

declare(strict_types=1);

namespace Ishmael\McpServer\Support;

use Ishmael\McpServer\Project\ProjectContext;

/**
 * Aggregates project-specific metadata for dynamic stub generation.
 */
final class StubDataCollector
{
    private ProjectContext $context;

    public function __construct(ProjectContext $context)
    {
        $this->context = $context;
    }

    /**
     * @return string[]
     */
    public function getTables(): array
    {
        $root = $this->context->getRoot();
        if ($root === null) {
            return [];
        }

        $dbConfigFile = $root . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'database.php';
        if (!is_file($dbConfigFile)) {
            return [];
        }

        $this->ensureBootstrapped();

        try {
            $dbConfig = require $dbConfigFile;
            if (!is_array($dbConfig)) {
                return [];
            }

            // We need to initialize the database if it hasn't been already
            if (class_exists('Ishmael\Core\Database')) {
                \Ishmael\Core\Database::init($dbConfig);
                $adapter = \Ishmael\Core\Database::adapter();
                $className = get_class($adapter);

                $q = '';
                if (str_contains($className, 'SQLiteAdapter')) {
                    $q = 'SELECT name FROM sqlite_master WHERE type="table" AND name NOT LIKE "sqlite_%"';
                } elseif (str_contains($className, 'MySQLAdapter')) {
                    $q = 'SHOW TABLES';
                } else {
                    // Assume Postgres
                    $q = "SELECT table_name FROM information_schema.tables WHERE table_schema = 'public'";
                }

                $tables = [];
                foreach (\Ishmael\Core\Database::query($q, [])->fetchAll() as $row) {
                    $tables[] = (string)array_values($row)[0];
                }
                return $tables;
            }
        } catch (\Throwable $e) {
            // Log error to stderr and return empty
            fwrite(STDERR, "[StubDataCollector] Error collecting tables: " . $e->getMessage() . "\n");
        }

        return [];
    }

    /**
     * @return string[]
     */
    public function getConfigKeys(): array
    {
        $root = $this->context->getRoot();
        if ($root === null) {
            return [];
        }

        $configDir = $root . DIRECTORY_SEPARATOR . 'config';
        if (!is_dir($configDir)) {
            return [];
        }

        $keys = [];
        foreach (glob($configDir . DIRECTORY_SEPARATOR . '*.php') as $file) {
            $name = basename($file, '.php');
            try {
                $data = require $file;
                if (is_array($data)) {
                    $this->flattenKeys($name, $data, $keys);
                }
            } catch (\Throwable $e) {
                fwrite(STDERR, "[StubDataCollector] Error parsing config file {$file}: " . $e->getMessage() . "\n");
            }
        }

        return array_unique($keys);
    }

    private function flattenKeys(string $prefix, array $data, array &$keys): void
    {
        foreach ($data as $key => $value) {
            if (!is_string($key)) {
                continue;
            }
            $fullKey = $prefix . '.' . $key;
            $keys[] = $fullKey;
            if (is_array($value)) {
                $this->flattenKeys($fullKey, $value, $keys);
            }
        }
    }

    private function ensureBootstrapped(): void
    {
        $root = $this->context->getRoot();
        if ($root === null) {
            return;
        }

        if (!defined('ISH_BOOTSTRAP_ONLY')) {
            define('ISH_BOOTSTRAP_ONLY', true);
        }
        if (!defined('ISH_APP_BASE')) {
            define('ISH_APP_BASE', $root);
        }

        $autoload = $root . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php';
        if (is_file($autoload)) {
            require_once $autoload;
        }
    }
}
