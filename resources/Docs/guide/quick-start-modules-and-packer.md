# Quick Start — Modules and Packer

This guide helps you scaffold a new module, add routes and optional schema metadata, and preview a production bundle using `ish pack`. All examples follow PascalCase/camelCase conventions, and code samples include PHPDoc.

## 1) Create a module

```
php IshmaelPHP-Core/bin/ish make:module Blog
```

This creates `Modules/Blog/` with a preferred `module.php` manifest and a `routes.php` file that returns a Closure.

module.php (generated; edit as needed):

```php
<?php
/**
 * Module manifest (preferred format).
 * @return array<string, mixed>
 */
return [
    'name' => 'Blog',
    'version' => '0.1.0',
    'enabled' => true,
    'env' => 'shared',
    'routes' => __DIR__ . '/routes.php',
    'schema' => __DIR__ . '/schema.php',
    'export' => ['Controllers', 'Models', 'Views', 'routes.php', 'schema.php', 'assets'],
];
```

routes.php:

```php
<?php
declare(strict_types=1);

use Ishmael\Core\Router;

/**
 * Register Blog routes.
 * @param Router $router Router instance
 * @return void
 */
return function (Router $router): void {
    $router->get('/blog/posts', [\Modules\\Blog\\Controllers\\PostController::class, 'index']);
};
```

## 2) Optional: Add schema metadata (preview)

Create `Modules/Blog/schema.php` to advertise tables, keys, and indexes for future tooling. This is illustrative only in this phase.

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
            'indexes' => [
                ['name' => 'idx_posts_author', 'columns' => ['authorId'], 'unique' => false]
            ]
        ]
    ]
];
```

## 3) Preview a production bundle (dry-run)

```
php IshmaelPHP-Core/bin/ish pack --env=production --dry-run
```

Example output:

```
[DRY-RUN] ish pack plan (production)
 - Modules/Blog/Controllers/PostController.php
 - Modules/Blog/Views/posts/index.php
 - storage/cache/routes.cache.php
 - config/app.php
 ...
```

Notes
- Development-only modules are excluded in production by default. Include them explicitly with `--include-dev` or `ALLOW_DEV_MODULES=true` if policy allows.
- The packer automatically includes caches when present: `storage/cache/modules.cache.json`, `storage/cache/routes.cache.php`.

## 4) Build a bundle to ./dist

```
php IshmaelPHP-Core/bin/ish pack --env=production --out=./dist
```

Inspect the generated `manifest.json` inside the bundle directory. It lists files and their SHA-256 checksums.

## 5) Optional caches for faster boot

```
php IshmaelPHP-Core/bin/ish modules:cache --env=production
php IshmaelPHP-Core/bin/ish route:cache
php IshmaelPHP-Core/bin/ish pack --env=production --dry-run
```

This embeds caches in the bundle for faster production startup.

See also
- Module Types: Documentation/reference/modules/types.md
- Packer CLI: Documentation/reference/cli-pack.md
- Security and CI policies: Documentation/reference/security-policies.md
