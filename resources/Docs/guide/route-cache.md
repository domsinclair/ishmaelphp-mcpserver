# Route Cache

Ishmael can cache the compiled route map to a PHP file for fast production boot. Because the cache is written using `var_export()`, only callables that can be represented as plain PHP values are eligible for caching.

This page explains what is cacheable, how to generate and clear the cache, how to interpret warnings, and how to convert non‑cacheable middleware to cacheable forms.

## What is cacheable?

Cacheable handlers/middleware:
- Class strings: `CorsMiddleware::class`, `JsonBodyParser::class`
- Static callables: `[SomeClass::class, 'handle']`
- String function names (discouraged): `'global_function_name'`

Not cacheable (will block caching unless you use `--force`):
- Closures: `function ($req, $res, $next) { ... }`
- Object‑bound callables: `[$instance, 'handle']`

Why: PHP cannot serialize closures or objects with `var_export()`. Writing such values to the cache would fail or generate invalid PHP.

## Generating the cache

Use the CLI to compile and save the route cache.

- Strict (default):
  - Fails if any non‑cacheable middleware or handlers are present.
  - Prints a precise error that includes the route pattern and module.

```
ish route:cache
```

- Force mode:
  - Strips non‑cacheable middleware entries from routes and replaces non‑cacheable handlers with a sentinel.
  - Embeds warnings into the cache file under `meta.warnings` and prints them to the console.

```
ish route:cache --force
```

Clearing the cache:

```
ish route:clear
```

## Runtime behavior

- When a valid cache exists, the bootstrap loads it directly to avoid running module route closures.
- In debug environments, a stale cache is ignored and dynamic routes are rebuilt; in non‑debug environments the cache is trusted.

## Converting closures to classes (recommended)

Before (not cacheable):

```php
use Ishmael\Core\Router;

Router::get('/posts', [PostsController::class, 'index'], [function ($req, $res, $next) {
    // ...
}]);
```

After (cacheable):

```php
final class PostsGateMiddleware
{
    public function __invoke($req, $res, $next)
    {
        // ...
        return $next($req, $res);
    }
}

Router::get('/posts', [PostsController::class, 'index'], [PostsGateMiddleware::class]);
```

You can also use a static callable if you prefer `handle` instead of `__invoke`:

```php
Router::get('/posts', [PostsController::class, 'index'], [[PostsGateMiddleware::class, 'handle']]);
```

## Troubleshooting

- Error: "Non‑cacheable middleware (closure/object) on route /path [module=Module] index N"
  - Replace the offending middleware with an invokable class string or a static callable array.
- The cache file loads but some middleware appears to be missing
  - You likely used `--force`, which strips non‑cacheable entries. Check `meta.warnings` inside `storage/cache/routes.php` for details.
- Local dev vs production
  - Prefer strict mode locally so issues are surfaced early. In CI or production builds, you can choose strict or `--force` depending on your policy.

## Reference

- CLI: see Guide → CLI for running commands.
- Routing details and examples: see Guide → Routing.


---

## Related reference
- Reference: [Routes](../reference/routes/_index.md)
- Reference: [CLI Cache Commands](../reference/cli-cache-commands.md)
