# Add CORS

To add CORS support, implement a simple middleware that sets the appropriate headers and short-circuits OPTIONS requests.

```php
namespace App\Http\Middleware;

use Ishmael\Core\Http\Request;
use Ishmael\Core\Http\Response;

final class CorsMiddleware
{
    public function __invoke(Request $req, Response $res, callable $next): Response
    {
        $res->header('Access-Control-Allow-Origin', '*')
            ->header('Access-Control-Allow-Methods', 'GET, POST, PUT, PATCH, DELETE, OPTIONS')
            ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization');

        if (strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') {
            return Response::text('', 204);
        }
        return $next($req, $res);
    }
}
```

Register it globally or per-route:

```php
use Ishmael\Core\Router;
use App\Http\Middleware\CorsMiddleware;

Router::useGlobal([CorsMiddleware::class]);
```

For production, restrict origins and methods to match your security policy.

---

## Related reference
- Reference: [Routes](../reference/routes/_index.md)
- Reference: [Config Keys](../reference/config-keys.md)
- Reference: [Core API (Markdown stubs)](../reference/core-api/_index.md)
