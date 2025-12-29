# Blog Tutorial — Part 9: Authors, Editing Workflow, and Content Format (Plain Text vs Markdown)

In this add‑on to the Blog series we answer common questions about who can publish, how posts are authored, what format to use (plain text or Markdown), and where that content lives. We also include small, drop‑in examples you can adapt to your module.

What you’ll learn:
- Define who can create/edit posts (roles/permissions) and how to enforce it with middleware.
- Build simple create/edit forms for posts.
- Choose a content format strategy: plain text, Markdown, or pre‑rendered HTML.
- Store and render Markdown safely.

Prerequisites:
- You’ve completed Parts 1–5 (module, routes, controllers, views, and middleware basics).
- You have a Blog module with a Post resource.

## 1) Who is allowed to add posts?

In most apps, only authenticated users with an Author or Admin capability should create posts. The exact identity and auth layer is up to your app; here’s a minimal “role check” middleware you can adapt.

Example middleware Modules/Blog/Middleware/RequireAuthor.php:

```php
<?php

declare(strict_types=1);

namespace Modules\Blog\Middleware;

use Ishmael\Core\Http\Request;
use Ishmael\Core\Http\Response;
use Ishmael\Core\Contracts\Middleware;

final class RequireAuthor implements Middleware
{
    public function handle(Request $request, callable $next): Response
    {
        $user = $request->getAttribute('user'); // however your app sets the current user
        if (!$user) {
            return new Response(302, ['Location' => '/login']);
        }

        if (!in_array('author', $user->roles ?? [], true) && !in_array('admin', $user->roles ?? [], true)) {
            return new Response(403)->withBody('Forbidden: author role required');
        }

        return $next($request);
    }
}
```

Register it on create/store/edit/update/destroy routes in Modules/Blog/routes.php:

```php
<?php
use Ishmael\Core\Routing\Router;
use Modules\Blog\Controllers\PostController;
use Modules\Blog\Middleware\RequireAuthor;

/** @var Router $router */
$router->group(function (Router $r) {
    $r->get('/blog/posts/create', [PostController::class, 'create'])->name('blog.posts.create');
    $r->post('/blog/posts', [PostController::class, 'store'])->name('blog.posts.store');
    $r->get('/blog/posts/{id}/edit', [PostController::class, 'edit'])->name('blog.posts.edit');
    $r->post('/blog/posts/{id}', [PostController::class, 'update'])->name('blog.posts.update');
    $r->post('/blog/posts/{id}/delete', [PostController::class, 'destroy'])->name('blog.posts.destroy');
})->middleware(RequireAuthor::class);
```

This keeps read‑only actions (index/show) public, while protecting authoring actions.

Tip: If you already have app‑level auth, swap in your own middleware or guards.

## 2) What format is a post’s body? (plain text or Markdown)

There are three common strategies:

- Plain text only: simplest, but limited formatting.
- Markdown stored and rendered on the fly: flexible and readable in the DB, render to HTML at request time.
- Markdown stored plus cached HTML: store raw Markdown and a generated HTML column for fast display; regenerate HTML when the Markdown changes.

A small schema that supports both plain text and Markdown with optional cached HTML:

```
posts
- id (int, PK)
- title (string)
- content (text)           // raw body, plain or markdown
- content_format (string)  // 'plain' | 'markdown'
- content_html (text, nullable) // optional cache of rendered HTML
- author_id (int, FK to users)
- created_at, updated_at (timestamps)
```

Migration snippet (pseudo‑migration; adapt to your DB/migration layer):

```php
$this->table('posts')
    ->addColumn('title', 'string')
    ->addColumn('content', 'text')
    ->addColumn('content_format', 'string', ['default' => 'plain'])
    ->addColumn('content_html', 'text', ['null' => true])
    ->addColumn('author_id', 'integer')
    ->addTimestamps()
    ->create();
```

## 3) Create/edit forms (no special editor required)

You can start with a standard HTML form. Later you can enhance it with a Markdown editor widget if you like, but it’s optional.

Views/posts/_form.php:

```php
<?php /** @var array{post?: array} $data */ $post = $data['post'] ?? []; ?>
<form method="post" action="<?= $data['action'] ?>">
  <?= function_exists('csrfField') ? csrfField() : '' ?>
  <label>Title
    <input type="text" name="title" value="<?= htmlspecialchars($post['title'] ?? '') ?>" required />
  </label>

  <label>Format
    <select name="content_format">
      <?php $fmt = $post['content_format'] ?? 'plain'; ?>
      <option value="plain" <?= $fmt === 'plain' ? 'selected' : '' ?>>Plain text</option>
      <option value="markdown" <?= $fmt === 'markdown' ? 'selected' : '' ?>>Markdown</option>
    </select>
  </label>

  <label>Body
    <textarea name="content" rows="12" required><?= htmlspecialchars($post['content'] ?? '') ?></textarea>
  </label>

  <button type="submit">Save</button>
</form>
```

