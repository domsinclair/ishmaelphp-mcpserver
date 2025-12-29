# Logging Overview

This guide explains how logging works in Ishmael, the defaults you get out of the box, and how to configure or extend it. The design prioritizes simplicity for newcomers while remaining PSR-3 compatible and flexible for advanced users.

## Key points
- PSR-3 everywhere: use `Psr\Log\LoggerInterface` in your code.
- Development default: write JSON Lines to a log file under `storage/logs`.
- Request correlation: optional `X-Request-Id` middleware to tag all log lines for a request.
- Easy switch to Monolog with equivalent JSON Lines output.

## Default behavior (development)
- Default channel: `daily` (rotates logs by date) or `single` depending on config.
- Location: `storage/logs/ishmael.log` (single) or `storage/logs/ishmael-YYYY-MM-DD.log` (daily).
- Format: JSON Lines — each log record is one JSON object on a single line.

Example JSON Lines entry:
```json
{"ts":"2025-11-03T14:41:00+00:00","lvl":"info","msg":"Hello route hit","app":"Ishmael Skeleton","env":"local","request_id":"c7e5...","context":{"module":"HelloWorld"}}
```

## Quick start examples

Using the facade (newcomer-friendly):
```php
use Ishmael\Core\Support\Log;

Log::info('User logged in', ['user_id' => 42]);
Log::warning('Slow query', ['ms' => 142]);
Log::error('Payment failed', ['order_id' => 1001, 'error' => 'declined']);
```

Injecting PSR-3 logger (recommended in core code):
```php
use Psr\Log\LoggerInterface;

class HomeController
{
    public function __construct(private LoggerInterface $log) {}

    public function index($req, $res)
    {
        $this->log->info('Home page hit');
        return $res->html('Hello');
    }
}
```

## Environment variables
- `LOG_CHANNEL` (e.g., `daily`, `single`, `stderr`, `monolog`)
- `LOG_LEVEL` (`debug|info|notice|warning|error|critical|alert|emergency`)

## Request IDs (optional)
Enable `RequestIdMiddleware` to propagate an `X-Request-Id` header and enrich log entries with a `request_id`. See How-To: Use Request IDs.

## Switching to Monolog
Set `LOG_CHANNEL=monolog` and add the channel configuration. Monolog supports JSON output that we standardize as JSON Lines (newline-terminated). See How-To: Switch to Monolog.

## Windows/macOS/Linux
The framework uses `DIRECTORY_SEPARATOR` to ensure paths like `storage/logs` work across platforms. When running in containers, prefer the `stderr` channel in production for centralized log collection.


---

## Related reference
- Reference: [Logging](../reference/logging.md)
- Reference: [Config Keys](../reference/config-keys.md)
- Reference: [Core API (Markdown stubs)](../reference/core-api/_index.md)
