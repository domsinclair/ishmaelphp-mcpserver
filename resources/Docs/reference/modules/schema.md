# Module Schema Metadata (preview)

This page defines a suggested structure for declaring database schema metadata on models and modules. It is illustrative and standardized for future SchemaManager tooling. There is no runtime enforcement in this phase.

What you can declare

- Model-level metadata via a public static `$schema` array on the model class
- Module-level metadata via a manifest `schema` key pointing to a `schema.php` file

Model-level `$schema` structure

```php
<?php
namespace Modules\Blog\Models;

/**
 * @property int $id
 * @property string $title
 * @property int $authorId
 */
final class Post
{
    /**
     * Suggested schema metadata for future SchemaManager integration.
     * @var array<string, mixed>
     */
    public static array $schema = [
        'table' => 'posts',
        'columns' => [
            ['name' => 'id', 'type' => 'int', 'nullable' => false, 'default' => null],
            ['name' => 'title', 'type' => 'string', 'nullable' => false],
            ['name' => 'authorId', 'type' => 'int', 'nullable' => false]
        ],
        'primaryKey' => ['id'],
        'foreignKeys' => [
            [
                'name' => 'fk_posts_author',
                'localColumns' => ['authorId'],
                'references' => ['table' => 'users', 'columns' => ['id']],
                'onUpdate' => 'cascade',
                'onDelete' => 'restrict'
            ]
        ],
        'indexes' => [
            ['name' => 'idx_posts_author', 'columns' => ['authorId'], 'unique' => false]
        ]
    ];
}
```

Module-level `schema.php`

Reference a `schema.php` file from your manifest. Place it at the module root for convenience.

Manifest (module.php):

```php
<?php
/**
 * Blog module manifest with schema hook.
 * @return array<string, mixed>
 */
return [
    'name' => 'Blog',
    'version' => '1.0.0',
    'env' => 'shared',
    'routes' => __DIR__ . '/routes.php',
    'schema' => __DIR__ . '/schema.php',
    'export' => ['Controllers', 'Models', 'Views', 'routes.php', 'schema.php', 'assets'],
];
```

schema.php (module-level):

```php
<?php
declare(strict_types=1);

/**
 * Module-level schema metadata (illustrative; consumed in future phases).
 * @return array<string, mixed>
 */
return [
    'tables' => [
        'posts' => [
            'columns' => [
                ['name' => 'id', 'type' => 'int', 'nullable' => false],
                ['name' => 'title', 'type' => 'string', 'nullable' => false],
                ['name' => 'authorId', 'type' => 'int', 'nullable' => false]
            ],
            'primaryKey' => ['id'],
            'foreignKeys' => [
                [
                    'name' => 'fk_posts_author',
                    'localColumns' => ['authorId'],
                    'references' => ['table' => 'users', 'columns' => ['id']],
                    'onUpdate' => 'cascade',
                    'onDelete' => 'restrict'
                ]
            ],
            'indexes' => [
                ['name' => 'idx_posts_author', 'columns' => ['authorId'], 'unique' => false]
            ]
        ]
    ]
];
```

Guidance and constraints

- Status: Preview only — standardizes the shape for future tooling; does not create or migrate tables yet.
- Keep code examples using PascalCase for classes and camelCase for methods/properties. Array keys use lowerCamelCase.
- Prefer `module.php` manifests; `module.json` is supported as a fallback. The `schema` key may point to a PHP file path or contain inline metadata.
