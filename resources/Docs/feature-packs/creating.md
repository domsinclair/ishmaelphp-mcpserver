# Creating a Feature Pack

This is a comprehensive, practical guide to designing, building, testing, and publishing Feature Packs (modules) for IshmaelPHP. It assumes basic familiarity with PHP, Composer, and the IshmaelPHP starter app.

What you will learn:
- How modularity works in IshmaelPHP and why packs exist
- How modules are designated for different environments (development, shared, production)
- How to set up PhpStorm or VS Code for feature pack development
- The minimum viable structure for a new pack
- How configuration files work and when to merge them into the app’s config
- How to add CLI commands to your pack and integrate them with the core CLI
- How to declare dependencies on other packs and handle missing prerequisites
- Best practices for testing, security, and publishing

See also:
- Concepts — Module Discovery
- Feature Packs — Overview
- Repository root blueprint: Docs/FeaturePacks.md

---

## 1) Modularity in IshmaelPHP (mindset and model)

IshmaelPHP is a modular framework. The core is intentionally slim and provides only foundational services (routing, request/response, module discovery, CLI plumbing, helpers). Application functionality is composed by adding modules (feature packs).

Key properties of modules:
- Self-contained: a module can carry routes, controllers, views, config, migrations, seeds, assets, and CLI commands.
- Portable: a module folder can be copied between apps or published as a Composer package.
- Discoverable: the core scans the app’s Modules directory and can also auto-discover modules installed under vendor when configured.
- Environment-aware: modules can be designated for development, shared, or production.

Your pack contributes to the ecosystem of reusable modules that any IshmaelPHP user can install. Treat it like a standalone product with a clear responsibility and stable API.

---

## 2) Environment designations: development, shared, production

Modules declare an environment in their manifest (`module.php` or `module.json`) using the `env` key. The three supported values are:
- development — For local tooling, sample data, profilers, debug UIs. Not intended for production deploys.
- shared — Safe for both local development and production (the default for most feature packs).
- production — Packs that should be activated only in production. Rare; used for hardened policies or production-side integrations.

Example (module.php):
```php
<?php
return [
    'name' => 'Blog',
    'version' => '0.1.0',
    'enabled' => true,
    'env' => 'shared', // development | shared | production
];
```

How the framework uses this:
- The module loader can filter modules by the current APP_ENV when caching/discovering modules (see Module Discovery docs and CLI route/modules cache pages).
- The packer (ish pack) can include or exclude modules depending on the target environment and flags (e.g., include-dev).

Guidance:
- Ship your feature pack as `shared` unless you have a strong reason otherwise.
- If your pack is purely developer tooling (e.g., fixtures, UI profilers), mark it `development` and document it clearly.

---

## 3) Project setup for pack development (PhpStorm and VS Code)

You can develop a pack either inside the main app repository (copy a template under `Modules/`) or as a standalone Composer package in its own repo. The latter is recommended for reusability.

### 3.1 PhpStorm setup
- Create a new project from existing sources pointing to your pack repository.
- Ensure Composer is initialized:
  - Run `composer init` (if starting from scratch).
  - Add `ishmaelphp/core` to `require` to get interfaces and helpers for type hints and tooling.
- Configure PSR-4 autoload in composer.json for your `src/` namespace.
- Add a Run/Debug configuration for the Ishmael CLI in your consuming app if you need to run integration flows:
  - Tools > Run External Tools or PhpStorm’s “CLI Tools Support” to wire the app’s `IshmaelPHP-Core/bin/ish` script.
- Optional: Mark `tests/` as a Test Sources Root for better navigation and code coverage.

### 3.2 VS Code setup
- Create/open your pack folder.
- Install extensions:
  - PHP Intelephense or PHP Language Features
  - PHPUnit Test Explorer (if applicable)
- Initialize Composer and autoload as above.
- Add tasks to `.vscode/tasks.json` if you want shortcuts for `composer test`, `phpunit`, or running the starter app’s `ish` CLI.

### 3.3 Local linking during development
Options:
- Path repositories: In an app consuming your pack, use Composer’s `repositories` with a `path` type pointing at your local pack directory; enable `symlink` to iterate quickly.
- Manual copy: Copy `Modules/<Name>` into the app’s `Modules` for quick trials.

---

## 4) Minimum viable Feature Pack

