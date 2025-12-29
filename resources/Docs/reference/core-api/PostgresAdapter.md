# PostgresAdapter

- FQCN: `Ishmael\Core\DatabaseAdapters\PostgresAdapter`
- Type: class

## Public Methods

- `connect(array $config)`
- `disconnect()`
- `isConnected()`
- `query(string $sql, array $params)`
- `execute(string $sql, array $params)`
- `lastInsertId(string $sequence)`
- `beginTransaction()`
- `commit()`
- `rollBack()`
- `inTransaction()`
- `supportsTransactionalDdl()`
- `createTable(Ishmael\Core\Database\Schema\TableDefinition $def)`
- `dropTable(string $table)`
- `addColumn(string $table, Ishmael\Core\Database\Schema\ColumnDefinition $def)`
- `alterColumn(string $table, Ishmael\Core\Database\Schema\ColumnDefinition $def)`
- `dropColumn(string $table, string $column)`
- `addIndex(string $table, Ishmael\Core\Database\Schema\IndexDefinition $def)`
- `dropIndex(string $table, string $name)`
- `addForeignKey(string $table, Ishmael\Core\Database\Schema\ForeignKeyDefinition $def)`
- `tableExists(string $table)`
- `columnExists(string $table, string $column)`
- `getTableDefinition(string $table)`
- `runSql(string $sql)`
- `getCapabilities()`
