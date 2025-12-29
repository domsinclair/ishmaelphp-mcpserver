# Ishmael CLI

There are two ways to use the Ishmael CLI, serving two audiences:

- Mono‑repo development (this repository): a repo‑root CLI at `bin/ish` that boots the SkeletonApp for fast local iteration.
- End‑user distribution (Composer install): a Core‑shipped CLI exposed as `vendor/bin/ish` that works in any application without SkeletonApp.

## Quick start

- In this repository (with SkeletonApp):
  - Windows: `php bin\ish help`
  - macOS/Linux: `./bin/ish help`

- In an end‑user app (after `composer require ishmael/ishmael-core`):
  - All platforms: `php vendor/bin/ish help`

## Why two CLIs?

- This repository includes a Skeleton application used to develop and test the framework quickly. The repo‑root CLI assumes that layout and reads config from `SkeletonApp/config/database.php`.
- End users install only the Core package. The Composer‑installed CLI must discover the caller app, read `config/database.php` from the project, and copy scaffolding from Core if the app has no overrides.

## Commands

Common to both CLIs:

ish help ish make:module <Name> ish make:migration <name> [--module=Name] ish migrate [--module=Name] [--steps=N] [--pretend] ish migrate:rollback [--module=Name] [--steps=N] ish status [--module=Name] ish seed [--module=Name] [--class=FQCN] [--force] [--env=ENV]

## Configuration discovery

- Repo‑root CLI: uses `SkeletonApp/config/database.php` and `SkeletonApp/vendor/autoload.php`.
- Composer CLI: treats your current working directory as the app root and loads `<appRoot>/config/database.php`. Override with `--config=PATH`.

## Templates (scaffolding) lookup order

1. App overrides: `<appRoot>/Templates/<Bundle>` (if present)
2. Core defaults: `vendor/ishmael/ishmael-core/IshmaelPHP-Core/Resources/stubs/<Bundle>`

Override entirely with `--templates=PATH`.

## Examples

- Apply pending migrations across all modules (Composer CLI):
  ```bash
  php vendor/bin/ish migrate
  ```
- Create a module with app override templates:
  ```bash
  php vendor/bin/ish make:module Blog
  ```
- Dry run migrations (no DB changes):
  ```bash
  php vendor/bin/ish migrate --pretend
  ```
- Seed with environment guard override (CI):
  ```bash
  php vendor/bin/ish seed --class=DatabaseSeeder --force --env=ci
  ```

## Notes and roadmap

- Upcoming: migration batching, checksums, transactional execution (adapter‑aware), and SchemaManager v0 in templates.
- Programmatic APIs remain available: Ishmael\\Core\\Database\\Migrations\\Migrator, Ishmael\\Core\\Database\\Seeding\\SeedManager.


---

## Related reference
- Reference: [CLI Route Commands](../reference/cli-route-commands.md)
- Reference: [CLI Cache Commands](../reference/cli-cache-commands.md)
- Reference: [Core API (Markdown stubs)](../reference/core-api/_index.md)
