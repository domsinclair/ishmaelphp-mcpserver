# Using SchemaManager Safely

SchemaManager provides a lean, explicit way to apply schema changes based on declarative metadata. It only applies safe operations automatically and refuses unsafe changes with actionable guidance.

Goals
- Read schema metadata from either a module schema.php file or model static metadata.
- Compute conservative diffs.
- Apply safe operations automatically; refuse unsafe operations with a clear message so you can write an explicit migration.

Safe operations applied automatically
- Create table if it doesn’t exist.
- Add new columns.
- Create new non-destructive indexes.

Unsafe operations (refused — write a migration)
- Column type changes (e.g., TEXT → INT).
- Nullability flips (NULL ↔ NOT NULL).
- Default value changes (treated as explicit).
- Dropping columns or indexes.

Entrypoints
- applyModuleSchema(string $modulePath): void
- diff(string $table, TableDefinition $desired): SchemaDiff
- synchronize(array $defs): void

Quick start (module schema.php)
1) Create a file Modules/YourModule/Database/schema.php returning an array of TableDefinition values.

Example schema.php:

```php
<?php
use Ishmael\Core\Database\Schema\{TableDefinition, ColumnDefinition, IndexDefinition};

return [
    new TableDefinition(
        name: 'posts',
        columns: [
            new ColumnDefinition('id', 'INTEGER', nullable: false, autoIncrement: true),
            new ColumnDefinition('title', 'TEXT', nullable: false),
            new ColumnDefinition('body', 'TEXT', nullable: true),
            new ColumnDefinition('created_at', 'TEXT', nullable: false),
        ],
        indexes: [
            new IndexDefinition(name: 'idx_posts_title', columns: ['title']),
        ],
    ),
];
```

Then from your bootstrap or a CLI command:

```php
use Ishmael\Core\Database\SchemaManager;
use Ishmael\Core\DatabaseAdapters\DatabaseAdapterFactory;
use Ishmael\Core\Database\Database; // or Ishmael\Core\Database facade in your app

// Initialize DB + get adapter (example)
Database::init(config('database'));
$adapter = DatabaseAdapterFactory::create(config('database.driver', 'sqlite'));

$sm = new SchemaManager($adapter, app(Psr\Log\LoggerInterface::class));
$sm->applyModuleSchema(base_path('Modules/YourModule'));
```

Model-driven metadata
A model can expose its schema statically so SchemaManager can read it:

```php
use Ishmael\Core\Database\Schema\{TableDefinition, ColumnDefinition};

final class Post
{
    public static string $table = 'posts';

    public static function schema(): TableDefinition
    {
        return new TableDefinition(
            name: self::$table,
            columns: [
                new ColumnDefinition('id', 'INTEGER', nullable: false, autoIncrement: true),
                new ColumnDefinition('title', 'TEXT', nullable: false),
                new ColumnDefinition('body', 'TEXT', nullable: true),
                new ColumnDefinition('created_at', 'TEXT', nullable: false),
            ],
        );
    }
}
```

You can collect definitions from multiple models and synchronize:

```php
$defs = $sm->collectFromModels([
    Post::class,
    // ... more models
]);
$sm->synchronize($defs);
```

Logging
- debug: computed diffs and planner decisions
- info: applied operations (create table, add column/index)
- warn: unsafe changes detected, refusal message

Engine support
- SQLite and MySQL are targeted initially. SQLite implements creation and addition operations out of the box. MySQL schema helpers may be limited — SchemaManager will still refuse unsafe changes and will log guidance where adapter support is missing.
- Postgres support can be added or guarded with a feature flag in your adapter capabilities.

Tips
- Keep schema.php explicit and readable; prefer adding columns rather than changing existing ones.
- For destructive or ambiguous changes, write a proper migration file and run it through your migration runner.
- Use SchemaDiff::toArray() in debugging output if you need to inspect planned operations.
