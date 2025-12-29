# Blog Tutorial — Part 3 (Revised): Controllers, Services, Routes, and Working Screens

In this revised Part 3 you will:
- Build PostService and AuthorService with simple, testable methods.
- Implement PostsController and AuthorsController actions that render views (or JSON while developing).
- Generate views with the new make:* CLI commands and wire up routes.
- Add a basic create/edit flow for posts including an Author dropdown and a URL slug.
- End with a fully working (simple) Blog app: list posts, view a post, create and edit posts.

Prerequisites:
- You completed Part 2 (authors + posts migrations and seeds).
- You can run the Ishmael CLI from your app root:

  ```bash
  php vendor/bin/ish
  ```

Tip: This part focuses on controllers/services and minimal views. In Part 4 we’ll polish the views and layout further.

---

## 1) Generate scaffolding (controllers, services, views)

If you haven’t already created these in Part 2, generate them now.

```bash
# Services
php vendor/bin/ish make:service Blog Author
php vendor/bin/ish make:service Blog Post

# Controllers
php vendor/bin/ish make:controller Blog Authors
php vendor/bin/ish make:controller Blog Posts

# Views for each resource (index, show, create, edit, _form)
php vendor/bin/ish make:views Blog authors
php vendor/bin/ish make:views Blog posts
```

This creates files under `Modules/Blog`:
- Services: `Services/AuthorService.php`, `Services/PostService.php`
- Controllers: `Controllers/AuthorsController.php`, `Controllers/PostsController.php`
- Views: `Views/authors/*`, `Views/posts/*`

---

## Controllers vs Services vs Models in IshmaelPHP

Before we implement the services, it’s worth clarifying the roles of Controllers, Services, and Models in an Ishmael app, and why we haven’t needed Models yet for Author and Post.

- Controllers
    - Sit at the HTTP boundary (routes → controller → response).
    - Read input (route params, query/body), call application logic, and choose what to render (HTML view or JSON).
    - Should remain thin: orchestrate, don’t implement business rules or data access.

- Services
    - Hold application/business logic and coordinate data access.
    - In this tutorial we use the framework’s Database helper directly from services and return plain arrays suitable for controllers and views.
    - Are easy to unit-test in isolation and safe to inject into controllers (constructor injection).

- Models
    - Optional domain objects that represent core concepts like Post or Author. They encapsulate behavior and invariants (e.g., slug generation, publish/unpublish rules) and can provide convenience methods.
    - In Ishmael you don’t have to start with Models. Many apps begin with lightweight services that issue SQL and return arrays. You can introduce Models later if/when the domain needs richer behavior.

Why no Models yet for Author and Post?
- Up to now, our needs are simple: list, view, create, and edit using straightforward SQL. Returning arrays keeps the tutorial focused on wiring controllers, services, routes, and views.
- The small scope means we don’t yet need object-level invariants, lifecycle methods, or rich behaviors that justify a Model layer.

When might you add Models later, and what advantages do they bring?
- When the domain grows more behavior:
    - Example: A Post that must auto-generate a unique slug from title, prevent editing after publication, or compute derived properties (excerpt, reading time).
    - Example: An Author with display-name rules, profile URL logic, or gravatar helpers.
- When you want to centralize validation and invariants in one place rather than scattering them across controllers/services.
- When you want clearer, expressive code in services: $post->setTitle($t)->publish() instead of passing around associative arrays and flags.
- When mapping becomes non-trivial: repositories can translate between Models and the database while services operate on Models.
- When you want more ergonomic testing: assert against domain methods and invariants, not just array shapes.

Bottom line
- Controllers remain thin.
- Services hold the application logic and can start out returning arrays.
- Introduce Models when your domain rules, invariants, and behaviors grow enough that encapsulating them provides clarity, safety, and better tests.

With that mental model in place, let’s implement the services for our blog.

## 2) Implement the services

We’ll keep services query‑centric and small. They return plain arrays suitable for controllers and views.

