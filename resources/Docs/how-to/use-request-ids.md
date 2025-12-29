# How-To: Use Request IDs in Logs

Adding a correlation ID to each request makes it trivial to follow a request through your logs and across services.

## What it does
- Accepts an incoming `X-Request-Id` header if present, or generates a UUIDv4.
- Makes the ID available on the request object and adds `X-Request-Id` to the response headers.
- Enriches all log entries with `request_id` via a processor.

## Enable the middleware
Register `RequestIdMiddleware` in your global middleware stack or for specific routes. Example using the Router:
```php
$router->group(['middleware' => [\Ishmael\Core\Http\Middleware\RequestIdMiddleware::class]], function() use ($router) {
    $router->get('/hello', 'HelloWorld\\Controllers\\HomeController@index');
});
```

## Inspect the response
Use your browser dev tools or `curl -i` to confirm the `X-Request-Id` header is present on responses.

## See it in logs
Every log line will include a `request_id` field when the middleware is active. Example:
```json
{"ts":"2025-11-03T14:41:00+00:00","lvl":"info","msg":"Handled /hello","request_id":"b1e2..."}
```

## Propagating downstream
When calling other services, forward the `X-Request-Id` header so logs across systems can be correlated.


---

## Related reference
- Reference: [Logging](../reference/logging.md)
- Reference: [Core API (Markdown stubs)](../reference/core-api/_index.md)
