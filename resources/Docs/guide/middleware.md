# Middleware

This guide explains what middleware is in Ishmael, how the pipeline executes, how to write and configure middleware, cacheability rules, and a set of copy‑paste examples for common use cases (auth, JSON body parsing, security headers, CORS, rate limiting, and maintenance mode). It also clarifies how global CSRF protection is applied and how to configure exemptions.

At a glance:
- Middleware runs in order: Global → Group → Route.
- Any middleware can short‑circuit by returning a Response.
- Prefer invokable classes (class strings) for production: they are cacheable.
- CSRF is enforced globally via VerifyCsrfToken; do not add CSRF middleware to routes.
- Configure global middleware in config/app.php; add exemptions (e.g., for webhooks) in config/security.php.

## What is middleware for?

Middleware is a small, focused component that can inspect and modify the Request/Response as it flows through the router. Typical uses:
- Cross‑cutting concerns: authentication, authorization, request IDs, logging
- Protocol features: parsing JSON bodies, setting security headers, CORS
- Policies: rate limiting, maintenance mode, IP allowlists

## Execution order and short‑circuiting

Order of execution:
1) Global middleware (application‑wide)
2) Group middleware (declared via Router::group([...]))
3) Route middleware (specified on a particular route)

Each middleware receives the current Request, Response, and a $next callable. If it returns $next($req, $res), the pipeline continues. If it returns a Response directly, the pipeline stops and that Response is sent.

Signature:
```php
function (\Ishmael\Core\Http\Request $req, \Ishmael\Core\Http\Response $res, callable $next): \Ishmael\Core\Http\Response
```

You can also use an invokable class (preferred for caching):
```php
final class JsonBodyParser
{
    public function __invoke($req, $res, $next): \Ishmael\Core\Http\Response
    {
        if (stripos((string)$req->getHeader('Content-Type'), 'application/json') !== false) {
            $data = json_decode((string)$req->getBody(), true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $req = $req->withAttribute('json', $data);
            }
        }
        return $next($req, $res);
    }
}
```

## Configuring middleware

Global configuration (application‑wide) is typically done in config/app.php by listing class strings:
```php
return [
    'http' => [
        'middleware' => [
            Ishmael\Core\Http\Middleware\StartSessionMiddleware::class,
            Ishmael\Core\Http\Middleware\VerifyCsrfToken::class, // global CSRF
            // Ishmael\Core\Http\Middleware\SecurityHeaders::class, // optional
        ],
    ],
];
```

What these middleware do (and how they affect your app):

- StartSessionMiddleware
  - Purpose: Starts the HTTP session early in the request so controllers, views, and other middleware can read/write session data. Enables helpers like flash messages and CSRF token storage.
  - Effects: Sets (and persists) a session cookie; makes `$req->getSession()` available; allows flash() helpers to work; ensures a stable CSRF token is available for forms.
  - Configuration: Session driver, cookie name, lifetime, and secure flags are typically configured under `config/session.php` (or your app’s equivalent). In production, set Secure/HttpOnly/SameSite appropriately.
  - Gotchas: Session writes can affect response cacheability. For purely static GET endpoints you may prefer not to touch the session to preserve CDN/browser caching.

- VerifyCsrfToken
  - Purpose: Enforces Cross‑Site Request Forgery protection for unsafe HTTP methods (POST/PUT/PATCH/DELETE).
  - Effects: If a request lacks a valid token, the middleware short‑circuits with HTTP 419 “CSRF token mismatch.” Valid tokens can be sent via a hidden form field named `_token` or headers `X-CSRF-Token` / `X-XSRF-Token`.
  - Configuration: See this guide’s “CSRF is global” section below. Toggle and exemptions live in `config/security.php` (e.g., `csrf.enabled`, `csrf.except_uris`). Prefer exemptions (like `/api/webhooks/*`) over route‑level toggles to keep policy centralized.
  - Gotchas: Machine‑to‑machine POSTs (webhooks) will fail without an exemption or alternative auth path. Front‑end SPA calls must include the token header. 419 responses are expected when tokens are missing/invalid — use the sanity tests below to validate.

- SecurityHeaders (optional)
  - Purpose: Adds sensible security HTTP headers (X‑Frame‑Options, X‑Content‑Type‑Options, Referrer‑Policy, Content‑Security‑Policy, etc.).
  - Effects: Improves baseline security posture; may restrict embedding (iframes), resource loading, or referrers depending on your policy.
  - Configuration: Either enable the provided middleware and customize its defaults, or implement your own variant to fit your app’s CSP/embedding needs.
  - Gotchas: A strict CSP can block inline scripts/styles or third‑party resources; test critical pages. If your app intentionally renders inside iframes (e.g., admin widgets), adjust `X‑Frame‑Options`/`Content‑Security‑Policy` accordingly.

Module/group/route usage (within Modules/<Module>/routes.php):
```php
use Ishmael\Core\Router;

return function (Router $router): void {
    // Apply middleware to a group with a prefix
    $router->group([
        'prefix' => '/admin',
        'middleware' => [AuthMiddleware::class],
    ], function (Router $r): void {
        $r->get('/', 'AdminController@index');
        $r->post('/users/{id:int}/suspend', 'AdminUsersController@suspend');
    });

    // Per‑route middleware (array in the 3rd argument)
    $router->get('/health', 'StatusController@health', [AuditMiddleware::class]);
};
```

