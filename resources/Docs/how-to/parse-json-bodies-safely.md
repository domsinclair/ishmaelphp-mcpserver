# Parse JSON Bodies Safely

To parse JSON request bodies, add a middleware that checks the Content-Type and safely decodes the body. Avoid relying on php://input globally; use Request helpers when available.

```php
namespace App\Http\Middleware;

use Ishmael\Core\Http\Request;
use Ishmael\Core\Http\Response;

final class JsonBodyParser
{
    public function __invoke(Request $req, Response $res, callable $next): Response
    {
        $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
        $ct = strtolower($_SERVER['CONTENT_TYPE'] ?? '');
        if (in_array($method, ['POST','PUT','PATCH']) && str_starts_with($ct, 'application/json')) {
            $raw = file_get_contents('php://input') ?: '';
            $data = json_decode($raw, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                return Response::json(['error' => 'Invalid JSON: ' . json_last_error_msg()], 400);
            }
            // Attach to request attributes (simple example; adapt as your Request supports)
            // $req = $req->withAttribute('json', $data);
        }
        return $next($req, $res);
    }
}
```

Register per-route or globally depending on your needs, and in your controller, read the parsed JSON from the Request/attributes as supported by your application.


---

## Related reference
- Reference: [Routes](../reference/routes/_index.md)
- Reference: [Core API (Markdown stubs)](../reference/core-api/_index.md)
