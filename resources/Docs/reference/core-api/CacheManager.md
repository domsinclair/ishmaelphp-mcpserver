# CacheManager

- FQCN: `Ishmael\Core\Cache\CacheManager`
- Type: class

## Public Methods

- `clearAll()`
- `getStats()`
- `instance()`
- `get(string $key, mixed $default, string $namespace)`
- `set(string $key, mixed $value, int $ttlSeconds, string $namespace, array $tags)`
- `has(string $key, string $namespace)`
- `forget(string $key, string $namespace)`
- `clearNamespace(string $namespace)`
- `clearTag(string $tag, string $namespace)`
- `remember(string $key, callable $callback, int $ttlSeconds, string $namespace, array $tags)`
- `store()`
