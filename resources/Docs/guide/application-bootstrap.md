# Application Bootstrap and Kernel (SkeletonApp Integration)

This guide explains how Ishmael boots your application, how the SkeletonApp front controller is wired, and which environment variables are used.

## Startup flow
1. public/index.php (your app)
   - Loads Composer autoload.
   - Defines ISH_APP_BASE to ensure paths resolve to your app (recommended when using the framework from vendor).
   - Defines ISH_BOOTSTRAP_ONLY=true and requires vendor/ishmael/framework/bootstrap/app.php.
   - Creates the Kernel (Ishmael\Core\App), calls boot() and then handle(Request).
   - Emits the Response and calls terminate().

2. vendor/ishmael/framework/bootstrap/app.php (single-file bootstrap)
   - Loads helper functions and .env (idempotent).
   - Reads config (config/app.php, config/logging.php).
   - Initializes the Logger.
   - Discovers modules in the configured modules path.
   - If ISH_BOOTSTRAP_ONLY is not defined (or false), it will also dispatch the current request using the legacy Router for BC. When used by SkeletonApp, we set ISH_BOOTSTRAP_ONLY=true so the Kernel controls handling.

3. Kernel (Ishmael\Core\App)
   - boot(): idempotent initialization (safe to call multiple times).
   - handle(Request): delegates to the Router, capturing output and status into a Response value object.
   - terminate(Request, Response): currently a no-op, reserved for future cleanup/logging.

## Minimal front controller
Below is the minimal public/index.php used by the SkeletonApp:

```php
<?php
declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

define('ISH_BOOTSTRAP_ONLY', true);
require __DIR__ . '/../vendor/ishmael/framework/bootstrap/app.php';

use Ishmael\Core\App;
use Ishmael\Core\Http\Request;

$app = new App();
$app->boot();

$request = Request::fromGlobals();
$response = $app->handle($request);

http_response_code($response->getStatusCode());
echo $response->getBody();

$app->terminate($request, $response);
```

## Environment variables
The bootstrap and Kernel read common environment variables from your .env file. You can create or edit .env in your project root. If missing, a sensible default is created automatically on first run.

- APP_NAME: Application display name (default: Ishmael)
- APP_ENV: Runtime environment (development, production, testing)
- APP_DEBUG: Enable verbose error output when true
- APP_URL: Base URL used in links and metadata
- LOG_CHANNEL: Logging channel (default: stack)
- LOG_LEVEL: Minimum log level (debug, info, warning, error)
- DB_CONNECTION, DB_HOST, DB_PORT, DB_DATABASE, DB_USERNAME, DB_PASSWORD: Database parameters reserved for database layer (may not be used in Kernel v1)

## Key properties
- Single-file bootstrap: vendor/ishmael/framework/bootstrap/app.php can be included by any host app.
- Idempotent boot: calling App::boot() multiple times is safe.
- Clear flow: index.php → bootstrap/app.php → App::boot() → App::handle() → App::terminate().

This foundation will be extended in later phases with richer Request/Response and an emitter for headers, as well as middleware and ergonomic routing APIs.


---

## Related reference
- Reference: [Config Keys](../reference/config-keys.md)
- Reference: [Core API (Markdown stubs)](../reference/core-api/_index.md)
