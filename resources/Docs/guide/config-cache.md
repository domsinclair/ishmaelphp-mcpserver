# Config Cache

Config caching compiles all configuration PHP files into a single optimized cache file to speed up application bootstrap in production. It is safe, opt-in, and easy to use via the ish CLI.

When enabled, Ishmael will load configuration from storage/cache/config.cache.php instead of reading multiple files from IshmaelPHP-Core/config and SkeletonApp/config on every request.

## When to use
- Production and staging environments to reduce I/O and PHP parsing during boot.
- CI environments to speed up integration tests that repeatedly boot the app.

In development, Ishmael automatically ignores a stale config cache and falls back to live files so that changes are reflected immediately.

## Commands

- Build cache:

```
php bin/ish config:cache
```

- Clear cache:

```
php bin/ish config:clear
```

- Clear application cache stores (file/sqlite) and optionally show stats:

```
php bin/ish cache:clear --stats
```

## How it works

- The compiler merges configuration files from these locations (later overrides earlier):
  1. IshmaelPHP-Core/config
  2. SkeletonApp/config
  3. Project root config (if present)

- It stores a single PHP file at storage/cache/config.cache.php with two keys:
  - meta: { hash, generatedAt, sources }
  - config: { app: {...}, logging: {...}, ... }

- On bootstrap:
  - If config cache exists and is fresh, it preloads the merged repository.
  - In non-debug (production), it will use the cache even if stale.
  - In debug (development), if the cache is stale it is ignored and the system falls back to loading config files directly.

## Examples

- Rebuilding cache after changing configuration:

```
php bin/ish config:clear
php bin/ish config:cache
```

- Deploy script snippet (Windows PowerShell):

```powershell
php bin/ish route:cache
php bin/ish config:cache
```

## Troubleshooting

- If a key is missing after caching, ensure that the config file returns an array and that file names (e.g., logging.php) match how you access them via config('logging').
- In development, if changes don't reflect, verify APP_DEBUG=false is not set in your .env; otherwise the cache will be used even when stale.


---

## Related reference
- Reference: [Config Keys](../reference/config-keys.md)
- Reference: [CLI Cache Commands](../reference/cli-cache-commands.md)