If you worked through Part 2 in its entirety then you will almost certainly have the two service implementations below implemented already.

Please note: while the Author service below is identical, the Post service below has been expanded slightly (pagination helper and slug handling) so copy it verbatim if yours differs.

`Modules/Blog/Services/AuthorService.php`

```php
<?php
declare(strict_types=1);

namespace Modules\Blog\Services;

use Ishmael\Core\Database;

final class AuthorService
{
    public function all(): array
    {
        return Database::query('SELECT * FROM authors ORDER BY name')->fetchAll();
    }

    public function find(int $id): ?array
    {
        $row = Database::query('SELECT * FROM authors WHERE id = :id', ['id' => $id])->fetch();
        return $row ?: null;
    }
}
```

`Modules/Blog/Services/PostService.php`

```php
<?php
declare(strict_types=1);

namespace Modules\Blog\Services;

use Ishmael\Core\Database;

final class PostService
{
    public function paginateWithAuthors(int $page = 1, int $perPage = 10): array
    {
        $offset = ($page - 1) * $perPage;
        $items = Database::query(
            'SELECT p.*, a.name AS author_name FROM posts p JOIN authors a ON a.id = p.author_id ORDER BY p.created_at DESC LIMIT :limit OFFSET :offset',
            ['limit' => $perPage, 'offset' => $offset]
        )->fetchAll();
        $total = (int) Database::query('SELECT COUNT(*) AS c FROM posts')->fetch()['c'];
        return ['items' => $items, 'total' => $total, 'page' => $page, 'perPage' => $perPage];
    }

    public function findById(int $id): ?array
    {
        $row = Database::query('SELECT * FROM posts WHERE id = :id', ['id' => $id])->fetch();
        return $row ?: null;
    }

    public function findBySlug(string $slug): ?array
    {
        $row = Database::query('SELECT * FROM posts WHERE slug = :slug', ['slug' => $slug])->fetch();
        return $row ?: null;
    }

    public function create(array $data): int
    {
        // Minimal validation/sanitization
        $title = trim((string)($data['title'] ?? ''));
        $slug = trim((string)($data['slug'] ?? ''));
        $body = (string)($data['body'] ?? '');
        $authorId = (int)($data['author_id'] ?? 0);
        if ($slug === '' && $title !== '') {
            $slug = $this->slugify($title);
        }

        Database::query(
            'INSERT INTO posts (title, slug, body, author_id, created_at, updated_at) VALUES (:t,:s,:b,:a,CURRENT_TIMESTAMP,CURRENT_TIMESTAMP)',
            ['t' => $title, 's' => $slug, 'b' => $body, 'a' => $authorId]
        );
        return (int) Database::lastInsertId();
    }

    public function update(int $id, array $data): void
    {
        $title = trim((string)($data['title'] ?? ''));
        $slug = trim((string)($data['slug'] ?? ''));
        $body = (string)($data['body'] ?? '');
        $authorId = (int)($data['author_id'] ?? 0);
        if ($slug === '' && $title !== '') {
            $slug = $this->slugify($title);
        }
        Database::query(
            'UPDATE posts SET title = :t, slug = :s, body = :b, author_id = :a, updated_at = CURRENT_TIMESTAMP WHERE id = :id',
            ['t' => $title, 's' => $slug, 'b' => $body, 'a' => $authorId, 'id' => $id]
        );
    }

    public function delete(int $id): void
    {
        // If you enabled soft deletes, change this to set deleted_at instead
        Database::query('DELETE FROM posts WHERE id = :id', ['id' => $id]);
    }

    private function slugify(string $title): string
    {
        $s = strtolower(trim($title));
        $s = preg_replace('/[^a-z0-9]+/i', '-', $s) ?? $s;
        return trim($s, '-');
    }
}
```

Notes
- If you adopted soft deletes (deleted_at) in your schema, replace `delete()` with an UPDATE that sets `deleted_at = CURRENT_TIMESTAMP` and filter accordingly in select queries.
- You can later move validation into a dedicated validator or form request object; here we keep it simple.

