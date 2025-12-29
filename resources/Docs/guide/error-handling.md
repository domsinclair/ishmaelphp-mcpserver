# Error Handling and Correlation IDs

This guide explains how Ishmael handles unexpected errors in web requests and how to enable robust error boundaries, content negotiation (JSON/HTML), and correlation IDs for tracing.

## Overview

Phase‑10 added a consistent error‑handling story for apps and modules:

- ErrorBoundaryMiddleware catches unhandled exceptions from your route handlers and downstream middleware.
- Returns a 500 Internal Server Error with content negotiation:
  - application/json → structured JSON error payload
  - text/html (default) → minimal HTML error page
- Always includes a correlation id header (X-Correlation-Id) so operators can find the associated log entry.
- Uses RequestIdMiddleware (X-Request-Id) when present to propagate the same id into logs and responses.
- The global exception handler in bootstrap/app.php mirrors the same behavior if an error escapes the router.

These additions are non‑breaking and can be enabled via the HTTP middleware stack or left as defaults for the global handler only.

## Enabling ErrorBoundaryMiddleware

Add the middleware to your global HTTP pipeline so all routes benefit from the boundary:

```php
// config/app.php
return [
    // ...
    'http' => [
        'middleware' => [
            Ishmael\Core\Http\Middleware\RequestIdMiddleware::class, // optional but recommended
            Ishmael\Core\Http\Middleware\ErrorBoundaryMiddleware::class,
        ],
    ],
];
```

- RequestIdMiddleware accepts an incoming X-Request-Id header or generates a UUIDv4, stores it in app('request_id'), and adds X-Request-Id to the response.
- ErrorBoundaryMiddleware will use that id as the correlation id when rendering errors; otherwise it generates a new UUID.

## What clients receive on error

- JSON (when Accept includes application/json):

```json
{
  "error": {
    "id": "9f1c2e80-...",
    "status": 500,
    "title": "Internal Server Error",
    "detail": "An unexpected error occurred"
  }
}
```

- HTML (default): a minimal error page with a Correlation Id rendered.

In both cases, the response will include:

- Content-Type: application/json or text/html
- X-Correlation-Id: <same id as error.error.id>
- When RequestIdMiddleware is enabled, the response will also have X-Request-Id.

## Developer vs Production output

The error detail is controlled by APP_DEBUG (or app.debug config):

- APP_DEBUG=true → detail includes exception message and, for JSON, a trace array.
- APP_DEBUG=false → safe generic message.

This applies in both the ErrorBoundaryMiddleware and the global exception handler.

## Logging with correlation id

All logger channels are wrapped with a RequestIdProcessor which injects request_id into log records when app('request_id') is set.

When an unhandled exception occurs, ErrorBoundaryMiddleware writes a critical log entry with context:

- exception class
- message
- file/line
- method and path
- request_id (correlation id)

Use the X-Correlation-Id from the response to find the corresponding log line.

## Global exception handler parity

bootstrap/app.php registers a global exception handler (disabled during tests) that:

- Logs critical with the correlation id.
- Negotiates JSON vs HTML error responses for non‑CLI requests.
- Emits X-Correlation-Id in headers and renders the id in HTML output.

This provides safety even when an exception escapes the router or the middleware pipeline is not configured.

## Custom error pages

You can override production error pages by replacing the HTML body in ErrorBoundaryMiddleware (recommended: create your own middleware that wraps/extends the behavior) or by serving module/app templates when APP_DEBUG=false. For JSON APIs, prefer the provided structure and add application‑specific fields via your own outer boundary.

## Summary of headers

- X-Request-Id: Set by RequestIdMiddleware for every request/response.
- X-Correlation-Id: Set on error responses (from ErrorBoundaryMiddleware or global handler). Uses app('request_id') when available; otherwise generated anew.

## Quick checklist

- Enable RequestIdMiddleware and ErrorBoundaryMiddleware in config/app.php → http.middleware.
- Ensure APP_DEBUG=false in production for safe messages.
- Verify logs include request_id and that operators can trace using X-Correlation-Id.
