# Blueprint

- FQCN: `Ishmael\Core\Database\Schema\Blueprint`
- Type: class

## Public Methods

- `id()`
- `increments(string $name)`
- `string(string $name, int $length, bool $nullable, string $default)`
- `text(string $name, bool $nullable, string $default)`
- `boolean(string $name, bool $nullable, bool $default)`
- `timestamps()`
- `index(mixed $columns, string $name)`
- `unique(mixed $columns, string $name)`
- `foreignKey(mixed $columns, string $referencesTable, mixed $referencesColumns, string $name, string $onDelete, string $onUpdate)`
- `foreignId(string $name, string $referencesTable, bool $nullable, string $type, string $referencesColumn, string $onDelete, string $onUpdate)`
- `toTableDefinition()`
