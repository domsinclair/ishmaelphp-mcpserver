# Blog Tutorial — Part 5: Routing, Named Routes, and Middleware

In Part 5 you will:
- Ensure routes are named and organized.
- Use a route() helper or named routes from controllers/views.
- Add CSRF protection to forms and demonstrate a simple rate limiting middleware example.

Prerequisites:
- Parts 1–4 completed. CRUD views exist.

## 1) Name your routes

Open `Modules/Blog/routes.php` and ensure each route has a name:

```php
<?php
use Ishmael\Core\Routing\Router;
use Modules\Blog\Controllers\PostController;

/** @var Router $router */
$router->get('/blog/posts', [PostController::class, 'index'])->name('blog.posts.index');
$router->get('/blog/posts/create', [PostController::class, 'create'])->name('blog.posts.create');
$router->post('/blog/posts', [PostController::class, 'store'])->name('blog.posts.store');
$router->get('/blog/posts/{id}', [PostController::class, 'show'])->name('blog.posts.show');
$router->get('/blog/posts/{id}/edit', [PostController::class, 'edit'])->name('blog.posts.edit');
$router->post('/blog/posts/{id}', [PostController::class, 'update'])->name('blog.posts.update');
$router->post('/blog/posts/{id}/delete', [PostController::class, 'destroy'])->name('blog.posts.destroy');
```

## 2) Generate URLs from names

If your app exposes a `route($name, $params = [])` helper (see How‑to: Generate URLs in views/controllers), you can update views to use it:

```php
<a href="<?php echo route('blog.posts.create'); ?>">New Post</a>
<a href="<?php echo route('blog.posts.show', ['id' => (int)$post['id']]); ?>">View</a>
```

In controllers, redirect using names if a helper is available, or continue using string paths:

```php
return $response->redirect(route('blog.posts.show', ['id' => $id]));
```

## 3) CSRF protection for forms and XHR

As of Phase‑14, CSRF protection is enabled by default for state‑changing methods (POST/PUT/PATCH/DELETE) via global middleware. Ensure your forms include a CSRF token field. If your version provides `csrfField()` helper, add it inside `<form>`:

```php
<form method="post" action="<?php echo route('blog.posts.store'); ?>">
    <?php echo csrfField(); ?>
    <!-- fields -->
</form>
```

If you don’t have the helper, include a hidden input named according to your middleware’s expectation and pull the token from session.

For JavaScript/XHR requests, expose a meta tag in your base layout and send the token as a header:

```php
<!-- layout.php <head> -->
<?= function_exists('csrfMeta') ? csrfMeta() : '' ?>
```

```js
// In your JS file
function csrfTokenFromMeta() {
  const m = document.querySelector('meta[name="csrf-token"]');
  return m ? m.getAttribute('content') : null;
}

async function postJson(url, data) {
  const headers = { 'Content-Type': 'application/json' };
  const t = csrfTokenFromMeta();
  if (t) headers['X-CSRF-Token'] = t;
  const res = await fetch(url, { method: 'POST', headers, body: JSON.stringify(data) });
  if (!res.ok) throw new Error('Request failed');
  return res.json();
}
```

To bypass CSRF for legitimate cross‑origin endpoints like public webhooks, you can mark specific routes without CSRF if your router exposes that API, e.g. `Router::post('/webhook/...', handler)->withoutCsrf();` Use sparingly.

## 4) Example rate limiting middleware

Create a simple middleware (outline):

```php
<?php

declare(strict_types=1);

namespace Modules\Blog\Middleware;

use Ishmael\Core\Http\Request;
use Ishmael\Core\Http\Response;

final class SimpleRateLimit
{
    public function __invoke(Request $request, Response $response, callable $next): Response
    {
        // Pseudo: allow 60 requests/min per IP
        // Implement using cache or in-memory store depending on your environment.
        return $next($request, $response);
    }
}
```

Attach it to selected routes in `routes.php` according to your router’s API (see Guides: Middleware Pipeline).

## Exact classes and methods referenced
- Router: `Ishmael\Core\Routing\Router::{get,post}` and route naming via `->name()`
- Controller: `Modules\Blog\Controllers\PostController`
- Middleware example: `Modules\Blog\Middleware\SimpleRateLimit::__invoke`

## Related reading
- Guide: [Routing](./routing.md) and [Routing v2: Parameters, Constraints, and Named Routes](./routing-v2-parameters-constraints-and-named-routes.md)
- Guide: [Middleware Pipeline](./middleware-pipeline.md)
- How‑to: [Generate URLs in views/controllers](../how-to/generate-urls-in-views-and-controllers.md)
- How‑to: [Add Middleware](../how-to/add-middleware.md)

## What you learned
- How to work with named routes in views and controllers.
- How to add CSRF protection to forms.
- How to introduce and attach a simple rate limiting middleware.

---

Looking for a reusable Markdown editor module with live preview and a comprehensive, step‑by‑step walkthrough? See the dedicated Part 5.5:

- Interlude 5.5: Reusable Markdown Editor Module (Full Guide) → [blog-part-5-5-markdown-editor-module.md](./blog-part-5-5-markdown-editor-module.md)