---

## 3) Implement the controllers

Controllers coordinate HTTP input/output and delegate to services. The code below renders views directly so that copy–paste immediately produces working pages.

`Modules/Blog/Controllers/AuthorsController.php`

```php
<?php
declare(strict_types=1);

namespace Modules\Blog\Controllers;

use Ishmael\Core\Http\Response;
use Ishmael\Core\Controller;
use Modules\Blog\Services\AuthorService;

final class AuthorsController extends Controller
{
    public function __construct(private AuthorService $authors) {}

    public function index(): Response
    {
        // If you wish to render a view for authors later, you can mirror the PostsController style.
        return Response::json($this->authors->all());
    }
}
```

`Modules/Blog/Controllers/PostsController.php`

```php
<?php
declare(strict_types=1);

namespace Modules\Blog\Controllers;

use Ishmael\Core\Controller;
use Ishmael\Core\Http\Response;
use Modules\Blog\Services\PostService;
use Modules\Blog\Services\AuthorService;

final class PostsController extends Controller
{
    public function __construct(private PostService $posts, private AuthorService $authors) {}

    public function index(): Response
    {
        $page = (int)($_GET['page'] ?? 1);
        $data = $this->posts->paginateWithAuthors($page, 10);
        // render() expects a view path relative to the module's Views/ directory (no .php suffix)
        ob_start();
        $this->render('posts/index', $data);
        $html = (string)ob_get_clean();
        $res = new Response();
        $res->setBody($html);
        return $res;
    }

    public function show(string $slug): Response
    {
        $post = $this->posts->findBySlug($slug);
        if (!$post) { return Response::json(['error' => 'Post not found'], 404); }
        ob_start();
        $this->render('posts/show', ['post' => $post]);
        $html = (string)ob_get_clean();
        $res = new Response();
        $res->setBody($html);
        return $res;
    }

    public function create(): Response
    {
        $authors = $this->authors->all();
        ob_start();
        $this->render('posts/create', ['authors' => $authors]);
        $html = (string)ob_get_clean();
        $res = new Response();
        $res->setBody($html);
        return $res;
    }

    public function edit(int $id): Response
    {
        $post = $this->posts->findById($id);
        if (!$post) { return Response::json(['error' => 'Post not found'], 404); }
        $authors = $this->authors->all();
        ob_start();
        $this->render('posts/edit', ['post' => $post, 'authors' => $authors]);
        $html = (string)ob_get_clean();
        $res = new Response();
        $res->setBody($html);
        return $res;
    }

    public function store(): Response
    {
        $id = $this->posts->create($_POST);
        $res = new Response();
        $res->setStatusCode(302);
        // After creation, redirect to the post show page by slug if available
        $created = $this->posts->findById($id);
        if ($created && !empty($created['slug'])) {
            $res->header('Location', '/blog/p/' . rawurlencode((string)$created['slug']));
        } else {
            // Fallback to index
            $res->header('Location', '/blog/posts');
        }
        return $res;
    }

    public function update(int $id): Response
    {
        $this->posts->update($id, $_POST);
        $res = new Response();
        $res->setStatusCode(302);
        // Redirect to the updated post's slug page if available
        $updated = $this->posts->findById($id);
        if ($updated && !empty($updated['slug'])) {
            $res->header('Location', '/blog/p/' . rawurlencode((string)$updated['slug']));
        } else {
            $res->header('Location', '/blog/posts');
        }
        return $res;
    }

    public function destroy(int $id): Response
    {
        $this->posts->delete($id);
        $res = new Response();
        $res->setStatusCode(302);
        $res->header('Location', '/blog/posts');
        return $res;
    }
}
```