At minimum, a usable pack should include:
- composer.json — package metadata, core dependency, PSR-4 autoload, and the `extra.ishmael-module` hint.
- Modules/<Name>/module.php — manifest declaring name, env, routes, export list, etc.
- Modules/<Name>/routes.php — route registrations.

Recommended additions:
- Modules/<Name>/Controllers — controllers for your routes.
- Modules/<Name>/Views — Tailwind-friendly views (if you expose UI).
- Modules/<Name>/Config — configuration defaults (namespaced under your pack name).
- Modules/<Name>/Database/Migrations and `Database/Seeders` — for database-backed features.
- tests/ — unit and integration tests.
- README.md — install and usage instructions.

Folder sketch:
```
composer.json
README.md
Modules/
  <Name>/
    module.php
    routes.php
    Controllers/
    Views/
    Config/
    Database/
      Migrations/
      Seeders/
src/
  ServiceProvider.php   # optional hooks, installers, or CLI registration helpers
tests/
```

composer.json (template):
```json
{
  "name": "ishmaelphp/<feature>",
  "description": "<Feature> feature pack for IshmaelPHP",
  "type": "library",
  "require": {
    "php": ">=8.0",
    "ishmaelphp/core": "^1.0"
  },
  "autoload": {
    "psr-4": { "Ishmael\\<Feature>\\": "src/" }
  },
  "extra": {
    "ishmael-module": { "module-path": "Modules/<Name>" }
  }
}
```

---

## 5) Module manifest (module.php)

The manifest is the authoritative description of your module. Use the PHP format (`module.php`) for flexibility.

Common keys:
- name (string)
- version (string)
- description (string)
- enabled (bool)
- env ("development" | "shared" | "production")
- routes (string|array) — path(s) to route files
- commands (string[]) — FQCNs of CLI commands your module provides
- migrations (string|array)
- assets (string|array)
- services (array) — DI registrations (pack-level)
- export (string[]) — guidance for publishers/packers on which files/dirs to include
- dependencies (string[]) — optional Composer package hints for tooling

Example:
```php
<?php
return [
  'name' => 'Upload',
  'description' => 'File uploads',
  'version' => '0.1.0',
  'enabled' => true,
  'env' => 'shared',
  'routes' => __DIR__.'/routes.php',
  'commands' => [/* e.g., \Ishmael\Upload\Console\PruneCommand::class */],
  'export' => [__DIR__.'/Controllers', __DIR__.'/Views', __DIR__.'/Config'],
];
```

See Module Discovery for the full manifest specification and precedence rules.

---

## 6) Configuration: structure, loading, and merging

Where to put config:
- Place defaults in `Modules/<Name>/Config/<pack>.php` where `<pack>` is a concise, lower-kebab or snake name (e.g., `upload.php`).
- Keep keys under a single top-level namespace to avoid collisions:
  ```php
  return [
    'max_size_bytes' => 5 * 1024 * 1024,
    'allowed_extensions' => ['jpg','png','pdf'],
  ];
  ```

How config is consumed:
- Modules may read their own config file directly (simple and decoupled) or rely on the app’s global config loader if present.

Merging into the app’s config folder — when and why:
- Why: Centralized control for operators, environment-specific overrides, and consistency with other app settings.
- When: In production deploys or when multiple modules need to read a common key.
- How: Provide a CLI installer/publisher (e.g., future `ish add <pack>`) that copies your config file to `config/<pack>.php`. Document the keys and defaults in your README and docs.

Pros:
- Operators can track and override configuration in one place.
- Config cache and validation can be applied uniformly.

Cons:
- Slightly more setup during installation.

Recommendation:
- Support both: ship module-local defaults for zero-config dev; publish/merge into app config for production.

### 6.1) Adding Models, Services, and Database Schema (inside your pack)

Most non-trivial packs will need persistent data. IshmaelPHP supports putting database models, services, migrations, and seeds inside your module so that everything ships together.

What to add to your pack:
- Modules/<Name>/Models — your domain models or data mappers
- Modules/<Name>/Services — application services that orchestrate business logic and repositories
- Modules/<Name>/Database/Migrations — PHP migration files to create/alter tables
- Modules/<Name>/Database/Seeders — optional seeders for test/demo data

Suggested layout:
```
Modules/
  <Name>/
    Models/
      Post.php
      PostRepository.php
    Services/
      PostService.php
    Database/
      Migrations/
        2025_12_02_120000_create_posts_table.php
      Seeders/
        PostSeeder.php
```

