# Views and Layouts

This guide shows how to build views and layouts in IshmaelPHP, how the optional ViewSections helper works, and how layout file paths are resolved on all platforms (Windows, macOS, Linux).

At a glance:
- Views live under Modules/{Module}/Views.
- Layouts are optional; a view can opt-in by setting $layoutFile inside the child view.
- ViewSections is a tiny helper for defining sections in a child view and yielding them in a layout.
- If a child view enables a layout but does not define a content section, Ishmael automatically uses the child view’s entire HTML as the content section.
- Layout file paths can be:
  - Relative to the Views/ root (recommended): 'layout' or 'posts/../layout'
  - Absolute (honored as-is): C:\path\to\layout.php, \\server\share\app\layout.php, or /var/www/app/layout.php

Contents
- Minimal example (no layout)
- Using a layout (relative path)
- Using a layout (absolute path)
- Using ViewSections
- Auto content when sections are not defined
- Path resolution rules (Windows, UNC, Unix)
- Security and best practices

Minimal example (no layout)

Child view Modules/Blog/Views/posts/index.php

<?php /** @var string|null $message */ ?>
<h1>Posts</h1>
<p><?= isset($message) ? htmlspecialchars((string)$message, ENT_QUOTES, 'UTF-8') : 'Welcome'; ?></p>

Controller action

public function index(): void
{
    $this->render('posts/index', ['message' => 'Hello']);
}

Using a layout (relative path)

Child view Modules/Blog/Views/posts/index.php

<?php $layoutFile = 'layout'; ?>
<?php /** @var Ishmael\Core\ViewSections $sections */ ?>
<?php $sections->start('content'); ?>
  <h1>Posts</h1>
<?php $sections->end(); ?>

Layout Modules/Blog/Views/layout.php

<?php /** @var Ishmael\Core\ViewSections $sections */ ?>
<!doctype html>
<html>
  <head><meta charset="utf-8"><title><?= $sections->yield('title', 'Blog'); ?></title></head>
  <body>
    <header>Header</header>
    <main><?= $sections->yield('content'); ?></main>
    <footer>Footer</footer>
  </body>
</html>

You can also use a parent-relative path from a subdirectory:

<?php $layoutFile = '../layout'; ?>

Using a layout (absolute path)

Absolute paths are honored. This is useful in advanced setups or when sharing a common layout across modules.

<?php $layoutFile = __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'layout.php'; ?>

Notes:
- On Windows, both C:\dir\file.php and \\server\share\file.php (UNC) are supported.
- On Unix/macOS, paths starting with / are supported.

Using ViewSections

Child view defines sections:

<?php $layoutFile = 'layout'; ?>
<?php /** @var Ishmael\Core\ViewSections $sections */ ?>
<?php $sections->start('title'); ?>Posts<?php $sections->end(); ?>
<?php $sections->start('content'); ?>
  <h1>All Posts</h1>
<?php $sections->end(); ?>

Layout yields them:

<?php /** @var Ishmael\Core\ViewSections $sections */ ?>
<title><?= $sections->yield('title', 'My App'); ?></title>
<main><?= $sections->yield('content'); ?></main>

Auto content when sections are not defined

If a child view sets $layoutFile but does not call $sections->start('content'), Ishmael uses the entire child output as the 'content' section. This makes it easy to migrate legacy views to a layout without changing the child template immediately.

Path resolution rules

Given $layoutFile inside the child view:
1) If it ends without .php, Ishmael appends .php automatically.
2) If it is an absolute path, Ishmael uses it as-is:
   - Windows drive letter: C:\path\to\layout.php
   - Windows UNC: \\server\share\path\to\layout.php
   - Unix: /var/www/layout.php
3) Otherwise, it is treated as relative to the module Views/ directory. For example:
   - 'layout' becomes Modules/{Module}/Views/layout.php
   - '../layout' from Modules/{Module}/Views/posts becomes Modules/{Module}/Views/layout.php

This resolution is cross-platform and avoids double-prefixing absolute paths.

Security and best practices
- Keep layouts inside your project; avoid pointing to user-controlled paths.
- Prefer relative paths within the module when possible (e.g., 'layout').
- Escape user content with htmlspecialchars when echoing.
- Consider extracting partials like _flash.php and _form.php for reuse.