Notes
- Controllers return Ishmael's Response object. For quick JSON endpoints, use `Response::json(...)`.
- The base Controller's `render()` expects view names relative to `Modules/{Module}/Views/` without the `.php` suffix. It echoes output; to return HTML via Response, capture output with `ob_start()` and set it as the Response body (as shown above).
- In production, prefer named routes and a dedicated redirect helper when available. Here we build a 302 Response with a `Location` header for redirects.

### What Ishmael expects from controllers (copy‑paste safe rules)
- Namespace: `Modules\{Module}\Controllers` and class name ends with `Controller`.
- Constructor DI: You may type‑hint your own services; the router will resolve them recursively if they are instantiable.
- Action signatures: You may type‑hint `Ishmael\Core\Http\Request` and/or `...\Response` as the first parameters; route parameters must be scalars and appear after those. Example: `public function show(Request $req, Response $res, string $slug): Response`.
- Rendering: Call `$this->render('folder/view', $vars)` where `folder/view` is under `Modules/{Module}/Views/`.
- Redirects: Return a Response with status 302 and a `Location` header. A `redirect()` helper exists on the base Controller in newer versions.

---

## 4) Routes (Phase 14 ready: session + CSRF)

Edit `Modules/Blog/routes.php` and add routes for posts and authors. If the file doesn’t exist, create it with this wrapper. Since Phase 14, browser routes should run with StartSessionMiddleware first and then VerifyCsrfToken.

```php
<?php
declare(strict_types=1);

use Ishmael\Core\Router;
use Ishmael\Core\Http\Middleware\StartSessionMiddleware;
use Ishmael\Core\Http\Middleware\VerifyCsrfToken;

return function (Router $router): void {
    // Web group: start session then enforce CSRF
    $router->group(['middleware' => [StartSessionMiddleware::class, VerifyCsrfToken::class]], function (Router $r): void {
        // Lists
        // Use short controller names so the Router can prefix the Blog module correctly
        $r->get('/blog/authors', ['AuthorsController', 'index'])->name('blog.authors.index');
        $r->get('/blog/posts', ['PostsController', 'index'])->name('blog.posts.index');

        // Show post by slug (shortened path to avoid collision with static "/blog/posts/create")
        $r->get('/blog/p/{slug:slug}', ['PostsController', 'show'])->name('blog.posts.show');

        // Create/edit
        $r->get('/blog/posts/create', ['PostsController', 'create'])->name('blog.posts.create');
        $r->post('/blog/posts', ['PostsController', 'store'])->name('blog.posts.store');
        $r->get('/blog/posts/{id}/edit', ['PostsController', 'edit'])->name('blog.posts.edit');
        $r->post('/blog/posts/{id}', ['PostsController', 'update'])->name('blog.posts.update');
        $r->post('/blog/posts/{id}/delete', ['PostsController', 'destroy'])->name('blog.posts.destroy');
    });
};
```

Notes
- Wrapping routes in a web group ensures the CSRF helpers (csrfMeta/csrfToken) are available on GET and that POST routes are protected.
- For stateless JSON APIs, use a separate group without session/CSRF and rely on token auth and CORS.

Tip: If you cache routes in production, remember to run:

```bash
php vendor/bin/ish route:cache --env=production
```

---

## 5) Optional: auto-generate routes from views (make:routes)

If you have created your views under `Modules/Blog/Views/posts` and `Modules/Blog/Views/authors`, you can ask the CLI to scan those folders and generate matching routes automatically.

Examples
- Default (web, grouped with session + CSRF):
  - `php vendor/bin/ish make:routes Blog`
  - `php vendor/bin/ish make:routes --module=Blog`
- API/stateless variant (no session/CSRF grouping):
  - `php vendor/bin/ish make:routes Blog --api`

The command will create or update `Modules/Blog/routes.php`, wrapping generated routes in the proper web middleware group by default, and will infer which routes to add based on which view files it finds (index.php, create.php, show.php, edit.php).

---

## 6) Minimal views to make it work

The `make:views` command created starter files in `Modules/Blog/Views/posts`. Replace their contents with the examples below to get a working UI quickly.

