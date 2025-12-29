# Defining Tables and Models

In Ishmael, database structure is declared using schema definitions and migrations. A TableDefinition describes a table and its columns and indexes. Adapters translate those definitions to the target engine (SQLite, MySQL, PostgreSQL).

Quick start: declare a table
```php
use Ishmael\Core\Database\Schema\{TableDefinition, ColumnDefinition, IndexDefinition};

$table = new TableDefinition('users');

// Primary key (auto‑increment integer)
$table->addColumn(ColumnDefinition::increments('id'));

// Basic columns
$table->addColumn(ColumnDefinition::string('name', 120)->nullable(false));
$table->addColumn(ColumnDefinition::string('email', 190)->unique());
$table->addColumn(ColumnDefinition::string('password', 255));
$table->addColumn(ColumnDefinition::boolean('is_active')->default(true));

// Timestamps (see Audit fields below for helpers/patterns)
$table->addColumn(ColumnDefinition::timestamp('created_at')->nullable(false));
$table->addColumn(ColumnDefinition::timestamp('updated_at')->nullable(true));

// Indexes
$table->addIndex(IndexDefinition::unique(['email']));
```

Applying the table using SchemaManager
```php
use Ishmael\Core\Database\SchemaManager;
use Ishmael\Core\DatabaseAdapters\DatabaseAdapterFactory;
use Ishmael\Core\Database; // or use Database::init(config('database')) earlier

Database::init(config('database'));
$adapter = DatabaseAdapterFactory::make(config('database'));
$schemas = [$table];

$manager = new SchemaManager($adapter);
foreach ($schemas as $def) {
    if (!$manager->exists($def->name)) {
        $adapter->createTable($def);
    } else {
        $diff = $manager->diff($def->name, $def);
        if ($diff->hasChanges()) {
            $manager->applyDiff($def->name, $diff);
        }
    }
}
```

ColumnDefinition helpers (common types)
- increments(name): integer primary key, auto‑increment
- bigIncrements(name): bigint primary key, auto‑increment
- integer(name), bigInteger(name)
- string(name, length = 255)
- text(name)
- boolean(name)
- date(name), datetime(name), timestamp(name)
- json(name)

Each ColumnDefinition typically supports fluent modifiers:
- nullable(bool = true)
- default(mixed)
- unique(bool = true) or add an IndexDefinition
- unsigned() for integer types (engine‑specific)

Indexes
```php
// Unique index across multiple columns
$table->addIndex(IndexDefinition::unique(['tenant_id', 'email']));

// Regular index
$table->addIndex(IndexDefinition::index(['created_at']));
```

Audit fields pattern

Many applications track who created/updated/deleted a record and when. A common pattern includes:
- created_at: timestamp not null default now
- updated_at: timestamp null
- created_by: string/user id
- updated_by: string/user id

Example
```php
$table = new TableDefinition('posts');
$table->addColumn(ColumnDefinition::increments('id'));
$table->addColumn(ColumnDefinition::string('title', 160));
$table->addColumn(ColumnDefinition::text('body'));

// Audit columns
$table->addColumn(ColumnDefinition::timestamp('created_at')->nullable(false));
$table->addColumn(ColumnDefinition::timestamp('updated_at')->nullable(true));
$table->addColumn(ColumnDefinition::string('created_by', 64)->nullable(true));
$table->addColumn(ColumnDefinition::string('updated_by', 64)->nullable(true));
```

Tip: Create a small helper that returns a reusable array of ColumnDefinition for timestamps and user stamps and merge them into your tables to avoid duplication.

Models?

Ishmael takes a minimal stance and doesn’t mandate an ORM. Many apps start with simple query usage via the Database facade and Result objects. If you prefer a model‑style wrapper, you can build a lightweight class that encapsulates common queries for a table. See Guide: Using the Minimal Model.

Further reading
- Guide: Using Schema Manager Safely
- Guide: Writing and Running Migrations
- Guide: Using the Minimal Model