## CSRF is global (don’t add CSRF to routes)

Starting with Phase 14, CSRF protection is enforced by global middleware registered in config/app.php (VerifyCsrfToken). You do not need to add CSRF middleware in routes or groups.

- Enablement: config/security.php → csrf.enabled = true (Starter sets this on).
- Tokens: `_token` form field; headers `X-CSRF-Token` or `X-XSRF-Token`.
- Exemptions for machine‑to‑machine endpoints: add URI patterns to config/security.php → csrf.except_uris (e.g., "/api/webhooks/*"). Prefer exemptions over route‑level toggles.

Sanity test:
- POST without token → 419 “CSRF token mismatch.”
- POST with `<input type="hidden" name="_token" value="<?= csrfToken() ?>">` → passes.

Advanced: If you truly need a block without CSRF, you can wrap it with `Router::groupWithoutCsrf([...], fn() => ...)`, but this is rarely necessary when using exemptions.

## Cacheability and route cache

When you run `ish route:cache`, all handlers and middleware must be serializable:
- Cacheable: class strings (`AuthMiddleware::class`), static callables (`[SomeClass::class, 'handle']`).
- Not cacheable: closures and object‑bound callables.

Development convenience: you may use closures locally. Without `--force`, the cache command fails with hints if it encounters non‑cacheable entries. With `--force`, it strips them and records warnings.

## Writing middleware: patterns and examples

1) Authentication (RequireAuth)
```php
final class RequireAuth
{
    public function __invoke($req, $res, $next): \Ishmael\Core\Http\Response
    {
        $user = $req->getAttribute('user');
        if (!$user) {
            return $res->setStatus(302)->withHeader('Location', '/login');
        }
        return $next($req, $res);
    }
}
```

2) Security headers
```php
final class SecurityHeaders
{
    public function __invoke($req, $res, $next): \Ishmael\Core\Http\Response
    {
        $resp = $next($req, $res);
        return $resp
            ->withHeader('X-Frame-Options', 'SAMEORIGIN')
            ->withHeader('X-Content-Type-Options', 'nosniff')
            ->withHeader('Referrer-Policy', 'no-referrer-when-downgrade')
            ->withHeader('Content-Security-Policy', "default-src 'self'");
    }
}
```

3) CORS
```php
final class Cors
{
    public function __invoke($req, $res, $next): \Ishmael\Core\Http\Response
    {
        if ($req->getMethod() === 'OPTIONS') {
            return $res
                ->withHeader('Access-Control-Allow-Origin', '*')
                ->withHeader('Access-Control-Allow-Methods', 'GET,POST,PUT,PATCH,DELETE,OPTIONS')
                ->withHeader('Access-Control-Allow-Headers', 'Content-Type, X-CSRF-Token, Authorization')
                ->setStatus(204);
        }
        $resp = $next($req, $res);
        return $resp->withHeader('Access-Control-Allow-Origin', '*');
    }
}
```

4) Rate limiting (very simple in‑memory example)
```php
final class SimpleRateLimit
{
    private static array $hits = [];
    public function __invoke($req, $res, $next): \Ishmael\Core\Http\Response
    {
        $ip = (string)$req->getIp();
        $bucket = (int)floor(time() / 60); // per minute
        $key = $ip . ':' . $bucket;
        self::$hits[$key] = (self::$hits[$key] ?? 0) + 1;
        if (self::$hits[$key] > 120) { // 120 req/min
            return \Ishmael\Core\Http\Response::json(['error' => 'Too Many Requests'], 429);
        }
        return $next($req, $res);
    }
}
```

5) Maintenance mode (config toggle)
```php
final class MaintenanceMode
{
    public function __invoke($req, $res, $next): \Ishmael\Core\Http\Response
    {
        $enabled = (bool)config('app.maintenance', false);
        if ($enabled && !$req->isFromTrustedIp()) {
            return $res->setStatus(503)->setBody('Service Unavailable');
        }
        return $next($req, $res);
    }
}
```

6) JSON body parser (as shown earlier) and per‑route use
```php
return function (\Ishmael\Core\Router $router): void {
    $router->post('/api/v1/echo', 'Api\\EchoController@store', [JsonBodyParser::class]);
};
```

## Error handling inside middleware

- Throwing exceptions: Let your global exception handler (or error middleware) convert them to responses.
- Validations: Return a 400/422 Response with details; optionally attach a problem+json content type.
- Idempotence: Ensure read‑only middleware does not mutate state on failed downstream calls.

## Testing middleware

- Unit test invokable classes directly by constructing Request/Response doubles and a fake $next.
- Integration test via HTTP tests hitting routes with/without the middleware applied.
- Verify headers, status codes, and attribute mutations.

## Troubleshooting

- Route cache fails with closures: convert middleware to invokable classes or use `--force` to strip (not recommended for production).
- CSRF 419 on POST: ensure tokens are present; add exemptions for machine endpoints via config/security.php.
- Unexpected 405: confirm your route methods and group/route middleware aren’t short‑circuiting requests.
- CORS preflight hangs: ensure your CORS middleware handles OPTIONS and sets the right headers.

---

## Related guides and references
- Guide: Routing (routing.md)
- Guide: Routing v2 — Parameters, Constraints, Named Routes (routing-v2-parameters-constraints-and-named-routes.md)
- Reference: Routes (../reference/routes/_index.md)
