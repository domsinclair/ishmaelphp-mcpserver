# Routing

This guide explains how routing works in Ishmael, where to declare routes, all the ways you can define them, how parameters and constraints behave, grouping and middleware, named routes and URL generation, diagnostics (404/405 and collision detection), and production route caching. It includes thoroughly commented examples you can copy into your app.

At a glance:
- Define module routes in `Modules/<ModuleName>/routes.php`.
- Prefer the fluent Router closure API; the legacy array format is still supported for simple cases.
- Use typed parameters like `{id:int}` and name your routes for stable URL generation.
- Middleware is supported per route and per group. Do not add CSRF middleware here — CSRF is global.
- Enable route caching for production.

## Where routes live

Each module may provide a `routes.php` at its root, for example:

```
SkeletonApp/Modules/Home/routes.php
SkeletonApp/Modules/Blog/routes.php
```

The application kernel discovers and registers these declaratively at boot.

## Two ways to declare routes

1) Legacy array (simple, static regex → handler). Good for quick static mappings.

```php
<?php
return [
    '^$' => 'HomeController@index',
    '^about$' => 'PagesController@about',
    '^posts/(\d+)$' => 'PostsController@show',
];
```

2) Fluent closure (recommended). Export a closure that receives a `Router $router` and call methods.

```php
<?php
use Ishmael\Core\Router;

return function (Router $router): void {
    $router->get('/', 'HomeController@index');
    $router->get('/posts/{id:int}', 'PostsController@show');

    // Grouping with prefix and middleware (no CSRF middleware needed)
    $router->group([
        'prefix' => '/admin',
        'middleware' => [AuthMiddleware::class],
    ], function (Router $r): void {
        $r->get('/', 'AdminController@index');
        $r->get('/stats', 'AdminController@stats');
    });
};
```

Tip: In module routes, both absolute paths (`/admin`) and module-relative paths (`admin`) are accepted; choose one style and be consistent.

## Handlers you can use

Handlers resolve to controller actions or callables. Supported forms:
- `'Controller@method'` string within the module’s namespace (recommended for cacheability)
- `[ControllerClass::class, 'method']` static callable
- `function (...) { ... }` inline closures (allowed but not cacheable; avoid if you plan to cache routes)

Controllers receive `Request` and `Response` automatically and typed parameters from the route:

```php
use Ishmael\Core\Http\Request;
use Ishmael\Core\Http\Response;

final class PostsController
{
    public function show(Request $req, Response $res, int $id): Response
    {
        return $res->setBody("Post {$id}");
    }
}
```

## Parameters and constraints

Define parameters with `{name}`. Add a type using `{name:type}`.

Built-in types include: `int`, `numeric`, `bool`, `slug`, `uuid`.

Examples:
- `{id}` matches a single non-slash segment
- `{id:int}` restricts to digits and injects an `int $id`
- `{slug:slug}` matches `[A-Za-z0-9-]+`

Custom constraints can be registered via `ConstraintRegistry::add()`; see the dedicated guide “Routing v2: Parameters, Constraints, and Named Routes”.

```php
use Ishmael\Core\ConstraintRegistry;

ConstraintRegistry::add('hex', '[A-Fa-f0-9]+', static fn (string $v) => strtolower($v));
// Then in routes:
$router->get('/debug/{token:hex}', 'DebugController@token');
```

## Named routes and URL generation

Name routes for stable URL generation and to avoid hard-coding paths:

```php
$router->get('/posts/{id:int}', 'PostsController@show')->name('posts.show');

// Later, generate a URL anywhere:
$url = Router::url('posts.show', ['id' => 42]); // "/posts/42"
```

If required parameters are missing at generation time, you get a clear error listing which ones are missing.

## Grouping: prefix and middleware

Use groups to apply a common path prefix and shared middleware. Example:

```php
$router->group([
    'prefix' => '/account',
    'middleware' => [AuthMiddleware::class],
], function (Router $r): void {
    $r->get('/profile', 'AccountController@profile')->name('account.profile');
    $r->post('/profile', 'AccountController@update');
});
```

Notes:
- CSRF is enforced globally; do not add a `csrf` middleware here.
- For route caching, middleware entries must be cacheable (class-strings or static callables), not closures.

## Convention fallback

If enabled, Ishmael can fall back to a convention pattern when no explicit route matches:

```
/{module}/{controller}/{action}/{params...}
```

Use explicit routes for user-facing paths; the convention fallback is helpful during rapid prototyping or for internal tools.

## Diagnostics: 404, 405, and collision detection

- If a path matches but the HTTP method doesn’t, the router returns 405 Method Not Allowed and sets an `Allow` header listing permitted methods.
- If no route matches, 404 Not Found is returned (or the convention fallback applies if enabled).
- The compiler detects conflicting routes (same method produces identical regex) and throws a clear `LogicException` so you can fix overlaps early.

## Middleware and cacheability

When you enable route caching, every handler and middleware in the route table must be serializable:
- Cacheable: `AuthMiddleware::class`, `[SomeClass::class, 'handle']`
- Not cacheable: closures, object-bound callables

