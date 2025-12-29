# Writing and Running Migrations

Migrations are module-first and live alongside your module code.

- Location per module: `Modules/<Module>/Database/Migrations/`
- File name pattern: `YYYYMMDDHHMMSS_Description.php`
- Each file defines a class that extends `Ishmael\Core\Database\Migrations\BaseMigration` and implements `up()` and `down()`.
- Inside `up()`/`down()` you can use adapter helpers or execute raw SQL via `$this->sql(...)`.

## Example

```php
<?php
use Ishmael\Core\Database\Migrations\BaseMigration;
use Ishmael\Core\Database\Schema\TableDefinition;
use Ishmael\Core\Database\Schema\ColumnDefinition;

class CreatePostsTable extends BaseMigration
{
    public function up(): void
    {
        $def = new TableDefinition('posts', [
            new ColumnDefinition('id', 'INTEGER', nullable: false),
            new ColumnDefinition('title', 'VARCHAR', length: 200, nullable: false),
        ]);
        $this->adapter()->createTable($def);
    }

    public function down(): void
    {
        $this->adapter()->dropTable('posts');
    }
}
```

## Running migrations (programmatic API)

Use `MigrationRunner` to apply, rollback, reset, and inspect status.

```php
use Ishmael\Core\DatabaseAdapters\DatabaseAdapterInterface;
use Ishmael\Core\Database\Migrations\MigrationRunner;

$adapter = /* resolve connected adapter */;
$runner = new MigrationRunner($adapter, app(Psr\Log\LoggerInterface::class));

// Apply all pending across all modules
$runner->migrate();

// Apply first 2 pending for the Blog module
$runner->migrate('Blog', steps: 2);

// Pretend mode (no changes; logs what would run)
$runner->migrate('Blog', pretend: true);

// Roll back last Blog migration
$runner->rollback('Blog', steps: 1);

// Reset all (rollback everything)
$runner->reset();

// Get status
$status = $runner->status();
```

## Bookkeeping table: ishmael_migrations

Schema (logical):
- id (auto increment primary key)
- module (string)
- name (string; migration file name)
- batch (integer)
- applied_at (datetime)

The runner auto-creates this table on first use.

## Transactions

If the active adapter reports `supportsTransactionalDdl() === true`, each migration
is executed inside its own transaction. Otherwise, the runner logs a warning and
executes without wrapping in a transaction.

## Guidelines

- Keep migrations explicit and reversible. Avoid destructive changes unless you fully understand the impact.
- For complex/unsafe schema changes, write dedicated migrations rather than relying on SchemaManager.
- Test migrations against the target engine (SQLite/MySQL) in development before deployment.