Note on the CSRF JavaScript helper used for XHR:
- In this Part 3, we place a tiny helper script directly in each view’s <head> so the examples work standalone.
- In Part 4, you will create a base layout and move this helper into the layout <head> so it loads once for all pages.

`Views/posts/index.php`

```php
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <title>Blog — Posts</title>
  <link rel="stylesheet" href="https://unpkg.com/@picocss/pico@2/css/pico.min.css">
  <style> body { padding: 1.25rem; } table { width: 100%; } </style>
  <?php // Expose CSRF token for XHR; safe to include globally if helpers available ?>
  <?= function_exists('csrfMeta') ? csrfMeta() : '' ?>
  <script>
    function csrf() {
      const m = document.querySelector('meta[name="csrf-token"]');
      return m ? m.getAttribute('content') : null;
    }
    async function postJson(url, data) {
      const headers = { 'Content-Type': 'application/json' };
      const t = csrf(); if (t) headers['X-CSRF-Token'] = t;
      const res = await fetch(url, { method: 'POST', headers, body: JSON.stringify(data) });
      if (!res.ok) throw new Error('Request failed');
      return res.json();
    }
    window.postJson = postJson;
    window.csrf = csrf;
  </script>
</head>
<body>
<main class="container">
  <h1>Posts</h1>
  <p><a href="/blog/posts/create">Create Post</a></p>
  <table>
    <thead><tr><th>Title</th><th>Author</th><th>Actions</th></tr></thead>
    <tbody>
    <?php foreach (($items ?? []) as $p): ?>
      <tr>
        <td><a href="/blog/p/<?= htmlspecialchars($p['slug'] ?? (string)$p['id']) ?>"><?= htmlspecialchars($p['title'] ?? '') ?></a></td>
        <td><?= htmlspecialchars($p['author_name'] ?? '') ?></td>
        <td>
          <a href="/blog/posts/<?= (int)($p['id'] ?? 0) ?>/edit">Edit</a>
          <form action="/blog/posts/<?= (int)($p['id'] ?? 0) ?>/delete" method="post" style="display:inline">
            <?= function_exists('csrfField') ? csrfField() : '' ?>
            <button type="submit" aria-label="Delete">Delete</button>
          </form>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</main>
</body>
</html>
```

`Views/posts/_form.php`

```php
<form method="post" action="<?= htmlspecialchars($action ?? '') ?>">
  <?= function_exists('csrfField') ? csrfField() : '' ?>
  <label>Title
    <input type="text" name="title" value="<?= htmlspecialchars($post['title'] ?? '') ?>" required>
  </label>
  <label>Slug (optional)
    <input type="text" name="slug" value="<?= htmlspecialchars($post['slug'] ?? '') ?>">
  </label>
  <label>Author
    <select name="author_id" required>
      <?php foreach (($authors ?? []) as $a): ?>
        <option value="<?= (int)$a['id'] ?>" <?= isset($post['author_id']) && (int)$post['author_id']===(int)$a['id'] ? 'selected' : '' ?>>
          <?= htmlspecialchars($a['name']) ?>
        </option>
      <?php endforeach; ?>
    </select>
  </label>
  <label>Body
    <textarea name="body" rows="8" required><?= htmlspecialchars($post['body'] ?? '') ?></textarea>
  </label>
  <button type="submit">Save</button>
</form>
```

`Views/posts/create.php`

```php
<?php $post = $post ?? []; $authors = $authors ?? []; $action = '/blog/posts'; ?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <title>Create Post</title>
  <link rel="stylesheet" href="https://unpkg.com/@picocss/pico@2/css/pico.min.css">
  <?= function_exists('csrfMeta') ? csrfMeta() : '' ?>
  <script>
    function csrf() {
      const m = document.querySelector('meta[name="csrf-token"]');
      return m ? m.getAttribute('content') : null;
    }
    async function postJson(url, data) {
      const headers = { 'Content-Type': 'application/json' };
      const t = csrf(); if (t) headers['X-CSRF-Token'] = t;
      const res = await fetch(url, { method: 'POST', headers, body: JSON.stringify(data) });
      if (!res.ok) throw new Error('Request failed');
      return res.json();
    }
    window.postJson = postJson;
    window.csrf = csrf;
  </script>
</head>
<body>
<main class="container">
  <h1>Create Post</h1>
  <?php include __DIR__ . '/_form.php'; ?>
</main>
</body>
</html>
```