FAQ

Do layouts require ViewSections?
— Layouts do not strictly require sections, but they are expected and supported. If a layout yields sections (e.g., content), define them in the child. If you omit the content section, Ishmael falls back to the full child output.

Can I omit $layoutFile completely?
— Yes; if you don’t set $layoutFile, the child is rendered directly with no layout.

How do I generate URLs inside a view?
— Use the provided route() helper: <?= "<?php echo " ?>$route('posts.show', ['id' => 10]); ?>.

Related
- Tutorial: Controllers & Views
- How‑to: Generate URLs in views/controllers
- Reference: ViewSections API

---

Comprehensive examples (end‑to‑end)

This section provides full, copy‑pasteable examples showing controllers, views, layouts, and partials working together.

Example: Posts index with layout and partial

1) Controller (Modules/Blog/Controllers/PostsController.php)

```php
<?php
namespace Modules\Blog\Controllers;

use Ishmael\Core\Controller;
use Ishmael\Core\Http\Response;

final class PostsController extends Controller
{
    public function index(): Response
    {
        // App‑level data visible to all templates via $data
        $this->data['appName'] = 'My Blog';

        // Per‑view variables
        $posts = [
            ['id' => 1, 'title' => 'Hello World'],
            ['id' => 2, 'title' => 'Second Post'],
        ];

        // Capture view output and return a Response (recommended in pipeline-based apps)
        ob_start();
        $this->render('posts/index', [
            'posts' => $posts,
        ]);
        $html = (string) ob_get_clean();

        $res = new Response();
        $res->setBody($html);
        return $res;
    }
}
```

Why capture output and return Response?
- Ishmael's middleware pipeline and HTTP handling are Response-centric. Returning a Response keeps side effects explicit and plays nicely with middleware (e.g., security headers, compression).
- The base Controller::render() echoes by default, so we buffer it with ob_start() to obtain the HTML string and place it into the Response body.

Legacy (direct output) variant

If you are not using the middleware pipeline yet, you can render directly without returning a Response:

```php
public function index(): void
{
    $this->data['appName'] = 'My Blog';
    $posts = [/* ... */];
    $this->render('posts/index', ['posts' => $posts]);
}
```

2) Layout (Modules/Blog/Views/layout.php)

```php
<?php /** @var Ishmael\Core\ViewSections $sections */ ?>
<?php /** @var array<string,mixed> $data */ ?>
<!doctype html>
<html lang="en">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo htmlspecialchars($sections->yield('title', $data['appName'] ?? 'Ishmael App'), ENT_QUOTES, 'UTF-8'); ?></title>
  </head>
  <body>
    <header>
      <strong><?php echo htmlspecialchars($data['appName'] ?? 'Ishmael App', ENT_QUOTES, 'UTF-8'); ?></strong>
      <?php if (file_exists(__DIR__ . DIRECTORY_SEPARATOR . '_flash.php')) include __DIR__ . DIRECTORY_SEPARATOR . '_flash.php'; ?>
    </header>
    <main>
      <?php echo $sections->yield('content'); ?>
    </main>
    <footer>© <?php echo date('Y'); ?></footer>
  </body>
</html>
```

3) Partial (Modules/Blog/Views/_flash.php)

```php
<?php if (!empty($_SESSION['flash'])): ?>
  <div class="flash"><?php echo htmlspecialchars((string)$_SESSION['flash'], ENT_QUOTES, 'UTF-8'); ?></div>
  <?php unset($_SESSION['flash']); ?>
<?php endif; ?>
```

4) Child view (Modules/Blog/Views/posts/index.php)

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

Absolute layout path example

```php
<?php $layoutFile = __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'layout.php'; ?>
```

Troubleshooting quick reference
- Layout not found: Ensure `$layoutFile` is relative to `Views/` or a valid absolute path; do not prefix the module path yourself. Use `'layout'` or `'../layout'`, not `__DIR__ . '/../../Views/layout.php'`.
- Sections undefined: If your layout yields `content`, make sure the child view defines it using `$sections`, or rely on the auto‑content fallback by not using `$sections` in the child.
- Data not visible in layout: Put shared values in `$this->data` on the controller. They appear as `$data` in layouts and partials.
