# Controllers & Views

Ishmael controllers extend the `Ishmael\Core\Controller` base class and can render PHP views or return JSON responses.

Recommended pattern (Response‑centric):

In pipeline‑based apps, actions should return an `Ishmael\Core\Http\Response`. Since `Controller::render()` echoes by default, capture the output and place it into the Response body.

```php
use Ishmael\Core\Http\Response;

public function index(): Response
{
    // Any shared data for layouts/partials
    $this->data['appName'] = 'My App';

    ob_start();
    $this->render('home', ['message' => 'Hello']);
    $html = (string) ob_get_clean();

    $res = new Response();
    $res->setBody($html);
    return $res;
}
```

Legacy variant (direct output):

If you’re not yet using the middleware pipeline, you can render directly.

```php
public function index(): void
{
    $this->render('home', ['message' => 'Hello']);
}
```

Example JSON action:

```php
public function api(): void
{
    $this->json(['ok' => true]);
}
```

Notes
- The `render()` method expects a view path relative to your module’s `Views/` directory without the `.php` suffix (e.g., `'posts/index'`).
- Layouts are optional. A child view can set `$layoutFile = 'layout';` and use `$sections` to define/yield content. See the Views & Layouts guide for details.

### Example using sections (recommended with layouts)

When a child view opts into a layout by setting `$layoutFile`, define sections using the `$sections` helper. The most common sections are `title` and `content`.

Child view (Modules/Blog/Views/posts/index.php):

```php
<?php $layoutFile = 'layout'; ?>
<?php /** @var Ishmael\Core\ViewSections $sections */ ?>
<?php /** @var array<int,array{id:int,title:string}> $posts */ ?>

<?php $sections->start('title'); ?>Posts<?php $sections->end(); ?>

<?php $sections->start('content'); ?>
  <h1>Posts</h1>
  <ul>
    <?php foreach ($posts as $p): ?>
      <li>
        <a href="<?php echo $route('posts.show', ['id' => $p['id']]); ?>">
          <?php echo htmlspecialchars($p['title'], ENT_QUOTES, 'UTF-8'); ?>
        </a>
      </li>
    <?php endforeach; ?>
    <?php if (!$posts): ?><li>None yet</li><?php endif; ?>
  </ul>
<?php $sections->end(); ?>
```

Layout (Modules/Blog/Views/layout.php):

```php
<?php /** @var Ishmael\Core\ViewSections $sections */ ?>
<!doctype html>
<html>
  <head>
    <meta charset="utf-8">
    <title><?php echo htmlspecialchars($sections->yield('title', 'My App'), ENT_QUOTES, 'UTF-8'); ?></title>
  </head>
  <body>
    <main><?php echo $sections->yield('content'); ?></main>
  </body>
  </html>
```

Controller (Response‑centric):

```php
use Ishmael\Core\Http\Response;

public function index(): Response
{
    $posts = [/* ... */];
    ob_start();
    $this->render('posts/index', ['posts' => $posts]);
    $html = (string) ob_get_clean();
    return (new Response())->setBody($html);
}
```

See “Views and Layouts” for many more end‑to‑end examples, including absolute and relative layout paths, auto‑content fallback, and partials.

---

## Related reference
- Guide: [Views and Layouts](views-and-layouts.md)
- Reference: [Routes](../reference/routes/_index.md)
- Reference: [Core API (Markdown stubs)](../reference/core-api/_index.md)
