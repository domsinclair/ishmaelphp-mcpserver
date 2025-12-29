# Soft Deletion

Soft deletion marks records as deleted without physically removing them from the database. This enables recovery, audit trails, and historical references while keeping your schema simple.

Concept
- Add a nullable deleted_at timestamp column (and optionally deleted_by) to tables.
- Queries exclude rows where deleted_at is not null.
- Deleting a record sets deleted_at (and deleted_by), instead of issuing a SQL DELETE.

Schema
```php
use Ishmael\Core\Database\Schema\{TableDefinition, ColumnDefinition};

$table = new TableDefinition('posts');
$table->addColumn(ColumnDefinition::increments('id'));
$table->addColumn(ColumnDefinition::string('title', 160));
$table->addColumn(ColumnDefinition::text('body'));

// Audit + soft delete
$table->addColumn(ColumnDefinition::timestamp('created_at')->nullable(false));
$table->addColumn(ColumnDefinition::timestamp('updated_at')->nullable(true));
$table->addColumn(ColumnDefinition::string('created_by', 64)->nullable(true));
$table->addColumn(ColumnDefinition::string('updated_by', 64)->nullable(true));
$table->addColumn(ColumnDefinition::timestamp('deleted_at')->nullable(true));
$table->addColumn(ColumnDefinition::string('deleted_by', 64)->nullable(true));
```

Usage patterns
- Delete
```php
// Instead of: DELETE FROM posts WHERE id = :id
$db->execute('UPDATE posts SET deleted_at = CURRENT_TIMESTAMP WHERE id = :id', ['id' => $id]);
```
- Fetch active rows only
```php
$db->query('SELECT * FROM posts WHERE deleted_at IS NULL ORDER BY created_at DESC');
```
- Include deleted when needed
```php
$db->query('SELECT * FROM posts ORDER BY created_at DESC');
```
- Restore
```php
$db->execute('UPDATE posts SET deleted_at = NULL, deleted_by = NULL WHERE id = :id', ['id' => $id]);
```

Pros
- Safer deletes: accidental removals can be reverted.
- Easier audits: who/when a record was removed.
- Referential integrity for historical reports (keep foreign keys intact by filtering instead of deleting).

Cons
- Data bloat: tables grow over time; need archiving or periodic purge.
- Query complexity: must remember to filter deleted_at IS NULL in most queries.
- Unique constraints: if you need to re‑use unique values (e.g., usernames) after soft delete, consider partial unique indexes or include deleted_at in the index (engine‑specific workaround may be required).

Recommendations
- Standardize helpers to add soft‑delete columns to all relevant tables.
- Centralize query helpers that automatically append deleted_at IS NULL for common tables.
- Consider adding partial indexes on active rows when the engine supports it (e.g., PostgreSQL).
- Define an archival policy to hard‑delete or move old soft‑deleted records after a retention window.

See also
- Defining Tables and Models
- Writing and Running Migrations
- Using the Minimal Model
