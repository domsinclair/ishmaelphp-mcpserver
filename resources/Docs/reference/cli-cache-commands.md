# CLI Cache Commands

This page documents Ishmael's cache-related CLI commands.

## ish config:cache

Compile and save a merged configuration cache for faster bootstrap.

Usage:

```
php bin/ish config:cache
```

- Reads configuration from IshmaelPHP-Core/config, SkeletonApp/config, and project config (if present).
- Writes a single cache file to storage/cache/config.cache.php.

## ish config:clear

Remove the configuration cache file.

Usage:

```
php bin/ish config:clear
```

- The application will fall back to reading configuration files directly on next boot.

## ish cache:clear

Clear application caches for supported stores (file/sqlite). Optionally prints simple statistics.

Usage:

```
php bin/ish cache:clear [--stats]
```

- For the file store, deletes cache files under storage/cache/*.
- For the sqlite store, truncates the cache table.
- With --stats, prints the remaining item count.

## Related

- See Guide: Config Cache for an overview and examples.
