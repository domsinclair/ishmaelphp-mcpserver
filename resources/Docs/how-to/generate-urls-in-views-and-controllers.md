# Generate URLs in Views and Controllers

Use the Router's URL generator to build links by route name. This decouples templates from hard-coded paths and validates parameters.

## Assign names to routes

```php
use Ishmael\Core\Router;

Router::get('/posts/{id:int}', 'PostsController@show')->name('posts.show');
Router::get('/posts', 'PostsController@index')->name('posts.index');
```

## Generate URLs in controllers

```php
use Ishmael\Core\Router;
use Ishmael\Core\Http\Response;

final class PostsController
{
    public function redirectToShow(int $id): Response
    {
        $url = Router::url('posts.show', ['id' => $id]);
        return new Response('', 302, ['Location' => $url]);
    }
}
```

## Generate URLs in views

In plain PHP views, call `Router::url()` directly:

```php
<?php use Ishmael\Core\Router; ?>
<a href="<?= htmlspecialchars(Router::url('posts.index')) ?>">All posts</a>
```

## Error messages for missing params

If a required parameter is omitted, the generator throws a clear exception, e.g.:

```
Missing parameters [id] for route 'posts.show' (source: fluent).
```

## Absolute URLs

```php
$url = Router::url('posts.show', ['id' => 42], [], true); // includes scheme and host
```


---

## Related reference
- Reference: [Routes](../reference/routes/_index.md)
- Reference: [CLI Route Commands](../reference/cli-route-commands.md)
- Reference: [Core API (Markdown stubs)](../reference/core-api/_index.md)
