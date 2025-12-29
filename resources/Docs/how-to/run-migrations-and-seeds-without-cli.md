# How to run migrations and seeds without the CLI

This guide shows how to run database migrations and seeds programmatically, without a CLI command. You can call tiny APIs from your app/tests or execute simple PHP scripts.

## Programmatic APIs

Two thin entrypoints wrap the runners:

- Ishmael\Core\Database\Migrations\Migrator
- Ishmael\Core\Database\Seeding\SeedManager

Example: run all pending migrations for all modules

```php
use Ishmael\Core\Database; // for Database::init()/adapter()
use Ishmael\Core\Database\Migrations\Migrator;
use Ishmael\Core\Logger;

$databaseConfig = require __DIR__ . '/SkeletonApp/config/database.php';
Database::init($databaseConfig);

$migrator = new Migrator(Database::adapter(), Logger::channel('migrations'));
$migrator->migrate();
```

Example: run seeders for a specific module/class in dev/test

```php
use Ishmael\Core\Database;
use Ishmael\Core\Database\Seeding\SeedManager;
use Ishmael\Core\Logger;

$databaseConfig = require __DIR__ . '/SkeletonApp/config/database.php';
Database::init($databaseConfig);

$seeder = new SeedManager(Database::adapter(), Logger::channel('seeding'));
$seeder->seed('HelloWorld', 'Database\\Seeders\\UserSeeder');
```

Notes
- Seeders are guarded to run only in dev/test/local by default. Pass $force=true to override.
- Migrations support pretend mode: Migrator::migrate(module: null, steps: 0, pretend: true).

## Simple scripts (bin/)

Two convenience scripts are included at the repo root:

- bin/migrate.php — Runs migrations
- bin/seed.php — Runs seeders

Usage examples

```bash
php bin/migrate.php                 # all modules, all pending
php bin/migrate.php HelloWorld 1    # run 1 migration for HelloWorld
php bin/migrate.php null 0 true     # pretend (dry-run)

php bin/seed.php                    # all modules (dev/test only)
php bin/seed.php HelloWorld         # only HelloWorld module
php bin/seed.php HelloWorld UserSeeder false true  # force in non-dev env
```

## SkeletonApp dev/test hook

To enable auto-migrate/seed during local development or tests, set flags and let the app include the optional hook:

- ISH_AUTO_MIGRATE=true
- ISH_AUTO_SEED=true
- ISH_SEED_MODULE=ModuleName (optional)
- ISH_SEED_CLASS=FQCN or short (optional)

The hook file lives at SkeletonApp/config/dev_bootstrap.php and is included by public/index.php. It only runs in dev/test/local environments.

## Where migrations and seeders live

- Migrations: Modules/<Module>/Database/Migrations/ YYYYMMDDHHMMSS_Description.php
- Seeders: Modules/<Module>/Database/Seeders/ classes implementing SeederInterface

Refer to the guides:
- Guide: writing-and-running-migrations.md
- How-to: create-and-run-seeders.md


---

## Related reference
- Reference: [Core API (Markdown stubs)](../reference/core-api/_index.md)
- Reference: [Config Keys](../reference/config-keys.md)
