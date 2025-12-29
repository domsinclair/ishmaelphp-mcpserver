# Blog Tutorial — Part 1: Bootstrap the Module

In this tutorial series you will build a fully working Blog module from scratch, using Ishmael PHP’s module structure, controllers, views, routing, and tests.

In Part 1 you will:
- Scaffold a Blog module with the CLI.
- Add a Post resource skeleton (controller, routes, and views).
- Run the development server and verify the module is discovered.

Prerequisites:
- You have run composer install and can run the ishmael CLI: `php IshmaelPHP-Core/bin/ishmael`
- Your app root is the repository root or the SkeletonApp folder, depending on your setup.

## 1) Create the Blog module

Run the make:module generator to scaffold a new module named Blog.

```bash
php IshmaelPHP-Core/bin/ishmael make:module Blog
```

This creates a standard structure under `SkeletonApp/Modules/Blog` (or your app’s Modules directory):

- Controllers/
- Models/
- Views/
- routes.php
- module.json

Example module.json created by the generator:

```json
{
  "name": "Blog",
  "description": "Tutorial Blog module",
  "version": "0.1.0",
  "enabled": true
}
```

## 2) Add the Post resource

Now scaffold a Post resource inside the Blog module. This will create a PostController with CRUD actions, routes, and view stubs.

```bash
php IshmaelPHP-Core/bin/ishmael make:resource Blog Post
```

The generator adds:
- Controllers/PostController.php
- Views/posts/index.php, show.php, create.php, edit.php, _form.php
- routes.php entries for posts

Example controller header (you’ll flesh this out in later parts):

```php
<?php

declare(strict_types=1);

namespace Modules\Blog\Controllers;

use Ishmael\Core\Http\Request;
use Ishmael\Core\Http\Response;

/**
 * Class PostController
 *
 * Handles CRUD for blog posts.
 */
final class PostController
{
    /**
     * List posts.
     */
    public function index(Request $request, Response $response): Response
    {
        // TODO: Implement using PostService in Part 3
        return $response->withBody("Posts index placeholder");
    }
}
```

Note: In this series, all PHP code uses StudlyCase/PascalCase for classes and camelCase for methods/variables. Avoid snake_case names.

## 3) Register routes (check)

The resource generator appends lines like the following to `Modules/Blog/routes.php`:

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

If your app requires a manual module registration step, ensure the Blog module is enabled according to your module loader setup.

## 4) Verify in the browser

Start the dev server and navigate to `/blog/posts`.

```bash
php -S localhost:8000 -t SkeletonApp/public
```

You should see the placeholder response from PostController::index.

## What you learned
- How to scaffold a module and a resource with the Ishmael CLI.
- The basic files created for a resource (controller, routes, views).
- Where Blog module files live in the repository.

## Related reading
- How‑to: [Create a Module](../how-to/create-a-module.md)
- Guide: [Routing](./routing.md) and [Routing v2: Parameters, Constraints, and Named Routes](./routing-v2-parameters-constraints-and-named-routes.md)
- Reference:  [Routes](../reference/routes/_index.md)
- API Placeholder: [Core API](../reference/core-api/_index.md)
