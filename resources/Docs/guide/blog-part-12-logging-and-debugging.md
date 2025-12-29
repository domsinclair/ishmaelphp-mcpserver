# Blog Tutorial — Part 12: Logging and Debugging for the Blog Module

Ishmael ships with a comprehensive, flexible logging system. In this part you’ll enable rich logs for your Blog module to speed up development and diagnose production issues.

What you’ll learn:
- Configure logging channels and formatters.
- Use per-request correlation IDs.
- Add module and request context to logs.
- Emit structured logs from controllers, services, and middleware.
- Capture errors and unusual states.

Prerequisites:
- Read the general guides: [Logging](../guide/logging.md), [Configure Logging](../how-to/configure-logging.md), and [Use Request IDs in Logs](../how-to/use-request-ids.md).

## 1) Ensure logging is configured

Check your app config (config/logging.php or equivalent) includes a default channel and a formatter compatible with your log shipper.

Common setup (conceptual example):

```php
return [
    'default' => 'stack',
    'channels' => [
        'stack' => [
            'driver' => 'stack',
            'channels' => ['daily'],
        ],
        'daily' => [
            'driver' => 'single',
            'path' => storage_path('logs/ishmael.log'),
            'level' => 'debug',
            'formatter' => \Ishmael\Core\Logging\JsonLinesFormatter::class,
        ],
    ],
];
```

## 2) Add a Request ID middleware

Attach a unique correlation ID to every request and add it to the logger context.

```php
final class RequestIdMiddleware implements Middleware
{
    public function handle(Request $request, callable $next): Response
    {
        $id = $request->getHeader('X-Request-ID')[0] ?? bin2hex(random_bytes(12));
        $request = $request->withAttribute('request_id', $id);

        // If you have a Logger instance, push a processor to inject the ID
        $logger = app('logger');
        $logger->pushProcessor(function (array $record) use ($id) {
            $record['extra']['request_id'] = $id;
            return $record;
        });

        $response = $next($request);
        return $response->withHeader('X-Request-ID', $id);
    }
}
```

Register it early in your middleware pipeline so all logs include the ID.

## 3) Emit logs in the Blog module

Example: log key lifecycle events and suspicious states.

Controllers/PostController.php:

```php
public function store(Request $req, Response $res, LoggerInterface $log): Response
{
    $user = $req->getAttribute('user');
    $log->info('Creating blog post', [
        'module' => 'blog',
        'actor' => $user?->id,
        'title' => substr((string) $req->input('title'), 0, 120),
    ]);

    try {
        // ... validate and persist
        $log->notice('Post created', ['module' => 'blog', 'post_id' => 123]);
        return $res->withStatus(302)->withHeader('Location', route('blog.posts.index'));
    } catch (DomainException $e) {
        $log->warning('Domain error while creating post', [
            'module' => 'blog',
            'error' => $e->getMessage(),
        ]);
        return $res->withStatus(422)->withBody('Validation error');
    } catch (Throwable $e) {
        $log->error('Unexpected error while creating post', [
            'module' => 'blog',
            'exception' => [
                'type' => get_class($e),
                'message' => $e->getMessage(),
            ],
        ]);
        throw $e; // Let the global handler format a 500
    }
}
```

## 4) Media uploads: log enough to trace issues

MediaController.php:

```php
public function upload(Request $req, Response $res, LoggerInterface $log): Response
{
    $user = $req->getAttribute('user');
    $file = $req->file('image');

    if (!$file) {
        $log->notice('Upload without file', ['module' => 'blog', 'actor' => $user?->id]);
        return $res->withStatus(422)->withBody('No file');
    }

    $log->info('Uploading image', [
        'module' => 'blog',
        'actor' => $user?->id,
        'mime' => $file->getClientMimeType(),
        'size' => $file->getSize(),
    ]);

    // ... validations and moveTo()

    $log->notice('Image uploaded', [
        'module' => 'blog',
        'path' => $targetPath,
    ]);

    // return JSON as in Part 10
}
```

## 5) Per-module logger (optional)

If you prefer separate log files for the Blog module, define a dedicated channel in your config and inject it. Example service container binding:

```php
// pseudo registration
$container->set('logger.blog', function () {
    return make_channel('blog_daily'); // defined in config
});
```

Then request LoggerInterface $logBlog via that key or wrap a small LoggerAware service inside your module.

## 6) Log correlation and context helpers

Adopt small helpers so every log line carries consistent context:

```php
function blogLog(LoggerInterface $log, array $ctx = []): LoggerInterface {
    $log->pushProcessor(function (array $record) use ($ctx) {
        $record['extra'] = array_merge(['module' => 'blog'], $record['extra'] ?? [], $ctx);
        return $record;
    });
    return $log;
}
```

Use it per request in your controller action.

## 7) Debugging in development

- Set log level to `debug` and tail the file: `tail -f storage/logs/ishmael.log`.
- Enable verbose error pages in your app config for local environments.
- Add temporary `debug` logs around suspect code to trace inputs and decisions.

## 8) Production practices

- Reduce log level to `notice` or `warning`.
- Ensure logs are rotated (daily file or external shipper).
- Include the `request_id` so you can correlate across services.
- Avoid logging sensitive data (passwords, tokens, full content bodies).

## Related reading
- How‑to: [Configure Logging](../how-to/configure-logging.md)
- How‑to: [Use Request IDs in Logs](../how-to/use-request-ids.md)
- Reference: [Logging](../reference/logging.md)
- Previous: [Part 11 — Private Media in Modules](./blog-part-11-private-media-in-modules.md)
