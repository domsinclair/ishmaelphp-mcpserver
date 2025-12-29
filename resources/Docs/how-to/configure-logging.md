# How-To: Configure Logging

This page shows common configurations to get comprehensive logging quickly.

## TL;DR
- Development default should log to file using JSON Lines.
- Set environment variables in your `.env` or web server:
```
LOG_CHANNEL=daily
LOG_LEVEL=debug
```

## Channels overview
- `single`: write to a single file (easy for local dev)
- `daily`: rotate by day, with retention (recommended default)
- `stderr`: write to stderr (great in containers)
- `null`: no-op (useful in tests)
- `stack`: fan-out to multiple channels
- `monolog`: use Monolog handlers if installed

## Example config/logging.php
```php
return [
    'default' => env('LOG_CHANNEL', 'daily'),
    'channels' => [
        'single' => [
            'driver' => 'single',
            'path'   => storage_path('logs' . DIRECTORY_SEPARATOR . 'ishmael.log'),
            'level'  => env('LOG_LEVEL', 'debug'),
            'format' => 'json', // json|line
        ],
        'daily' => [
            'driver' => 'daily',
            'path'   => storage_path('logs' . DIRECTORY_SEPARATOR . 'ishmael.log'),
            'level'  => env('LOG_LEVEL', 'info'),
            'days'   => 14,
            'format' => 'json',
        ],
        'stderr' => [
            'driver' => 'stderr',
            'level'  => env('LOG_LEVEL', 'warning'),
            'format' => 'json',
        ],
        'null' => [
            'driver' => 'null',
        ],
        'stack' => [
            'driver'   => 'stack',
            'channels' => ['daily', 'stderr'],
            'level'    => env('LOG_LEVEL', 'info'),
        ],
        'monolog' => [
            'driver'  => 'monolog',
            'handler' => 'stream', // stream|rotating_file|error_log|syslog
            'path'    => storage_path('logs' . DIRECTORY_SEPARATOR . 'ishmael.log'),
            'level'   => env('LOG_LEVEL', 'info'),
            'format'  => 'json', // standardized as JSON Lines
            // Additional Monolog-specific options can go here
        ],
    ],
];
```

## JSON Lines format
Ishmael standardizes on JSON Lines (one JSON object per line, newline-terminated). This makes logs machine-parseable and easy to tail/grep.

## Windows/macOS/Linux
Paths use `DIRECTORY_SEPARATOR` so that `storage/logs` resolves correctly across platforms.

## Verify
- Hit a route in your app.
- Check `storage/logs/` for new log files.
- Tail the file and confirm JSON per line.


---

## Related reference
- Reference: [Logging](../reference/logging.md)
- Reference: [Config Keys](../reference/config-keys.md)
- Reference: [Core API (Markdown stubs)](../reference/core-api/_index.md)
