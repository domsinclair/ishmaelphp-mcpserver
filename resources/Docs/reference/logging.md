# Reference: Logging Configuration and API

This page lists configuration options for logging and summarizes the API surface you can rely on.

## Config structure
File: `config/logging.php`

Keys:
- `default`: the default channel name (env: `LOG_CHANNEL`)
- `channels`: map of channel definitions

Common per-channel keys:
- `driver`: one of `single`, `daily`, `stderr`, `null`, `stack`, `monolog`
- `level`: minimum level to log (`debug`, `info`, `notice`, `warning`, `error`, `critical`, `alert`, `emergency`)
- `format`: `json` (JSON Lines) or `line`

Driver-specific keys:
- `single`:
  - `path`: absolute path to the log file
- `daily`:
  - `path`: base file path (date suffix appended automatically)
  - `days`: retention count
- `stderr`:
  - no additional keys; optional `format`
- `stack`:
  - `channels`: array of child channel names
- `monolog`:
  - `handler`: `stream`, `rotating_file`, `error_log`, `syslog`
  - `path`: for `stream` or `rotating_file` (use `php://stderr` for stderr)
  - `with.max_files`: for `rotating_file`

## Environment variables
- `LOG_CHANNEL`: selects the default channel (e.g., `daily`, `monolog`)
- `LOG_LEVEL`: sets the minimum log level threshold

## JSON Lines standard
Ishmael standardizes on JSON Lines for structured logs: one JSON object per line, newline-terminated. Keys typically include:
- `ts`: ISO8601 timestamp with timezone
- `lvl`: lower-case PSR-3 level
- `msg`: the message string
- `app`: application name
- `env`: environment name
- `request_id`: correlation ID when middleware is enabled
- `context`: free-form structured context

## Developer API
- PSR-3 interface: `Psr\Log\LoggerInterface`
- Facade (optional): `Ishmael\Core\Support\Log`
- Logger manager: `Ishmael\Core\Log\LoggerManager` with `channel($name)` and `default()`

## Examples
Selecting a specific channel:
```php
$securityLog = app(\Ishmael\Core\Log\LoggerManager::class)->channel('security');
$securityLog->notice('Login attempt', ['username' => $name, 'success' => false]);
```

Using the facade:
```php
use Ishmael\Core\Support\Log;
Log::info('Hello route hit', ['module' => 'HelloWorld']);
```

## Notes
- On Windows, use `DIRECTORY_SEPARATOR` for cross-platform file paths.
- In containers, prefer stderr to integrate with your orchestrator’s logging.
