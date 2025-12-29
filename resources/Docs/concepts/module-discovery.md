# Module Discovery

Ishmael applications are composed of modules. A module is a small, portable package that can contain controllers, routes, views, assets, migrations, seeds, and services. The framework discovers modules automatically at boot.

What you’ll learn on this page:
- Where modules live and how discovery works
- The minimal files a module needs (`module.php` preferred; `module.json` supported) and `routes.php`
- Module manifest specification and environment awareness
- How controllers, views, assets, and migrations are registered
- Load order, enabling/disabling modules, and route caching
- Practical examples and troubleshooting tips

## 1) Where modules live

By default, Ishmael looks for modules under your application’s Modules directory. In the SkeletonApp this is:
- Disk path: `SkeletonApp/Modules/`
- Namespace root (examples): `Modules\<Name>\...`

The root can be configured in your app config; most apps keep the default. Each first-level folder under `Modules/` is treated as a module (e.g., `Modules/Blog`, `Modules/Users`).

## 2) Minimal module structure

A simple module requires two files:
- `Modules/Blog/module.php` — manifest (preferred) returning an array of metadata and flags
- `Modules/Blog/routes.php` — route registrations

The JSON variant `module.json` is supported as a fallback when a PHP manifest is not present. If both exist, `module.php` takes precedence.

Example `module.php` created by the CLI (preferred):
```php
<?php
/**
 * Module manifest (preferred format).
 * @return array<string, mixed>
 */
return [
    'name' => 'Blog',
    'description' => 'Tutorial Blog module',
    'version' => '0.1.0',
    'enabled' => true,
    // Environment: development | shared | production
    'env' => 'shared',
];
```

Example `module.json` (fallback):
```json
{
  "name": "Blog",
  "description": "Tutorial Blog module",
  "version": "0.1.0",
  "enabled": true,
  "env": "shared"
}
```

Example `routes.php`:
```php
<?php
use Ishmael\Core\Routing\Router;
use Modules\Blog\Controllers\PostController;

/** @var Router $router */
$router->get('/blog/posts', [PostController::class, 'index'])->name('blog.posts.index');
$router->get('/blog/posts/{id}', [PostController::class, 'show'])->name('blog.posts.show');
```

You can generate this structure with the CLI:
```
php IshmaelPHP-Core/bin/ishmael make:module Blog
```
See also: Guide — Application Bootstrap, and Blog Tutorial Part 1.

## 2.1) Module manifest specification

Ishmael prefers a PHP manifest file named `module.php` that returns an associative array. A JSON manifest (`module.json`) is supported for simple cases. If both files exist, `module.php` is used.

Supported keys (for both formats):
- name: string — human‑readable module name
- version: string — semantic version (e.g., 1.0.0)
- description: string (optional)
- enabled: bool (optional; default true) — discovery toggle
- env: string — one of `development`, `shared`, `production`
- dependencies: string[] (optional) — packages required by this module
- peerDependencies: string[] (optional) — environment prerequisites (e.g., PHP extensions)
- conflicts: string[] (optional) — modules or packages that cannot coexist
- routes: string|array (optional) — path to `routes.php` or list of route files
- commands: string[] (optional) — fully qualified class names of CLI commands
- migrations: string|array (optional) — path(s) to migration files or directory
- assets: string|array (optional) — assets or directories to publish
- services: array (optional) — DI registrations (service id => class or factory)
- hooks: array (optional) — lifecycle hooks (boot/shutdown callbacks; event prep)
- schema: string|array (optional) — points to `schema.php` or inline metadata for future SchemaManager integration
- export: string[] — files/directories the packer should include

Validation and precedence rules:
- `module.php` > `module.json` when both exist in the same module directory.
- `env` must be one of `development`, `shared`, or `production`.
- Unknown keys SHOULD be ignored by the loader but may be validated by tooling.
- Paths must be inside the module directory or a configured safe path.

Environment‑aware examples

