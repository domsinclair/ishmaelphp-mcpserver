# FileCacheStore

- FQCN: `Ishmael\Core\Cache\FileCacheStore`
- Type: class

## Public Methods

- `get(string $key, mixed $default, string $namespace)`
- `set(string $key, mixed $value, int $ttlSeconds, string $namespace, array $tags)`
- `has(string $key, string $namespace)`
- `delete(string $key, string $namespace)`
- `clearNamespace(string $namespace)`
- `clearTag(string $tag, string $namespace)`
- `remember(string $key, callable $callback, int $ttlSeconds, string $namespace, array $tags)`
- `purgeExpired(string $namespace)`
