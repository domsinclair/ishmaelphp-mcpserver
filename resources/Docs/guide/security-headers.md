# Security Headers

This guide shows how to enable and customize HTTP response security headers in Ishmael using the SecurityHeaders middleware.

## What it does

By default, the middleware applies these headers:

- X-Frame-Options: SAMEORIGIN
- X-Content-Type-Options: nosniff
- Referrer-Policy: no-referrer-when-downgrade
- Content-Security-Policy: default-src 'self'; frame-ancestors 'self'
- Strict-Transport-Security: disabled by default (enable for HTTPS)
- Permissions-Policy: optional (disabled by default)

All defaults are configurable via config/security.php or environment variables.

## Enable globally

Edit config/app.php and add the middleware to the global HTTP stack (order after request-id/CORS and before app routes is typical):

```php
'http' => [
    'middleware' => [
        // ...
        Ishmael\Core\Http\Middleware\SecurityHeaders::class,
    ],
],
```

## Configure defaults

config/security.php has a `headers` section:

```php
'headers' => [
    'enabled' => true,
    'x_frame_options' => env('SECURITY_XFO', 'SAMEORIGIN'),
    'x_content_type_options' => env('SECURITY_XCTO', 'nosniff'),
    'referrer_policy' => env('SECURITY_REFERRER_POLICY', 'no-referrer-when-downgrade'),
    'permissions_policy' => env('SECURITY_PERMISSIONS_POLICY', ''),
    'content_security_policy' => env('SECURITY_CSP', "default-src 'self'; frame-ancestors 'self'"),
    'hsts' => [
        'enabled' => (bool) env('SECURITY_HSTS', false),
        'only_https' => (bool) env('SECURITY_HSTS_ONLY_HTTPS', true),
        'max_age' => (int) env('SECURITY_HSTS_MAX_AGE', 15552000),
        'include_subdomains' => (bool) env('SECURITY_HSTS_INCLUDE_SUBDOMAINS', false),
        'preload' => (bool) env('SECURITY_HSTS_PRELOAD', false),
    ],
],
```

When serving over HTTPS, consider enabling HSTS:

```
SECURITY_HSTS=true
SECURITY_HSTS_ONLY_HTTPS=true
SECURITY_HSTS_MAX_AGE=15552000
SECURITY_HSTS_INCLUDE_SUBDOMAINS=false
SECURITY_HSTS_PRELOAD=false
```

## Per-route overrides

You can adjust or disable headers for specific routes using the factory helpers:

```php
use Ishmael\Core\Http\Middleware\SecurityHeaders;

// Override the CSP on this route only
Router::get('security-demo', 'HomeController@index', [
    SecurityHeaders::with([
        'content_security_policy' => "default-src 'self' https://cdn.example.com; frame-ancestors 'none'",
    ]),
]);

// Completely disable headers on this route
Router::get('legacy', 'HomeController@legacy', [
    SecurityHeaders::disabled(),
]);
```

## Interaction with other middleware

- Works for both HTML and JSON responses (headers are applied to the final Response regardless of content type).
- Order typically doesn’t matter, but if you need to adjust CSP based on route, place the override middleware in that route’s middleware list.

## Troubleshooting

- HSTS is only emitted when `SECURITY_HSTS=true` and the request is HTTPS (unless `only_https=false`).
- If testing locally over HTTP, HSTS will not be added with the default settings.



---

## Related reference
- Reference: [Config Keys](../reference/config-keys.md)
- Reference: [Core API (Markdown stubs)](../reference/core-api/_index.md)