Models and repositories:
- Use a simple repository pattern to keep SQL isolated from controllers.
- If you prefer the minimal model utilities, see Guide — Using the Minimal Model (linked below).

Example PostRepository (sketch):
```php
<?php
namespace Modules\Blog\Models;

use Ishmael\Core\Database; // obtain the adapter

final class PostRepository
{
    private \PDO $db;

    public function __construct()
    {
        $this->db = Database::adapter()->pdo();
    }

    /** @return array<int, array<string,mixed>> */
    public function all(): array
    {
        return $this->db->query('SELECT id, title, slug, published_at FROM posts ORDER BY id DESC')
            ->fetchAll(\PDO::FETCH_ASSOC);
    }

    /** @param array<string,mixed> $data */
    public function create(array $data): int
    {
        $stmt = $this->db->prepare('INSERT INTO posts (title, slug, body, published_at) VALUES (?,?,?,?)');
        $stmt->execute([$data['title'], $data['slug'], $data['body'], $data['published_at'] ?? null]);
        return (int)$this->db->lastInsertId();
    }
}
```

Services orchestrate logic and can be injected where needed:
```php
<?php
namespace Modules\Blog\Services;

use Modules\Blog\Models\PostRepository;

final class PostService
{
    public function __construct(private PostRepository $repo) {}

    /** @return array<int, array<string,mixed>> */
    public function listPosts(): array { return $this->repo->all(); }
}
```

Registering services (DI):
- Add a `services` key in `module.php` to hint registrations, or bootstrap them in a small ServiceProvider under `src/` if your pack uses one.
```php
// Modules/<Name>/module.php
return [
  // ...
  'services' => [
    Modules\Blog\Models\PostRepository::class => Modules\Blog\Models\PostRepository::class,
    Modules\Blog\Services\PostService::class => Modules\Blog\Services\PostService::class,
  ],
];
```

Creating tables with migrations:
- Generate files with the CLI from the application root:
```
ish make:migration create_posts_table --module=Blog
```
- Or, if your CLI supports two-argument form:
```
ish make:migration Blog create_posts_table
```
- Edit the generated file under `Modules/Blog/Database/Migrations/` to define `up()` and `down()` operations using the Schema Manager.

Run migrations for your module:
```
ish migrate --module=Blog
ish status --module=Blog
```

Seeding module data:
```
ish make:seeder Blog PostSeeder
ish seed --module=Blog --class=Modules\\Blog\\Database\\Seeders\\PostSeeder
```

Transactions and safety:
- Wrap multi-step writes in transactions (see Guide — Transactions).
- For destructive operations, support `--pretend`/`--dry-run` where appropriate.

Soft deletion:
- If your tables need soft deletes, include a nullable `deleted_at` column; see Database — Soft Deletion.

Links to learn more:
- Database — Configuring the Database
- Database — Defining Tables and Models
- Guide — Writing and running migrations
- Guide — Using the Minimal Model
- Guide — Transactions
- How‑to — Run migrations and seeds without the CLI

---

## 7) Adding CLI commands to your pack

Why add commands:
- Automate pack-specific tasks (e.g., data seeding, background processing, cleanup, publishing assets).
- Provide a consistent UX for developers and operators.

Design guidelines:
- Namespacing: use a clear prefix or group in your command names (e.g., `upload:prune`, `blog:import`).
- Idempotency and safety: support `--dry-run`, `--force`, and environment guards when destructive.
- Output: write human-friendly text to STDOUT/STDERR; keep exit codes meaningful.

Registration models:
- Manifest-based: list FQCNs under `commands` in `module.php` and let your pack’s bootstrap wire them when discovered.
- ServiceProvider/Installer: expose a class in `src/` to register commands with the core CLI during app boot.

Integration with Ish CLI:
- Commands registered by modules appear alongside core commands in `ish help`. Keep names unique to avoid collisions.
- For long-running or background processes, prefer separate worker tooling; the CLI is best for one-shot tasks.

---

## 8) Declaring dependencies on other modules

If your pack requires another pack (e.g., Admin depends on Auth):
- Composer-level dependency: add the other pack to `require` in composer.json. This ensures installation order and version constraints.
- Manifest hint: include the dependency name in `dependencies` for tooling and installers to surface nicer error messages.
- Runtime checks: during module boot, verify that required services are available; fail fast with a clear error that guides the user to install the missing pack.

