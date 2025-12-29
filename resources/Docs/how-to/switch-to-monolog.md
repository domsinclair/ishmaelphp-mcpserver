# How-To: Switch to Monolog

Monolog provides a rich ecosystem of handlers and processors. Ishmael makes it easy to switch to Monolog while preserving our JSON Lines standard.

## 1) Install Monolog (if not already present)
If your app is standalone:
```
composer require monolog/monolog:^3.0
```
(SkeletonApp already vendors Monolog.)

## 2) Update your environment
```
LOG_CHANNEL=monolog
LOG_LEVEL=info
```

## 3) Configure the `monolog` channel
Add or adjust the `monolog` entry in `config/logging.php`:
```php
'monolog' => [
    'driver'  => 'monolog',
    'handler' => 'stream', // stream|rotating_file|error_log|syslog
    'path'    => storage_path('logs' . DIRECTORY_SEPARATOR . 'ishmael.log'),
    'level'   => env('LOG_LEVEL', 'info'),
    'format'  => 'json', // standardized as JSON Lines
    // Optional Monolog-specific options
    'with' => [
        // For rotating_file
        'max_files' => 14,
    ],
],
```

## 4) Handlers cheat sheet
- `stream`: writes to a single file (good for dev)
- `rotating_file`: daily rotation with retention (`with.max_files`)
- `error_log`: PHP error_log handler
- `syslog`: system log (Linux/macOS)
- `stderr`: use `handler=stream` with `path=php://stderr` for containers

## 5) JSON Lines with Monolog
Monolog’s JsonFormatter supports newline-terminated JSON. Ishmael configures the Monolog bridge to emit one JSON object per line so your logs remain easy to parse and tail.

## 6) Verify
- Hit your app and inspect `storage/logs` (or the configured sink).
- Ensure each line is a single JSON object with keys like `ts`, `lvl`, `msg`, `request_id`.

## 7) Rollback
Set `LOG_CHANNEL` back to `daily` (or `single`) to use the lightweight native channels again.


---

## Related reference
- Reference: [Logging](../reference/logging.md)
- Reference: [Config Keys](../reference/config-keys.md)
- Reference: [Core API (Markdown stubs)](../reference/core-api/_index.md)
