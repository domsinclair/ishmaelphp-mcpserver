# ish modules:cache, modules:clear, route:cache, route:clear — Caching stubs

This page documents the cache commands introduced for Phase 11 (Milestone 7). Route caching is a stub in this phase; module caching is functional.

Synopsis

```
ish modules:cache [--env=production|development|testing] [--allow-dev] [--modules=PATH] [--cache=PATH]
ish modules:clear [--cache=PATH]
ish route:cache [--out=PATH] [--modules=PATH] [--env=ENV]
ish route:clear [--file=PATH]
```

Cache file locations
- Modules cache: `storage/cache/modules.cache.json`
- Routes cache: `storage/cache/routes.cache.php`

modules:cache

```php
<?php
/**
 * Cache the current module discovery results.
 *
 * Behavior:
 * - Scans Modules/ with environment filtering (production|development|testing).
 * - Honors allow-dev flag to include development modules in production.
 * - Writes a compact JSON snapshot to storage/cache/modules.cache.json (or --cache).
 * - Subsequent discovery can read from cache when `useCache=true` is provided to the loader.
 *
 * Options:
 * - --env=ENV          Environment name; defaults to APP_ENV or development
 * - --allow-dev        Include development modules when env=production
 * - --modules=PATH     Modules root (default: ./Modules)
 * - --cache=PATH       Cache file path (default: ./storage/cache/modules.cache.json)
 */
```

modules:clear

```php
<?php
/**
 * Clear (delete) the modules discovery cache file.
 *
 * Options:
 * - --cache=PATH  Cache file path (default: ./storage/cache/modules.cache.json)
 */
```

route:cache (stub)

```php
<?php
/**
 * Compile and cache application routes.
 *
 * Behavior (stub in this phase):
 * - Discovers modules with environment filtering.
 * - Collects any array-based route definitions returned by module routes.php files.
 * - Writes a PHP file returning an array for fast load at:
 *   ./storage/cache/routes.cache.php (or a custom --out path).
 * - Closures are not executed/serialized in this phase; future phases may extend this.
 *
 * Options:
 * - --out=PATH      Output cache file path
 * - --modules=PATH  Modules root (default: ./Modules)
 * - --env=ENV       Environment for module filtering (default: APP_ENV or development)
 */
```

route:clear (stub)

```php
<?php
/**
 * Delete the compiled routes cache file.
 *
 * Options:
 * - --file=PATH   Cache file path (default: ./storage/cache/routes.cache.php)
 */
```

Packer integration

- The packer includes both caches automatically when present.
- Run `ish modules:cache` and `ish route:cache` before `ish pack` to embed caches in the bundle.

Notes
- The CLI requires a Composer autoload context; run `composer install` in your app root if autoload cannot be found.
- Follow PascalCase for classes and camelCase for methods. PHPDoc-style comments are used in examples above.
