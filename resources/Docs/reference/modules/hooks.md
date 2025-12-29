# Module Lifecycle Hooks (preview)

This page defines the `hooks` section for module manifests. Hooks are parsed and stored by the Module Loader, but they are not executed yet. Actual Event Bus integration will activate these in a future phase.

What are hooks?

- boot: runs early in the module lifecycle (after DI is ready, before routes are finalized)
- shutdown: runs late during application termination
- onEvents: mapping of `eventName => handler reference` to subscribe to events when the Event Bus is available

Handler reference formats

- "FQCN@method" string, e.g., "Modules\\Blog\\Bootstrap@boot"
- [FQCN, "method"] array, e.g., [Modules\\Blog\\Bootstrap::class, "boot"]
- FQCN string for invokable classes, e.g., Modules\\Blog\\Listeners\\InvalidateCache::class
- Absolute path to a PHP file returning a Closure (advanced)

Example manifest (module.php)

```php
<?php
declare(strict_types=1);

use Psr\Log\LoggerInterface;

/**
 * Blog module with hooks (preview).
 * @return array<string, mixed>
 */
return [
    'name' => 'Blog',
    'version' => '1.1.0',
    'env' => 'shared',
    'routes' => __DIR__ . '/routes.php',
    'hooks' => [
        'boot' => [Modules\\Blog\\Bootstrap::class, 'boot'],
        'shutdown' => 'Modules\\Blog\\Bootstrap@shutdown',
        'onEvents' => [
            'user.registered' => [Modules\\Blog\\Listeners\\SendWelcomeEmail::class, 'handle'],
            'posts.cleared' => Modules\\Blog\\Listeners\\InvalidateCache::class
        ]
    ]
];

namespace Modules\\Blog;

final class Bootstrap
{
    /**
     * Module boot hook.
     * @param LoggerInterface $logger Logger (example dependency)
     * @return void
     */
    public static function boot(LoggerInterface $logger): void
    {
        $logger->info('Blog module booted');
    }

    /**
     * Module shutdown hook.
     * @return void
     */
    public static function shutdown(): void
    {
        // Flush metrics, close resources, etc.
    }
}

namespace Modules\\Blog\\Listeners;

final class SendWelcomeEmail
{
    /**
     * Example event handler (wired in a future phase).
     * @param array<string, mixed> $payload Event payload
     * @return void
     */
    public function handle(array $payload): void
    {
        // send email …
    }
}

final class InvalidateCache
{
    /**
     * Invokable example handler.
     * @param array<string, mixed> $payload Event payload
     * @return void
     */
    public function __invoke(array $payload): void
    {
        // clear cache …
    }
}
```

Example manifest (module.json)

```json
{
  "name": "Payments",
  "version": "1.2.0",
  "env": "production",
  "routes": "<MODULE_DIR>/routes.php",
  "hooks": {
    "boot": "Modules\\Payments\\Bootstrap@boot",
    "shutdown": ["Modules\\Payments\\Bootstrap", "shutdown"],
    "onEvents": {
      "payments.captured": ["Modules\\Payments\\Listeners\\NotifyAccounting", "handle"]
    }
  }
}
```

Constraints and guidance

- Hooks are optional. Declaring them has no effect until the Event Bus phase activates execution.
- Keep handlers small and idempotent; avoid heavy work in boot/shutdown.
- Use PascalCase for classes, camelCase for methods. Include PHPDoc in handlers.
- Prefer keeping handler classes within the module boundary.

Status: Preview only — subject to refinement when the Event Bus lands.