Development‑only (module.php):
```php
<?php
/**
 * Development-only module manifest.
 * @return array<string, mixed>
 */
return [
    'name' => 'FakerSeeder',
    'version' => '1.0.0',
    'env' => 'development',
    'dependencies' => ['fakerphp/faker'],
    'routes' => __DIR__ . '/routes.php',
    'export' => ['src', 'assets'],
];
```

Shared module (module.php):
```php
<?php
/**
 * Shared module manifest usable in dev and prod.
 * @return array<string, mixed>
 */
return [
    'name' => 'Editor',
    'version' => '0.3.0',
    'env' => 'shared',
    'routes' => __DIR__ . '/routes.php',
    'export' => ['src', 'Views', 'Resources'],
];
```

### 2.2) Lifecycle hooks (preview)

Hooks prepare modules for a future Event Bus without enforcing runtime behavior today. The loader will parse and store the `hooks` section as-is. Execution is deferred until the Event Bus phase ships.

What you can declare

- boot: callable reference to run early in the module lifecycle (after DI, before routes are finalized)
- shutdown: callable reference to run during graceful termination
- onEvents: map of `eventName => handler reference` (subscribed in a future phase)

Handler reference formats

- "FQCN@method" (e.g., "Modules\\Blog\\Bootstrap@boot")
- [FQCN, "method"] array (e.g., [Modules\\Blog\\Bootstrap::class, "boot"])
- FQCN string for invokable classes (e.g., Modules\\Blog\\Listeners\\InvalidateCache::class)
- Absolute path to a PHP file returning a Closure

Example (module.php)

```php
<?php
declare(strict_types=1);

/**
 * Hooks preview for future Event Bus.
 * @return array<string, mixed>
 */
return [
    'name' => 'Blog',
    'env' => 'shared',
    'routes' => __DIR__ . '/routes.php',
    'hooks' => [
        'boot' => [Modules\\Blog\\Bootstrap::class, 'boot'],
        'shutdown' => 'Modules\\Blog\\Bootstrap@shutdown',
        'onEvents' => [
            'user.registered' => [Modules\\Blog\\Listeners\\SendWelcomeEmail::class, 'handle']
        ]
    ]
];
```

Constraints

- Hooks are optional and currently inert (not executed). They will be activated in a future phase.
- Keep handlers small and idempotent; avoid heavy work in boot/shutdown.
- Follow PascalCase for classes and camelCase for methods; include PHPDoc in your examples.

Production module (module.json):
```json
{
  "name": "PaymentGateway",
  "version": "1.0.0",
  "env": "production",
  "dependencies": ["psr/log"],
  "peerDependencies": ["ext-curl"],
  "export": ["src", "config", "views"]
}
```

## 3) How discovery works

At boot, the `ModuleManager`:
1. Reads the configured Modules directory.
2. Finds immediate subdirectories (candidate modules).
3. For each, loads and parses a manifest: prefer `module.php`; if absent, parse `module.json`.
   - If `enabled` is `false`, the module is skipped.
   - The `env` key is recorded for later environment‑aware filtering by the runtime and packer.
4. Registers module resources in this order:
   - Routes: if `routes.php` exists, it is required and receives a `Router` instance.
   - Views: the module’s `Views/` directory is added to the view resolver paths.
   - Migrations/Seeds: if present, they are made available to the CLI.
   - Assets: optional publish step maps `Resources/*` to `public/modules/<module>/`.

This means a module is immediately active after adding it to the Modules directory and setting `enabled: true`.

### Registration order
- By default, modules are processed in alphabetical directory order. If you have inter-module route dependencies, keep paths disjoint or use unique route prefixes.

## 4) Environment filtering (runtime)

The ModuleManager supports environment-aware filtering when discovering modules.

Truth table (APP_ENV vs module env):

- APP_ENV=production
  - module env=production → load
  - module env=shared → load
  - module env=development → skip by default; load only if ALLOW_DEV_MODULES=true
- APP_ENV=development or testing
  - module env=production/shared/development → load

