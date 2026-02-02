<?php

declare(strict_types=1);

namespace Ishmael\McpServer\Providers;

use Ishmael\McpServer\Contracts\ResourceProvider;
use Ishmael\McpServer\Support\DatabaseConnectionFactory;
use PDO;
use Exception;

/**
 * Provides an authoritative schema map of the live database.
 */
final class DatabaseSchemaProvider implements ResourceProvider
{
    private DatabaseConnectionFactory $connectionFactory;

    public function __construct(DatabaseConnectionFactory $connectionFactory)
    {
        $this->connectionFactory = $connectionFactory;
    }

    public function listResources(): array
    {
        return [
            [
                'uri' => 'ish://database/schema',
                'name' => 'Database Schema Map',
                'description' => 'Authoritative JSON representation of the live database schema (tables, columns, keys, and foreign keys).',
                'mimeType' => 'application/json',
            ]
        ];
    }

    public function readResource(string $uri): ?string
    {
        if ($uri !== 'ish://database/schema') {
            return null;
        }

        try {
            $pdo = $this->connectionFactory->getConnection();
            $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);

            $schema = [
                'database' => [
                    'engine' => $driver,
                    'tables' => $this->introspectTables($pdo, $driver)
                ]
            ];

            return json_encode($schema, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        } catch (Exception $e) {
            return json_encode([
                'error' => 'Failed to introspect database schema',
                'message' => $e->getMessage()
            ], JSON_PRETTY_PRINT);
        }
    }

    private function introspectTables(PDO $pdo, string $driver): array
    {
        $tables = [];
        
        if ($driver === 'sqlite') {
            $stmt = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%'");
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $tableName = $row['name'];
                $tables[$tableName] = [
                    'owner' => $this->inferOwner($tableName),
                    'columns' => $this->getSqliteColumns($pdo, $tableName),
                    'foreign_keys' => $this->getSqliteForeignKeys($pdo, $tableName),
                    'indexes' => $this->getSqliteIndexes($pdo, $tableName),
                ];
            }
        } elseif ($driver === 'mysql') {
            // MySQL implementation would go here
            $tables = $this->introspectMysql($pdo);
        }

        return $tables;
    }

    private function getSqliteColumns(PDO $pdo, string $table): array
    {
        $columns = [];
        $stmt = $pdo->query("PRAGMA table_info(\"{$table}\")");
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $name = $row['name'];
            $columns[$name] = [
                'type' => strtolower($row['type']),
                'nullable' => $row['notnull'] === 0,
                'default' => $row['dflt_value'],
                'primary_key' => $row['pk'] > 0,
            ];
        }
        return $columns;
    }

    private function getSqliteForeignKeys(PDO $pdo, string $table): array
    {
        $fks = [];
        $stmt = $pdo->query("PRAGMA foreign_key_list(\"{$table}\")");
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $fks[] = [
                'local_column' => $row['from'],
                'referenced_table' => $row['table'],
                'referenced_column' => $row['to'],
                'on_update' => $row['on_update'],
                'on_delete' => $row['on_delete'],
            ];
        }
        return $fks;
    }

    private function getSqliteIndexes(PDO $pdo, string $table): array
    {
        $indexes = [];
        $stmt = $pdo->query("PRAGMA index_list(\"{$table}\")");
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $indexName = $row['name'];
            $indexInfo = $pdo->query("PRAGMA index_info(\"{$indexName}\")")->fetchAll(PDO::FETCH_ASSOC);
            
            $columns = [];
            foreach ($indexInfo as $info) {
                $columns[] = $info['name'];
            }

            $indexes[$indexName] = [
                'unique' => $row['unique'] === 1,
                'columns' => $columns,
            ];
        }
        return $indexes;
    }

    private function introspectMysql(PDO $pdo): array
    {
        // Basic MySQL introspection as a placeholder
        $tables = [];
        $stmt = $pdo->query("SHOW TABLES");
        $dbName = $pdo->query("SELECT DATABASE()")->fetchColumn();
        
        while ($row = $stmt->fetch(PDO::FETCH_NUM)) {
            $tableName = $row[0];
            $tables[$tableName] = [
                'owner' => $this->inferOwner($tableName),
                'columns' => $this->getMysqlColumns($pdo, $dbName, $tableName),
                // FKs and Indexes for MySQL could be added here
            ];
        }
        return $tables;
    }

    private function getMysqlColumns(PDO $pdo, string $db, string $table): array
    {
        $columns = [];
        $stmt = $pdo->prepare("SELECT COLUMN_NAME, DATA_TYPE, IS_NULLABLE, COLUMN_DEFAULT, COLUMN_KEY, EXTRA 
                               FROM information_schema.COLUMNS 
                               WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?");
        $stmt->execute([$db, $table]);
        
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $name = $row['COLUMN_NAME'];
            $columns[$name] = [
                'type' => $row['DATA_TYPE'],
                'nullable' => $row['IS_NULLABLE'] === 'YES',
                'default' => $row['COLUMN_DEFAULT'],
                'primary_key' => $row['COLUMN_KEY'] === 'PRI',
                'auto_increment' => str_contains($row['EXTRA'], 'auto_increment'),
            ];
        }
        return $columns;
    }

    private function inferOwner(string $tableName): string
    {
        // Simple heuristic: core tables are often users, roles, feature_packs, etc.
        $coreTables = ['users', 'roles', 'user_roles', 'feature_packs', 'feature_pack_dependencies', 'settings', 'jobs', 'audit_logs', 'failed_jobs', 'migrations'];
        if (in_array($tableName, $coreTables, true)) {
            return 'core';
        }

        // Check if it belongs to a known feature pack (requires more metadata, for now generic)
        return 'unknown';
    }
}
