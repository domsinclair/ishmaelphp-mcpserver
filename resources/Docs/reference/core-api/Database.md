# Database

- FQCN: `Ishmael\Core\Database`
- Type: class

## Public Methods

- `init(array $config)`
- `conn()`
- `adapter()`
- `initAdapter(Ishmael\Core\DatabaseAdapters\DatabaseAdapterInterface $adapter)`
- `reset()`
- `transaction(callable $fn)`
- `retryTransaction(int $attempts, int $sleepMs, callable $fn)`
- `prepare(string $sql)`
- `query(string $sql, array $params)`
- `execute(string $sql, array $params)`
- `lastInsertId(string $sequence)`
- `normalizeParams(array $params)`