Views/posts/create.php:

```php
<?php $this->extend('layout'); ?>
<h1>New Post</h1>
<?php $this->insert('posts/_form', ['action' => route('blog.posts.store')]); ?>
```

Views/posts/edit.php:

```php
<?php /** @var array $post */ ?>
<?php $this->extend('layout'); ?>
<h1>Edit Post</h1>
<?php $this->insert('posts/_form', [
  'action' => route('blog.posts.update', ['id' => $post['id']]),
  'post' => $post,
]); ?>
```

## 4) Rendering Markdown

Pick any Markdown library. A popular choice is league/commonmark.

Install (project root):

```bash
composer require league/commonmark:^2.4
```

PostService rendering helper (e.g., SkeletonApp/Modules/Blog/Services/PostService.php):

```php
<?php

declare(strict_types=1);

namespace Modules\Blog\Services;

use League\CommonMark\CommonMarkConverter;

final class PostService
{
    private ?CommonMarkConverter $md = null;

    private function markdown(): CommonMarkConverter
    {
        return $this->md ??= new CommonMarkConverter([
            'html_input' => 'strip',   // prevent raw HTML injection
            'allow_unsafe_links' => false,
        ]);
    }

    public function renderBody(array $post): string
    {
        if (($post['content_format'] ?? 'plain') === 'markdown') {
            return $this->markdown()->convert($post['content'] ?? '')->getContent();
        }
        // Plain text → escape for HTML
        return nl2br(htmlspecialchars($post['content'] ?? ''));
    }
}
```

Use it in your controller when showing a post:

```php
$bodyHtml = $postService->renderBody($post);
return $response->withBody($this->view('posts/show', compact('post', 'bodyHtml')));
```

Views/posts/show.php:

```php
<?php $this->extend('layout'); ?>
<article class="post">
  <h1><?= htmlspecialchars($post['title']) ?></h1>
  <div class="post-body"><?= $bodyHtml ?></div>
</article>
```

### Optional: Cache rendered HTML

To avoid converting Markdown on every request, cache it when saving:

```php
// In PostController::store / ::update after validation
if ($data['content_format'] === 'markdown') {
    $data['content_html'] = $postService->renderBody($data); // render once
} else {
    $data['content_html'] = nl2br(htmlspecialchars($data['content']));
}
// save $data including content_html
```

Then in show action prefer content_html if present.

## 5) Where is Markdown stored?

- In the database: the raw Markdown text goes into posts.content.
- Optional: store its pre‑rendered HTML in posts.content_html for speed.

This keeps posts portable and versionable, and does not require special files.

## 6) Putting it together in the controller

Minimal PostController excerpts for create/store/edit/update:

```php
public function create(Request $req, Response $res): Response
{
    return $res->withBody($this->view('posts/create'));
}

public function store(Request $req, Response $res, PostService $svc): Response
{
    $data = [
        'title' => trim((string) $req->input('title')),
        'content' => (string) $req->input('content'),
        'content_format' => $req->input('content_format') === 'markdown' ? 'markdown' : 'plain',
        'author_id' => (int) $req->getAttribute('user')->id,
    ];

    // Optionally pre-render
    $data['content_html'] = $data['content_format'] === 'markdown'
        ? $svc->renderBody($data)
        : nl2br(htmlspecialchars($data['content']));

    // TODO: persist using your Post model/repository

    return $res->withStatus(302)->withHeader('Location', route('blog.posts.index'));
}

public function edit(Request $req, Response $res, int $id): Response
{
    // $post = ... load from DB
    return $res->withBody($this->view('posts/edit', compact('post')));
}

public function update(Request $req, Response $res, int $id, PostService $svc): Response
{
    // Similar to store(); persist changes
    return $res->withStatus(302)->withHeader('Location', route('blog.posts.show', ['id' => $id]));
}
```

## FAQ

- Do I need a special editor for Markdown? No. A plain textarea works. You can later add a JS Markdown editor (EasyMDE, ToastUI, etc.) without changing the server code.
- Do I need special display views for Markdown? Not really; render Markdown to HTML server‑side (or from the cached HTML column) and output it where the body goes. That’s it.

## Related reading
- Part 2: [Schema, Migrations, and Seeds](./blog-part-2-schema-migrations-seeds.md)
- Part 3: [Controllers and Service](./blog-part-3-controllers-and-service.md)
- Part 4: [Views and Layout](./blog-part-4-views-and-layout.md)
- Part 5: [Routing and Middleware](./blog-part-5-routing-and-middleware.md)
- Next: [Part 10 — Images and Storage](./blog-part-10-images-and-storage.md)