`Views/posts/edit.php`

```php
<?php $authors = $authors ?? []; $action = '/blog/posts/' . (int)($post['id'] ?? 0); ?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <title>Edit Post</title>
  <link rel="stylesheet" href="https://unpkg.com/@picocss/pico@2/css/pico.min.css">
  <?= function_exists('csrfMeta') ? csrfMeta() : '' ?>
  <script>
    function csrf() {
      const m = document.querySelector('meta[name=\"csrf-token\"]');
      return m ? m.getAttribute('content') : null;
    }
    async function postJson(url, data) {
      const headers = { 'Content-Type': 'application/json' };
      const t = csrf(); if (t) headers['X-CSRF-Token'] = t;
      const res = await fetch(url, { method: 'POST', headers, body: JSON.stringify(data) });
      if (!res.ok) throw new Error('Request failed');
      return res.json();
    }
    window.postJson = postJson;
    window.csrf = csrf;
  </script>
</head>
<body>
<main class="container">
  <h1>Edit Post</h1>
  <?php include __DIR__ . '/_form.php'; ?>
</main>
</body>
</html>
```

`Views/posts/show.php`

```php
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <title><?= htmlspecialchars($post['title'] ?? 'Post') ?></title>
  <link rel="stylesheet" href="https://unpkg.com/@picocss/pico@2/css/pico.min.css">
</head>
<body>
<main class="container">
  <p><a href="/blog/posts">← Back to posts</a></p>
  <h1><?= htmlspecialchars($post['title'] ?? '') ?></h1>
  <article>
    <p><?= nl2br(htmlspecialchars($post['body'] ?? '')) ?></p>
  </article>
</main>
</body>
</html>
```

These views assume the `PostsController` implementation shown above, which already renders views via `$this->render(...)`.

---

## 6) Try it out

1. Ensure migrations have been run and seeds inserted (from Part 2).
2. Visit `/blog/posts` — you should see your seeded posts.
3. Create a new post at `/blog/posts/create` — pick an author and submit.
4. Click a post title to open `/blog/p/{slug}` — the post should render.
5. Edit a post via the Edit link.
6. Delete a post via the inline Delete button.

If routes don’t change after edits, clear caches:

```bash
php vendor/bin/ish route:clear
php vendor/bin/ish modules:clear
```

---

## 7) Apply these lessons to other apps

The same pattern generalizes beyond the blog:
- Sales app: CustomersService, OrdersService, and controllers; tables `customers`, `orders`, `order_items` with FKs. Use `make:views` to bootstrap UIs quickly.
- Docs/Knowledge app: AuthorsService + ArticlesService with `authors` and `articles` tables; add `revisions` later.
- Soft deletes and auditing: Add `deleted_at` and `timestamps()` in your migrations. Filter on `deleted_at IS NULL` in services by default, and expose `restore()` actions in controllers when needed.

Design guidance:
- Keep controllers thin: parse inputs and orchestrate, but keep SQL in services.
- Model relationships explicitly with foreign keys and add indexes for the columns you filter/join on.
- Prefer named routes; centralize URL generation once your app grows.

---

## Useful references
- Guide: [Controllers & Views](../guide/controllers-and-views.md)
- Database: [Phase 12 — Database Additions](../database/phase-12-database-additions.md)
- How‑to: [Create and Run Seeders](../how-to/create-and-run-seeders.md)
- Reference: [CLI Commands (generated)](../reference/cli-commands.md)