You can still use closures in development. If you run `ish route:cache` without `--force`, closures will cause an error with actionable hints. With `--force`, non-cacheable entries are omitted with warnings.

## CSRF protection is global (don’t add CSRF to routes)

Starting with Phase 14, CSRF protection is enforced by global middleware registered in `config/app.php` (VerifyCsrfToken). You do not need to add CSRF middleware or groups inside `Modules/*/routes.php` — all unsafe HTTP methods (POST/PUT/PATCH/DELETE) are checked by default.

- Configuration: `config/security.php` → `csrf.enabled` must be `true` (default in Starter).
- Token names: form field `_token`; request headers `X-CSRF-Token` or `X-XSRF-Token` are accepted.
- Exemptions: add URI patterns to `config/security.php` → `csrf.except_uris` (e.g., `'/api/webhooks/*'`). Prefer exemptions over route-level toggles to keep intent centralized.

Quick sanity test:
- Send a POST to any route without a token → expect 419 “CSRF token mismatch.”
- Submit a form including `<input type="hidden" name="_token" value="<?= csrfToken() ?>">` → request should pass.

Advanced: If you really need a route group without CSRF (e.g., a purely token-auth API group you control), you can use `Router::groupWithoutCsrf([...], fn() => ...)` around those endpoints only. This is rarely necessary when using the global exemptions list.

## Route cache (production)

Speed up cold boots by compiling routes to a cache file:

```
ish route:cache            # strict by default, fails on closures
ish route:cache --force    # strips non-cacheable entries and records warnings
ish route:clear            # remove cache file
```

In production, the Kernel loads the cache automatically when present and fresh; in development, dynamic discovery remains the default.

## End-to-end examples

1) Minimal module routes file

```php
<?php
use Ishmael\Core\Router;

return function (Router $router): void {
    $router->get('/', 'HomeController@index')->name('home.index');
    $router->get('/about', 'PagesController@about');
};
```

2) Admin area with auth middleware and named routes

```php
return function (Router $router): void {
    $router->group(['prefix' => '/admin', 'middleware' => [AuthMiddleware::class]], function (Router $r): void {
        $r->get('/', 'AdminController@index')->name('admin.dashboard');
        $r->get('/users', 'AdminUsersController@index')->name('admin.users.index');
        $r->post('/users/{id:int}/suspend', 'AdminUsersController@suspend')->name('admin.users.suspend');
    });
};
```

3) Blog resource-like routes with parameters

```php
return function (Router $router): void {
    $router->get('/posts', 'PostsController@index')->name('posts.index');
    $router->get('/posts/create', 'PostsController@create')->name('posts.create');
    $router->post('/posts', 'PostsController@store')->name('posts.store');
    $router->get('/posts/{id:int}', 'PostsController@show')->name('posts.show');
    $router->get('/posts/{id:int}/edit', 'PostsController@edit')->name('posts.edit');
    $router->post('/posts/{id:int}', 'PostsController@update')->name('posts.update');
    $router->post('/posts/{id:int}/delete', 'PostsController@destroy')->name('posts.destroy');
};
```

4) API prefix and webhook CSRF exemption (via config)

```php
return function (Router $router): void {
    $router->group(['prefix' => '/api', 'middleware' => [ApiAuth::class]], function (Router $r): void {
        $r->get('/v1/status', 'Api\StatusController@index');
        $r->post('/v1/posts', 'Api\PostsController@store');
        // Webhook endpoint – CSRF exempt via config/security.php → csrf.except_uris: ["/api/webhooks/*"]
        $r->post('/webhooks/provider', 'Api\WebhooksController@provider');
    });
};
```

5) Redirect and simple responses

```php
return function (Router $router): void {
    $router->get('/old-home', 'PagesController@legacyHome');
    $router->get('/health', function($req, \Ishmael\Core\Http\Response $res) {
        return \Ishmael\Core\Http\Response::json(['ok' => true]);
    });
};
```

## Migrating from legacy arrays

Most array entries translate 1:1 to fluent definitions:

Array:
```php
return [ '^posts/(\d+)$' => 'PostsController@show' ];
```

Fluent:
```php
$router->get('/posts/{id:int}', 'PostsController@show');
```

Benefits of the fluent API include typed parameters, named routes, easier grouping, better diagnostics, and route caching support.

## Troubleshooting checklist

- Getting 405? Confirm the path exists for a different method and check the `Allow` header.
- Parameters not injected? Verify your placeholder types and controller signature types match.
- Route not found in production? Ensure you ran `ish route:cache` after adding routes, or clear the cache with `ish route:clear`.
- CSRF mismatch (419)? Ensure your form includes `_token` and that CSRF is enabled; for machine-to-machine endpoints, add exemptions in `config/security.php`.

---

## Related guides and references
- Guide: Routing v2 — Parameters, Constraints, Named Routes (routing-v2-parameters-constraints-and-named-routes.md)
- Guide: Middleware (middleware.md)
- Guide: Route Cache (this guide, section above)
- Reference: [Routes](../reference/routes/_index.md)
- Reference: [CLI Route Commands](../reference/cli-route-commands.md)