Notes:
- If a development-only module is present in production without explicit override, a warning is logged and the module is skipped.
- You can also pass options to discover():
  - discover($path, ['appEnv' => 'production', 'allowDevModules' => true])

## 5) Module discovery cache (stub)

To speed up boot, a simple modules cache snapshot can be written and read:

- CLI: `php IshmaelPHP-Core/bin/ish modules:cache [--env=production|development|testing] [--allow-dev] [--modules=PATH] [--cache=PATH]`
- CLI: `php IshmaelPHP-Core/bin/ish modules:clear [--cache=PATH]`

Behavior:
- The cache stores the discovered modules array as JSON. It is safe to delete; the framework will rediscover modules as needed.
- When using the route cache (see Guide — Route Cache), discovery runs once to build the cache; subsequent requests load the cached routes for speed.

## 4) Controllers and namespaces

Controllers are regular PHP classes under the module’s namespace. A common layout:
```
Modules/
  Blog/
    Controllers/
      PostController.php
    Views/
      posts/
        index.php
        show.php
    routes.php
    module.json
```
Example controller (excerpt):
```php
<?php
namespace Modules\Blog\Controllers;

use Ishmael\Core\Http\Request;
use Ishmael\Core\Http\Response;

final class PostController
{
    public function index(Request $req, Response $res): Response
    {
        return $res->withBody($this->view('posts/index', [/* ... */]));
    }
}
```

## 5) Views and templates

Place module views under `Modules/<Name>/Views`. The view resolver is aware of module paths, so from within that module you can render `posts/index` or `posts/show` and it will resolve to the correct file. See Guide — Controllers & Views and Blog Tutorial Part 4.

## 6) Middleware, services, and routes

- You can import and attach middleware in `routes.php`:
  ```php
  use Modules\Blog\Middleware\RequireAuthor;
  $router->post('/blog/posts', [PostController::class, 'store'])
      ->middleware(RequireAuthor::class)
      ->name('blog.posts.store');
  ```
- Group routes or apply prefixes as needed. See Guide — Routing and “Routing v2: Parameters, Constraints, and Named Routes”.

## 7) Migrations and seeds (optional)

If your module ships database changes, keep them under `Modules/<Name>/Database/Migrations` and seeds under `Modules/<Name>/Database/Seeds`. The CLI can pick these up:
```
php IshmaelPHP-Core/bin/ishmael migrate --module=Blog
php IshmaelPHP-Core/bin/ishmael db:seed --module=Blog
```
Consult the “Writing and running migrations” guide for details.

## 8) Static assets (optional)

Keep CSS/JS/images under `Modules/<Name>/Resources/` and publish them to the public web root during build or install, for example to `public/modules/<name>/`. See Blog Tutorial Part 13 (CSS) and Part 14 (JavaScript) for concrete patterns.

## 9) Enabling/disabling modules

Toggle the `enabled` flag in `module.json`. Disabled modules are skipped during discovery and none of their routes or views are registered. If using route cache, rebuild it after toggling:
```bash
php IshmaelPHP-Core/bin/ishmael route:cache
```

## 10) Troubleshooting

- “My routes don’t appear”: ensure `module.json` has `"enabled": true` and that `routes.php` returns without syntax errors. Clear route cache.
- “Views not found”: confirm files are under `Modules/<Name>/Views` and that the view name matches the folder structure (`posts/index`).
- “Conflicting routes between modules”: use unique prefixes (e.g., `/blog/...` vs `/shop/...`) and named routes.

## Related reading
- Guide: [Application Bootstrap](../guide/application-bootstrap.md)
- Guide: [Routing](../guide/routing.md) and [Routing v2: Parameters, Constraints, and Named Routes](../guide/routing-v2-parameters-constraints-and-named-routes.md)
- Guide: [Controllers & Views](../guide/controllers-and-views.md)
- How‑to: [Create a Module](../how-to/create-a-module.md)
- Reference: [Routes](../reference/routes/_index.md)
