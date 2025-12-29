# How‑To: Implement a new Adapter

This guide walks you through creating a Database Adapter that conforms to Ishmael PHP’s Database Adapter Contract.

Prerequisites
- Familiarity with PDO and your target database engine (SQLite, MySQL, Postgres, etc.).
- Read the reference: Database Adapter Contract.

1) Create the class
- Namespace: Ishmael\Core\DatabaseAdapters
- Implements: DatabaseAdapterInterface
- Recommended filename: IshmaelPHP-Core/app/Core/DatabaseAdapters/<EngineName>Adapter.php

Skeleton
```php
<?php
declare(strict_types=1);

namespace Ishmael\Core\DatabaseAdapters;

use PDO;
use PDOException;
use Ishmael\Core\Database\Result;
use Ishmael\Core\Database\Schema\TableDefinition;
use Ishmael\Core\Database\Schema\ColumnDefinition;
use Ishmael\Core\Database\Schema\IndexDefinition;

final class AcmeDBAdapter implements DatabaseAdapterInterface
{
    private ?PDO $pdo = null;

    public function connect(array $config): PDO
    {
        // Build DSN from $config and create PDO with sane defaults
        $dsn = $config['dsn'] ?? '';
        $user = $config['user'] ?? null;
        $pass = $config['password'] ?? null;
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ];
        $this->pdo = new PDO($dsn, (string)$user, (string)$pass, $options);
        return $this->pdo;
    }

    public function disconnect(): void { $this->pdo = null; }
    public function isConnected(): bool { return $this->pdo instanceof PDO; }

    public function query(string $sql, array $params = []): Result
    {
        $stmt = $this->pdoOrFail()->prepare($sql);
        $stmt->execute($params);
        return Result::fromPdoStatement($stmt);
    }

    public function execute(string $sql, array $params = []): int
    {
        $stmt = $this->pdoOrFail()->prepare($sql);
        $stmt->execute($params);
        return $stmt->rowCount();
    }

    public function lastInsertId(?string $sequence = null): string
    {
        return $this->pdoOrFail()->lastInsertId($sequence ?? '');
    }

    public function beginTransaction(): void { $this->pdoOrFail()->beginTransaction(); }
    public function commit(): void { $this->pdoOrFail()->commit(); }
    public function rollBack(): void { $this->pdoOrFail()->rollBack(); }
    public function inTransaction(): bool { return $this->pdoOrFail()->inTransaction(); }
    public function supportsTransactionalDdl(): bool { return in_array(self::CAP_TRANSACTIONAL_DDL, $this->getCapabilities(), true); }

    public function createTable(TableDefinition $def): void { /* emit CREATE TABLE ... */ }
    public function dropTable(string $table): void { /* emit DROP TABLE ... */ }

    public function addColumn(string $table, ColumnDefinition $def): void { /* emit ALTER TABLE ... ADD COLUMN ... */ }
    public function alterColumn(string $table, ColumnDefinition $def): void { /* emit ALTER TABLE ... ALTER/MODIFY COLUMN ... */ }
    public function dropColumn(string $table, string $column): void { /* emit ALTER TABLE ... DROP COLUMN ... */ }

    public function addIndex(string $table, IndexDefinition $def): void { /* emit CREATE INDEX ... */ }
    public function dropIndex(string $table, string $name): void { /* emit DROP INDEX ... */ }

    public function tableExists(string $table): bool { /* query information_schema/pragma */ return false; }
    public function columnExists(string $table, string $column): bool { /* query metadata */ return false; }
    public function getTableDefinition(string $table): TableDefinition { /* introspect */ throw new \RuntimeException('Not implemented'); }

    public function runSql(string $sql): void { $this->pdoOrFail()->exec($sql); }

    public function getCapabilities(): array
    {
        return [
            // Return only what your engine truly supports
            self::CAP_TRANSACTIONAL_DDL,
            // self::CAP_ALTER_TABLE_ALTER_COLUMN,
            // self::CAP_ALTER_TABLE_RENAME_COLUMN,
            // self::CAP_DROP_COLUMN,
            // self::CAP_ADD_COLUMN_AFTER,
            // self::CAP_PARTIAL_INDEX,
            // self::CAP_CONCURRENT_INDEX,
            // self::CAP_SCHEMAS,
        ];
    }

    private function pdoOrFail(): PDO
    {
        if (!$this->pdo) {
            throw new \RuntimeException('Adapter is not connected');
        }
        return $this->pdo;
    }
}
```

2) Capability flags
- Only advertise flags your engine supports. SchemaManager will rely on these.
- Example: SQLite typically cannot drop or alter columns natively; omit those flags.

3) Schema operations
- Implement createTable/addColumn/alterColumn/dropColumn with the correct SQL for your engine.
- Quote identifiers correctly for your engine (e.g., `"name"` vs `\`name\``) and consider schemas/namespaces.

4) Transactions
- If your engine supports transactional DDL, ensure supportsTransactionalDdl() returns true (either via the flag or a direct check).

5) Register the adapter in the factory (optional)
- If using DatabaseAdapterFactory, add a mapping so it can be resolved by name.
  - File: IshmaelPHP-Core/app/Core/DatabaseAdapters/DatabaseAdapterFactory.php
  - Add to the registry array: 'acmedb' => AcmeDBAdapter::class

6) Testing
- Write a minimal conformance test to cover:
  - connect()/disconnect()/isConnected()
  - query()/execute()/lastInsertId()
  - Transactions begin/commit/rollback/inTransaction()
  - Capability flags and supportsTransactionalDdl()
  - At least one schema op (createTable + dropTable)
- See IshmaelPHP-Core/tests/DatabaseAdapterFactoryTest.php for factory expectations.

7) Configuration example
```env
DB_CONNECTION=acmedb
DB_DSN=acme:host=localhost;dbname=app
DB_USER=app
DB_PASSWORD=secret
```

Troubleshooting
- Verify your DSN and that PDO driver is installed.
- Check error messages and ensure you throw exceptions for unsupported operations with guidance.

References
- Reference: Database Adapter Contract (../reference/core-api/DatabaseAdapterInterface.md)


---

## Related reference
- Reference: [Core API (Markdown stubs)](../reference/core-api/_index.md)
- Reference: [Config Keys](../reference/config-keys.md)
