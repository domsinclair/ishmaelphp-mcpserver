# Request & Response

Ishmael provides lightweight Request and Response objects for working with HTTP in controllers and middleware. They make routing, middleware composition, and testing consistent and predictable.

What you’ll learn on this page:
- The Request API (method, URL, headers, query, input, files, attributes)
- The Response API (status, headers, body, and helpers like json/html/redirect/stream)
- Using Request/Response in controllers and middleware
- Parsing JSON safely and returning JSON
- Testing with Request/Response

## 1) Request overview

The Request object is immutable: methods that change state return a new instance.

Common accessors:
```php
$req->getMethod();          // 'GET', 'POST', ...
$req->getUri();             // e.g., '/blog/posts/42?draft=1'
$req->getPath();            // '/blog/posts/42'
$req->query('draft', '0');  // query string param with default
$req->input('title');       // parsed body fields (form-encoded or JSON)
$req->all();                // merged input (query + body), framework-specific
$req->getHeader('Accept');  // returns array of header values
$req->hasHeader('X-Foo');
$req->cookies();            // cookie array (if enabled)
$req->file('image');        // uploaded file (see Files section)
```

Route parameters are passed as action arguments (e.g., `function show(Request $r, Response $w, int $id)`), but can also be available via attributes depending on your router.

### Attributes
Attributes let middleware share data with later middleware/controllers:
```php
$req = $req->withAttribute('user', $user);
$user = $req->getAttribute('user');
```

### Files (uploads)
Uploaded files (if any) expose common methods:
```php
$file = $req->file('image');
$file->getClientOriginalName();
$file->getClientMimeType();
$file->getSize();
$file->moveTo($targetPath);
```
Always validate type and size before saving; see Blog Part 10 for examples.

## 2) Response overview

The Response is also immutable. Create or transform a response through helpers:
```php
$res->withStatus(302);
$res->withHeader('Location', '/login');
$res->withBody($stringOrStream);
```

Helper shortcuts (availability may vary by version, shown here as recommended patterns):
```php
// HTML
$html = '<h1>Hello</h1>';
$res = $res->withHeader('Content-Type', 'text/html; charset=UTF-8')
           ->withBody($html);

// JSON
$payload = ['ok' => true];
$res = $res->withHeader('Content-Type', 'application/json')
           ->withBody(json_encode($payload, JSON_UNESCAPED_SLASHES));

// Redirect
$res = $res->withStatus(302)->withHeader('Location', '/blog/posts');

// Stream a file (download)
$stream = fopen($path, 'rb');
$res = $res->withHeader('Content-Type', 'application/octet-stream')
           ->withHeader('Content-Disposition', 'attachment; filename="report.csv"')
           ->withBody($stream);
```

## 3) Controllers using Request/Response

A typical controller action receives Request and Response and returns a Response:
```php
use Ishmael\Core\Http\Request;
use Ishmael\Core\Http\Response;

final class PostController
{
    public function show(Request $req, Response $res, int $id): Response
    {
        // load post from repo
        $post = $this->repo->find($id);
        if (!$post) {
            return $res->withStatus(404)->withBody('Not found');
        }

        // server-rendered HTML view
        $html = $this->view('posts/show', compact('post'));
        return $res->withHeader('Content-Type', 'text/html; charset=UTF-8')
                  ->withBody($html);
    }
}
```

For JSON endpoints:
```php
public function like(Request $req, Response $res, int $id): Response
{
    $user = $req->getAttribute('user');
    if (!$user) {
        return $res->withStatus(401)->withHeader('Content-Type', 'application/json')
                  ->withBody('{"error":"auth"}');
    }
    $liked = $this->likes->toggle($id, $user->id);
    return $res->withHeader('Content-Type', 'application/json')
              ->withBody(json_encode(['liked' => $liked], JSON_UNESCAPED_SLASHES));
}
```

## 4) Middleware with Request/Response

Middleware receives the Request and a next callable, and returns a Response. Use it to add cross-cutting behavior (auth, logging, CORS, etc.).
```php
use Ishmael\Core\Contracts\Middleware;

final class RequireAuthor implements Middleware
{
    public function handle(Request $request, callable $next): Response
    {
        $user = $request->getAttribute('user');
        if (!$user || !in_array('author', $user->roles ?? [], true)) {
            return (new Response(302))->withHeader('Location', '/login');
        }
        return $next($request);
    }
}
```
See Guide — Middleware Pipeline for registration and ordering.

## 5) Parsing JSON safely

If a request declares `Content-Type: application/json`, parse it and expose values via `input()` or a `json()` accessor if available. If your version of Ishmael doesn’t auto-parse JSON, use the how-to below to safely parse it and validate types.

Tips:
- Check `Content-Type` and reject oversized bodies.
- Decode with `json_decode($raw, true, flags)` and handle errors.
- Do not trust client-provided types; cast/validate.

See How‑to: Parse JSON bodies safely.

## 6) Returning JSON

- Always set `Content-Type: application/json`.
- Use `JSON_UNESCAPED_SLASHES` and `JSON_THROW_ON_ERROR` (if you handle exceptions) for reliability.
- For errors, include an application-specific code and an http status (e.g., 400, 401, 404, 422).

Example:
```php
return $res->withStatus(422)
          ->withHeader('Content-Type', 'application/json')
          ->withBody(json_encode(['error' => 'validation', 'fields' => ['title' => 'required']], JSON_UNESCAPED_SLASHES));
```

## 7) Streaming and downloads

Use a resource stream for large files to avoid loading them fully into memory.
```php
$stream = fopen($path, 'rb');
return $res->withHeader('Content-Type', $mime)
          ->withHeader('Content-Length', (string) filesize($path))
          ->withBody($stream);
```
Set `Cache-Control` appropriately (`no-store` for private media, `max-age` for public assets served via PHP).

## 8) Testing controllers and middleware

Construct a Request with the desired method, path, headers, and body, call your controller action, then assert the Response status, headers, and body.

Example sketch:
```php
$req = (new Request('POST', '/blog/posts'))
  ->withHeader('Content-Type', 'application/x-www-form-urlencoded')
  ->withParsedBody(['title' => 'Hello']);
$res = new Response();

$res = $controller->store($req, $res);

$this->assertSame(302, $res->getStatusCode());
$this->assertSame('/blog/posts', $res->getHeader('Location')[0] ?? null);
```

For middleware, wrap a dummy next callable that returns a Response and assert behavior when conditions fail (e.g., should redirect to /login).

## 9) Related reading
- Guide: [Middleware Pipeline](../guide/middleware-pipeline.md)
- Guide: [Controllers & Views](../guide/controllers-and-views.md)
- Guide: [Routing](../guide/routing.md)
- How‑to: [Parse JSON bodies safely](../how-to/parse-json-bodies-safely.md)
- How‑to: [Generate URLs in views/controllers](../how-to/generate-urls-in-views-and-controllers.md)
- Blog Tutorial: [Part 10 — Images and Storage](../guide/blog-part-10-images-and-storage.md)
