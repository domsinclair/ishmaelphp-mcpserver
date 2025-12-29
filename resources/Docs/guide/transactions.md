# Transactions

This guide covers ergonomic transaction helpers and safe parameter binding in Ishmael PHP.

- Namespace: Ishmael\Core
- Primary class: Database

## Quick start

```php
use Ishmael\Core\Database;

// Initialize once at bootstrap with your config array
Database::init($config);

// Run a block in a transaction
$result = Database::transaction(function () {
    Database::execute('INSERT INTO accounts (name, is_active) VALUES (:n, :a)', [
        ':n' => 'Acme Co',
        ':a' => true, // normalized to 1 for drivers that require it
    ]);

    return Database::query('SELECT COUNT(*) AS c FROM accounts')->fetch();
});
```

- Return values from your callback are returned by `transaction()`.
- Any exception thrown inside the callback causes an automatic rollback and is re‑thrown.

## Nested transactions

`Database::transaction()` defers to the adapter/driver semantics for nesting:

- If a transaction is already active (`adapter()->inTransaction() === true`), no new transaction is started.
- Your callback executes inline and no commit/rollback is performed at this level. The outer scope controls the boundary.
- Savepoints are not emulated by the helper. If you need savepoints, use the adapter/connection directly.

This keeps behavior predictable across engines (SQLite/MySQL/Postgres) without introducing hidden abstractions.

## Retrying on deadlocks or serialization failures

Some workloads are prone to transient conflicts. Use `retryTransaction()` to automatically retry when the error is likely transient:

```php
use Ishmael\Core\Database;

$val = Database::retryTransaction(attempts: 3, sleepMs: 50, fn: function () {
    // Your transactional work here
    Database::execute('UPDATE inventory SET qty = qty - 1 WHERE id = :id', [':id' => 42]);
    return true;
});
```

- Retries on common SQLSTATEs like `40001` (serialization failure) and `40P01` (deadlock), or MySQL codes `1213`/`1205`.
- Sleeps `sleepMs` between attempts.
- Non‑retryable exceptions are rethrown immediately.

## Prepared statements and safe parameter binding

The Database facade exposes small helpers to streamline prepared statements and ensure safe, normalized bindings:

- `Database::prepare(string $sql): PDOStatement` — low‑level access to prepare a statement.
- `Database::query(string $sql, array $params = []): Result` — prepares, binds, executes, and returns a lightweight `Result` wrapper for fetching rows.
- `Database::execute(string $sql, array $params = []): int` — prepares, binds, executes, and returns affected rows for DML.
- `Database::normalizeParams(array $params): array` — converts values into driver‑friendly representations (used internally by `query`/`execute`).

### Parameter normalization rules

- `DateTimeInterface` ➜ ISO‑8601 string (`->format('c')`).
- `bool` ➜ integer `1`/`0` (many drivers/drivers prefer numeric for tinyint/bool columns).
- Objects with `__toString()` ➜ cast to string.
- `null` stays `null`.
- All other scalar values are passed as‑is.

You can pass parameters as either named (recommended) or positional arrays:

```php
Database::execute('INSERT INTO posts (title, published_at) VALUES (:t, :p)', [
    ':t' => 'Hello',
    ':p' => new \DateTimeImmutable('now'), // normalized to ISO‑8601
]);

// Positional
Database::execute('DELETE FROM posts WHERE id = ?', [123]);
```

## Adapter capabilities

The helpers build on the connected adapter (`Database::adapter()`), which implements `DatabaseAdapterInterface` including:

- `beginTransaction()`, `commit()`, `rollBack()`, `inTransaction()`, and `supportsTransactionalDdl()`.

When running migrations, the migration runner uses adapter capabilities to decide whether to wrap DDL in a transaction. See the Migrations guide for details.

## Error handling notes

- The helper will always prefer throwing your original exception. If a rollback itself throws, it is suppressed to preserve the original error context.
- `retryTransaction()` unwraps `PDOException` error codes and message text to detect commonly transient conditions; this is heuristic by design.
