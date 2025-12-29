# Routing v2: Parameters, Constraints, and Named Routes

Ishmael's Router now supports parameterized routes with built-in and custom constraints, strong diagnostics, named route URL generation, and improved 404/405 behavior.

## Defining routes with parameters

Use placeholder segments like `{id}` or typed placeholders like `{id:int}`. Built-in types: `int`, `numeric`, `bool`, `slug`, `uuid`. You can add custom types via `ConstraintRegistry::add()`.

```php
use Ishmael\Core\Router;
use Ishmael\Core\ConstraintRegistry;

Router::group(['module' => 'Posts'], function (Router $r) {
    $r->get('/posts/{id:int}', 'PostsController@show')->name('posts.show');
    $r->get('/posts/{slug:slug}', 'PostsController@bySlug');
});
```

In your controller action, parameters are available as typed arguments (Request/Response are auto-injected):

```php
use Ishmael\Core\Http\Request;
use Ishmael\Core\Http\Response;

final class PostsController
{
    public function show(Request $req, Response $res, int $id): Response
    {
        // $id is an int due to the :int constraint
        // ...
        return $res->setBody("Post {$id}");
    }
}
```

## Custom constraints

```php
ConstraintRegistry::add('hex', '[A-Fa-f0-9]+', static fn (string $v): string => strtolower($v));
Router::get('/debug/{token:hex}', 'DebugController@token');
```

The constraint regex is precomputed for matching, and the converter is applied to the raw segment value to produce the typed argument.

## Named routes and URL generation

Assign names to routes with `->name('posts.show')` and generate URLs anywhere:

```php
$url = Router::url('posts.show', ['id' => 42]); // "/posts/42"
```

- Missing params produce clear errors: `Missing parameters [id] for route 'posts.show' (source: fluent).`
- Legacy array routes with names are supported for simple static patterns.

## Collision detection on compile

Defining two routes that compile to the same regex for the same HTTP method now throws a `LogicException` during registration. This catches conflicts between static and parameterized routes early.

Example conflicting routes:

```php
Router::get('/users/{id}', 'UsersController@show');
Router::get('/users/new', 'UsersController@new'); // if {id} would match "new", this collides
```

If the compiled regex is identical, you will see an error similar to:

```
Route collision detected for method(s) GET: '/users/new' conflicts with existing pattern 'users/{id}' (modules: new=App, existing=App)
```

## Improved 404 and 405 handling

- If a path matches existing routes but with different HTTP methods, the router returns 405 Method Not Allowed and emits an `Allow` header listing allowed methods.
- If no routes match the path, a 404 Not Found is returned (or the convention fallback applies if enabled).

## Middleware pipeline

- Global, group, then per-route middleware execute in order. Returning a `Response` from any middleware short-circuits the pipeline.
- Use class-string or static callable middleware for route caching compatibility. See Middleware guide.

## Route cache

Use `ish route:cache` to compile routes. In production, the Kernel loads the cached routes for faster cold boots. `ish route:clear` clears the cache and restores dynamic discovery. See the Route Caching guide for details and benchmarks.


---

## Related reference
- Reference: [Routes](../reference/routes/_index.md)
- Reference: [CLI Route Commands](../reference/cli-route-commands.md)
