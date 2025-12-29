# Configuring the Database

This section explains how Ishmael connects to your database, which engines you can use, how to choose between them, and how to install the required dependencies.

Ishmael talks to databases via small pluggable adapters that implement the DatabaseAdapterInterface. You can switch engines by changing configuration only.

Supported adapters out of the box:
- SQLite — zero‑config file database, perfect for local development, demos, small tools, and test suites.
- MySQL/MariaDB — popular choice for many applications, wide hosting support, good tooling.
- PostgreSQL — advanced features, strong correctness guarantees, powerful SQL.

Choosing an engine — quick guidance:
- Prefer SQLite when:
  - You want the simplest local setup (no server).
  - You run unit/integration tests without external services.
  - Your workload is single‑node, low concurrency.
  - Trade‑offs: file locking, limited concurrent writes, fewer advanced SQL features.
- Prefer MySQL/MariaDB when:
  - You need wide operational familiarity and hosting support.
  - You favor replication options and common tooling.
  - Trade‑offs: historically weaker standard compliance, some feature gaps vs Postgres.
- Prefer PostgreSQL when:
  - You value correctness, advanced SQL, JSON/GIN indexes, robust migrations.
  - You need complex queries, window functions, CTEs.
  - Trade‑offs: slightly steeper learning curve, operational tuning.

Installation basics
- SQLite: usually bundled with PHP (pdo_sqlite). Ensure your PHP has the pdo_sqlite extension. No server to install.
- MySQL/MariaDB: install server (or use managed), ensure PHP pdo_mysql extension is enabled.
- PostgreSQL: install server (or use managed), ensure PHP pdo_pgsql extension is enabled.

Configuration keys
- Define database configuration in config/database.php and/or environment variables:
  - DB_CONNECTION: sqlite | mysql | pgsql
  - DB_HOST, DB_PORT, DB_DATABASE, DB_USERNAME, DB_PASSWORD: engine‑specific values
  - Optional engine‑specific flags can be provided depending on your adapter.

Example .env values
```
# SQLite
DB_CONNECTION=sqlite
DB_DATABASE=storage/database/database.sqlite

# MySQL
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=ishmael
DB_USERNAME=ish
DB_PASSWORD=secret

# PostgreSQL
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=ishmael
DB_USERNAME=ish
DB_PASSWORD=secret
```

Bootstrapping in code
```php
use Ishmael\Core\Database;

Database::init(config('database'));
```

Notes
- Adapters are resolved by DatabaseAdapterFactory using DB_CONNECTION.
- To add a new engine, implement DatabaseAdapterInterface and register it. See: How‑to > Implement a new Adapter.
