# Middleware Pipeline

This page is a quick reference for how Ishmael executes middleware. For a comprehensive, example‑rich guide (what middleware is for, how to write it, configure it globally/per‑route, CSRF, cacheability, patterns like auth/CORS/rate limiting), see: Middleware (middleware.md).

Ishmael's router executes middleware in a predictable order and short-circuits when a middleware returns a Response.

Order of execution:
- Global middleware (set via Router::useGlobal() or Router::setGlobalMiddleware())
- Group middleware (declared via Router::group(['middleware' => [...]])
- Route middleware (passed to the specific route)

Each middleware must have the signature:

```php
function (\Ishmael\Core\Http\Request $req, \Ishmael\Core\Http\Response $res, callable $next): \Ishmael\Core\Http\Response
```

A middleware may also be an invokable class string (preferred for route caching):

```php
final class JsonBodyParser
{
    public function __invoke($req, $res, $next): \Ishmael\Core\Http\Response
    {
        // parse JSON if present, set on request attributes, etc.
        return $next($req, $res);
    }
}
```

Example usage:

```php
use Ishmael\Core\Router;

Router::useGlobal([
    \App\Http\Middleware\RequestId::class,
]);

Router::group(['middleware' => [\App\Http\Middleware\RequireAuth::class]], function (Router $r) {
    $r->get('/admin', 'AdminController@index', [\App\Http\Middleware\Audit::class]);
});
```

Short-circuiting:
- If any middleware returns a Response, the pipeline stops and that Response is sent.

Caching considerations:
- For `ish route:cache` to work, middleware must be cacheable: either a class string or a static callable. Closures and object callables are not cacheable and will cause `route:cache` to fail unless `--force` is specified (non-cacheable entries are stripped with warnings). See the Middleware page for details.

CSRF note:
- CSRF protection is enforced globally by VerifyCsrfToken registered in config/app.php. Do not add CSRF middleware in your routes/groups. To exempt URIs (e.g., webhooks), add patterns to config/security.php → csrf.except_uris.


---

## Related reference
- Guide: [Middleware](middleware.md)
- Guide: [Routing](routing.md)
- Reference: [Routes](../reference/routes/_index.md)
- Reference: [Core API (Markdown stubs)](../reference/core-api/_index.md)