Example composer.json dependency:
```json
{
  "require": {
    "ishmaelphp/core": "^1.0",
    "ishmaelphp/auth": "^1.0"
  }
}
```

Handling optional integrations:
- Use soft checks (feature detection) and document optional packs in README under a dedicated "Integrations" section.

---

## 9) Integration models (auto-discovery vs publish)

Two ways users bring your pack into an app:
- Auto-discovery: after `composer require`, the framework can discover your `Modules/<Name>` under `vendor` and register its routes. Minimal setup; great for quick trials.
- Publish via CLI: an installer command (e.g., future `ish add <pack>`) copies module files (and config) into the app for deterministic deployments; runs migrations and seeds as needed.

Recommendation:
- Support both. Document the quick path and the production path.

---

## 10) Security and quality checklist

Security:
- Input validation and sanitization (size, MIME/extension, path checks, CSRF for form posts).
- Access control: protect sensitive routes with middleware.
- Avoid path traversal; confine file operations to whitelisted directories.
- Consider adding optional antivirus hooks, SVG sanitization, and content-type sniffing where relevant.

Quality:
- Tests: unit + integration. Exercise route registration and basic HTTP flows.
- Static analysis: run PHPStan/Psalm where possible.
- Coding style: PSR-12 and consistent docblocks.
- Changelog: maintain breaking changes and migration notes.

---

## 11) Testing your pack

- Unit tests for controllers/services using light doubles.
- Integration tests that boot a minimal Ishmael app context and register your module’s routes.
- If you ship views, consider snapshot testing of rendered HTML for critical pages.
- Test environment flags: the core supports helpers for injecting request bodies and avoiding real uploads during tests.

---

## 12) Publishing and versioning

- Follow SemVer. Communicate supported core versions with Composer constraints.
- Tag releases and publish to Packagist for installation via `composer require`.
- Document upgrade paths in CHANGELOG.md.
- Consider a "Compatibility" table in your README mapping pack versions to core versions.

---

## 13) Quickstart: create your first pack

1) Copy the Upload template as a starting point:
   - See `Templates/FeaturePacks/Upload` in this repository.
2) Rename the module folder and namespaces.
3) Update `composer.json` (package name, description, autoload).
4) Edit `Modules/<Name>/module.php` metadata and set `env` to `shared`.
5) Add routes in `routes.php` and a simple controller in `Controllers`.
6) If you expose UI, run `ish ui:tailwind` in your app and use Tailwind utility classes in your views.
7) Add a small config under `Modules/<Name>/Config/<pack>.php`.
8) Write at least one integration test that hits a route.
9) Document installation in README.md (auto-discovery and publish flows).

You should now be able to:
- Install via Composer into a test app
- Hit your module’s route(s)
- See your CLI commands (if any) under `ish help`

---

## 14) Troubleshooting FAQ

- My routes don’t appear:
  - Ensure `module.php` exists and `enabled` is true.
  - Verify `routes.php` path in the manifest.
  - Clear and rebuild any route/module caches.

- My views can’t find Tailwind CSS:
  - Run `ish ui:tailwind` in the app to initialize assets.
  - Ensure your layout includes `/assets/app.css`.

- The pack isn’t discovered from vendor:
  - Confirm `extra.ishmael-module.module-path` is present in `composer.json`.
  - Check that the app’s loader supports vendor discovery (or publish the module into `Modules/`).

- A required pack is missing:
  - Add it to `composer.json` `require`.
  - Re-run Composer; then flush module cache and try again.

---

## Further reading and templates

- Template: Templates/FeaturePacks/Upload — a minimal pack with routes, controllers, views, and config
- Concepts: Module Discovery (concepts/module-discovery.md)
- Database: Configuring the Database (database/configuring.md)
- Database: Defining Tables and Models (database/models-and-tables.md)
- Database: Soft Deletion (database/soft-deletion.md)
- Guide: Writing and running migrations (guide/writing-and-running-migrations.md)
- Guide: Using the Minimal Model (guide/using-the-minimal-model.md)
- Guide: Transactions (guide/transactions.md)
- How‑to: Run migrations and seeds without the CLI (how-to/run-migrations-and-seeds-without-cli.md)