---

## What you learned
- How to implement Controllers and Services that work together.
- How to wire routes and generate starter views with `make:views`.
- How to perform basic create/edit/delete flows tied to foreign keys (Posts → Authors).
- How to carry these patterns into other modules and apps built on Ishmael.

---

## Appendix A — Router expectations and examples (copy‑paste safe)

Ishmael’s Router performs strict compile‑time collision detection and is module‑aware when resolving controller names. Use these rules to avoid common pitfalls.

What the Router expects
- Handlers: Prefer array syntax with short controller names: `['PostsController', 'index']`. The Router will build the FQCN as `Modules\{Module}\Controllers\PostsController` based on the current module.
- Module context: Your `Modules/Blog/routes.php` file is loaded within the Blog module context, so short names resolve under `Modules\Blog\Controllers`.
- Route params: Use `{name}` or `{name:type}`. Built‑in types include `int`, `numeric`, `bool`, `slug`, `alpha`, `alnum`, `uuid`. Example: `/blog/p/{slug:slug}`.
- Collisions: Static paths and parameterized paths cannot overlap for the same method. For example, `/blog/posts/create` collides with `/blog/posts/{slug}` for GET. To fix, either constrain or change the path (we use `/blog/p/{slug:slug}` in this tutorial).
- Naming: Name routes with `->name('module.resource.action')` for easier URL generation later.

Fictitious examples
```php
use Ishmael\Core\Router;

return function (Router $r): void {
    // Module: Blog
    $r->get('/blog/posts', ['PostsController','index'])->name('blog.posts.index');
    $r->get('/blog/p/{slug:slug}', ['PostsController','show'])->name('blog.posts.show');
    $r->post('/blog/posts', ['PostsController','store'])->name('blog.posts.store');

    // Different resource in same module
    $r->get('/blog/authors', ['AuthorsController','index'])->name('blog.authors.index');

    // Admin module (in Modules/Admin/routes.php) — short names resolve to Modules\Admin\Controllers
    $r->get('/admin/reports', ['ReportsController','index'])->name('admin.reports.index');
    // Constrained numeric ID and safe static route living together
    $r->get('/admin/users/create', ['UsersController','create'])->name('admin.users.create');
    $r->get('/admin/users/{id:int}', ['UsersController','show'])->name('admin.users.show');
};
```

Gotchas and troubleshooting
- If you see “Controller not found: Modules\Blog\Controllers\Modules\Blog\Controllers\…”, you likely used a fully qualified class name in routes. Switch to short names like `['PostsController','index']`.
- If you see “View not found … .php.php”, you likely passed an absolute path to `render()`. Pass a module‑relative view name like `posts/index` without the `.php` suffix.
- If your new route conflicts on build, check for static vs parameterized overlaps and constrain or alter the path.
- After changing routes or modules, clear caches if necessary: `php vendor/bin/ish route:clear` and `php vendor/bin/ish modules:clear`.

## Appendix B — Controller expectations and examples (copy‑paste safe)

Minimal controller action signatures
```php
use Ishmael\Core\Http\Request;
use Ishmael\Core\Http\Response;

public function show(Request $req, Response $res, string $slug): Response
{
    // $slug comes from {slug:slug} in the route pattern
    $post = $this->posts->findBySlug($slug);
    if (!$post) { return Response::json(['error' => 'Not found'], 404); }
    ob_start();
    $this->render('posts/show', ['post' => $post]);
    $res->setBody((string)ob_get_clean());
    return $res;
}
```

Fictitious small controller
```php
namespace Modules\Admin\Controllers;

use Ishmael\Core\Controller;
use Ishmael\Core\Http\Response;

final class ReportsController extends Controller
{
    public function index(): Response
    {
        ob_start();
        $this->render('reports/index', ['title' => 'Admin Reports']);
        $html = (string)ob_get_clean();
        $res = new Response();
        $res->setBody($html);
        return $res;
    }
}
```
