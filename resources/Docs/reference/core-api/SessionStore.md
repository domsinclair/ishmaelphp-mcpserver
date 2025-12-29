# SessionStore

- FQCN: `Ishmael\Core\Session\SessionStore`
- Type: interface

## Public Methods

- `load(string $id)`
- `persist(string $id, array $data, int $ttlSeconds)`
- `destroy(string $id)`
- `generateId()`
