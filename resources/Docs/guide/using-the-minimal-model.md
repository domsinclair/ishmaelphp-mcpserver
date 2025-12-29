# Using the Minimal Model

This guide introduces the thin, explicit Model base that ships with Ishmael PHP. It provides simple CRUD helpers that delegate to the active database adapter and an optional schema() hook for integration with SchemaManager. There is no magic, no relations, and no hidden state.

## Key ideas
- Explicit table mapping via a protected static string $table.
- Static CRUD helpers only: find, findBy, insert, update, delete.
- Optional static schema(): ?TableDefinition for SchemaManager.
- All methods validate inputs and throw clear exceptions on misuse.

## Defining a Model
```php
use Ishmael\Core\Model;
use Ishmael\Core\Database\Schema\{TableDefinition, ColumnDefinition, IndexDefinition};

final class Post extends Model
{
    protected static string $table = 'posts';

    /**
     * Optional: allow SchemaManager to create/validate this table.
     */
    public static function schema(): ?TableDefinition
    {
        return new TableDefinition(
            'posts',
            [
                new ColumnDefinition(name: 'id', type: 'INTEGER', nullable: false, autoIncrement: true),
                new ColumnDefinition(name: 'title', type: 'TEXT', nullable: false),
                new ColumnDefinition(name: 'created_at', type: 'DATETIME', nullable: false),
            ],
            [
                new IndexDefinition(name: 'pk_posts', columns: ['id'], type: 'primary')
            ]
        );
    }
}
```

## Performing CRUD
```php
// Create
$postId = Post::insert(['title' => 'Hello world', 'created_at' => date('c')]);

// Read
$one = Post::find($postId);       // array|NULL
$list = Post::findBy(['title' => 'Hello world']);
$all  = Post::findBy([]);         // returns all rows (explicit)

// Update
$changed = Post::update($postId, ['title' => 'Updated']); // affected rows

// Delete
$deleted = Post::delete($postId); // affected rows
```

## Exceptions on misuse
- Missing table name: Model subclasses must declare a non-empty protected static $table, otherwise a LogicException is thrown.
- Invalid arguments: Empty data arrays or invalid column names result in InvalidArgumentException.

## Notes
- CRUD methods are intentionally static and accept/return plain arrays. There is no identity map or hydration layer.
- Column names are interpolated into SQL verbatim; ensure they are trusted/valid identifiers from your application code. Values are always bound as parameters.
- The primary key column is assumed to be `id` for the helper methods.

## Integrating with SchemaManager
SchemaManager can read model schemas via the static schema() method and apply them conservatively:

```php
use Ishmael\Core\Database\SchemaManager;

$defs = (new SchemaManager(Database::adapter()))
    ->collectFromModels([Post::class]);

// Then synchronize these definitions to the database (safe-only)
(new SchemaManager(Database::adapter()))->synchronize($defs);
```

This keeps your models explicit while enabling a simple workflow for creating tables in development.
