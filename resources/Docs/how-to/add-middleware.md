# Add Middleware

Middleware lets you run code before and/or after a controller action. Middleware must be callable and return a Response (or mutate one) by invoking the `$next` callback.

## Quick start

Create an invokable class:

```php
use Ishmael\Core\Http\Request;
use Ishmael\Core\Http\Response;

final class AuthMiddleware
{
    public function __invoke(Request $req, Response $res, callable $next): Response
    {
        if (!isset($_SESSION['user_id'])) {
            return $res->withStatus(302)->withHeader('Location', '/login');
        }
        return $next($req, $res);
    }
}
```

Register on a group or route in your module `routes.php` fluent closure:

```php
use Ishmael\Core\Router;

return function (Router $r): void {
    $r->group(['middleware' => [AuthMiddleware::class]], function () use ($r): void {
        $r->get('/dashboard', [AdminController::class, 'index']);
    });

    // Or on a single route
    $r->get('/profile', [AccountController::class, 'show'], [AuthMiddleware::class]);
};
```

## Cacheability rules

When using the route cache, only cacheable forms are allowed in middleware and handlers:

Cacheable:
- Class strings like `AuthMiddleware::class`
- Static callables like `[SomeClass::class, 'handle']`

Not cacheable:
- Closures: `function ($req, $res, $next) { ... }`
- Object callables like `[$instance, 'handle']`

If you run `ish route:cache` and a non‑cacheable callable is found, the command fails with a clear error. You may use `ish route:cache --force` to strip invalid entries; warnings will be embedded in the cache file.

## Converting a closure to a class

Before:

```php
$r->get('/posts', [PostsController::class, 'index'], [function ($req, $res, $next) {
    // ...
}]);
```

After:

```php
final class PostsGateMiddleware
{
    public function __invoke($req, $res, $next)
    {
        // ...
        return $next($req, $res);
    }
}

$r->get('/posts', [PostsController::class, 'index'], [PostsGateMiddleware::class]);
```

See also: Guide → Route Cache, Guide → Routing.


---

## Related reference
- Reference: [Routes](../reference/routes/_index.md)
- Reference: [CLI Route Commands](../reference/cli-route-commands.md)
- Reference: [Core API (Markdown stubs)](../reference/core-api/_index.md)
