# Module Types — development, shared, production

Environment-aware modules let Ishmael load the right code for the right environment and ship optimized bundles.

This page provides side-by-side examples for the three types, showing manifests, minimal structure, routes, schema metadata, and asset exports.

Development-only module

Structure

```
Modules/DevTools/
  Controllers/
  Views/
  routes.php
  module.php
  assets/
```

Manifest (module.php)

```php
<?php
/**
 * Development-only tools for local usage.
 * @return array<string, mixed>
 */
return [
    'name' => 'DevTools',
    'version' => '1.0.0',
    'env' => 'development',
    'routes' => __DIR__ . '/routes.php',
    'export' => ['Controllers', 'Views', 'routes.php', 'assets'],
];
```

Routes (routes.php)

```php
<?php
declare(strict_types=1);

use Ishmael\Core\Router;

/**
 * Register DevTools routes.
 * @param Router $router Router instance
 * @return void
 */
return function (Router $router): void {
    $router->get('/dev/ping', [\Modules\\DevTools\\Controllers\\PingController::class, 'index']);
};
```

Shared module

Structure

```
Modules/Blog/
  Controllers/
  Models/
  Views/
  routes.php
  module.php
  assets/
```

Manifest (module.php)

```php
<?php
/**
 * Shared Blog module usable across environments.
 * @return array<string, mixed>
 */
return [
    'name' => 'Blog',
    'version' => '1.0.0',
    'env' => 'shared',
    'routes' => __DIR__ . '/routes.php',
    'schema' => __DIR__ . '/schema.php',
    'export' => ['Controllers', 'Models', 'Views', 'routes.php', 'schema.php', 'assets'],
];
```

Schema metadata (schema.php)

```php
<?php
/**
 * Module-level schema metadata (illustrative; consumed in future phases).
 * @return array<string, mixed>
 */
return [
    'tables' => [
        'posts' => [
            'columns' => [
                ['name' => 'id', 'type' => 'int', 'nullable' => false],
                ['name' => 'title', 'type' => 'string', 'nullable' => false],
                ['name' => 'authorId', 'type' => 'int', 'nullable' => false]
            ],
            'primaryKey' => ['id']
        ]
    ]
];
```

Production module

Structure

```
Modules/Payments/
  Controllers/
  Services/
  Views/
  routes.php
  module.json
  assets/
```

Manifest (module.json)

```json
{
  "name": "Payments",
  "version": "1.2.0",
  "env": "production",
  "peerDependencies": ["ext-curl"],
  "routes": "<MODULE_DIR>/routes.php",
  "export": ["Controllers", "Services", "Views", "routes.php", "assets"]
}
```

Routes (routes.php)

```php
<?php
declare(strict_types=1);

use Ishmael\Core\Router;

/**
 * Production-safe payment routes.
 * @param Router $router Router instance
 * @return void
 */
return function (Router $router): void {
    $router->post('/payments/charge', [\Modules\\Payments\\Controllers\\ChargeController::class, 'create']);
};
```

Security posture

- In production, development-only modules are excluded by default.
- Explicit overrides: ALLOW_DEV_MODULES=true in environment or --include-dev for `ish pack`.
- Recommended CI policy: fail builds if dev modules are detected in a production pack unless explicitly allowed.

See also

- Packer CLI: ../cli-pack.md
- Security and Policies: ../security-policies.md
