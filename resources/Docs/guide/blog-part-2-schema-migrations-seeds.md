# Blog Tutorial — Part 2 (Revised): Authors + Posts with Migrations, Seeds, and Scaffolding

In this revised Part 2 you will:
- Define two related tables: authors and posts (posts.author_id → authors.id).
- Create and run the migrations.
- Seed sample authors and posts.
- Scaffold controllers, services, and views using Ishmael’s new make:* CLI commands.
- See how the same pattern applies to other modules (Sales, Docs, etc.).

Prerequisites:
- You completed Part 1 and have the Blog module scaffolded.
- You can run the Ishmael CLI from your app root:

  ```bash
  php vendor/bin/ish
  ```

Tip: If you’re working directly inside the core repo, you can also run `php IshmaelPHP-Core/bin/ish`, but for app users the standard is `php vendor/bin/ish`.

---

## 1) Create migrations for Authors and Posts

We’ll create two migrations inside the Blog module. Use either positional module or the `--module` flag (both are supported).

Generate Authors migration:

```bash
php vendor/bin/ish make:migration Blog CreateAuthors
```

Generate Posts migration:

```bash
php vendor/bin/ish make:migration Blog CreatePosts
```

At this point you can safely delete the Create Example Migration file.

Open each generated migration file under `Modules/Blog/Database/Migrations/…` and replace the class contents with the following code.

Authors table (CreateAuthors):

```php
<?php

declare(strict_types=1);

use Ishmael\Core\Database\Migrations\BaseMigration;
use Ishmael\Core\Database\Schema\Schema;
use Ishmael\Core\Database\Schema\Blueprint;

final class CreateAuthors extends BaseMigration
{
    public function up(): void
    {
        Schema::create('authors', function (Blueprint $table): void {
            $table->id();                      // INTEGER auto-increment PK
            $table->string('name', 191);
            $table->string('email', 191);
            $table->text('bio', true);         // nullable text
            $table->timestamps();              // created_at, updated_at

            // Indexes / constraints
            $table->unique('email', 'authors_email_unique');
            $table->index('name', 'idx_authors_name');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('authors');
    }
}
```

Posts table referencing Authors (CreatePosts):

```php
<?php

declare(strict_types=1);

use Ishmael\Core\Database\Migrations\BaseMigration;
use Ishmael\Core\Database\Schema\Schema;
use Ishmael\Core\Database\Schema\Blueprint;

final class CreatePosts extends BaseMigration
{
    public function up(): void
    {
        Schema::create('posts', function (Blueprint $table): void {
            $table->id();
            $table->string('title', 255);
            $table->string('slug', 191);
            $table->text('body');

            // FK: posts.author_id → authors.id (restrict delete by default)
            $table->foreignId(
                name: 'author_id',
                referencesTable: 'authors',
                nullable: false,
                type: 'INTEGER',
                referencesColumn: 'id',
                onDelete: 'restrict',
                onUpdate: 'cascade'
            );

            $table->timestamps();

            // Useful indexes
            $table->unique('slug', 'posts_slug_unique');   // unique slugs (global)
            $table->index('author_id', 'idx_posts_author_id');
            $table->index(['title', 'created_at'], 'idx_posts_title_created');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('posts');
    }
}
```

Notes:
- Create `authors` before `posts` so the foreign key can be created.
- If you prefer per‑author unique slugs, replace the global unique on `slug` with a composite unique on `['author_id', 'slug']`.
- The DSL helpers used here are supported today: `id`, `string`, `text`, `timestamps`, `index`, `unique`, and `foreignId`.

---

## 2) Run the migrations

From your app root:

```bash
php vendor/bin/ish migrate --module=Blog
```

Verify that `authors` and `posts` exist in your database. `posts.author_id` should be a foreign key to `authors.id`.

---

## 3) Seeding in Ishmael: concepts, contract, and an Authors → Posts example

This section explains how seeding is intended to work in Ishmael and then walks you through a compliant example for the Blog module.

How Ishmael seeding works (the model)
- Module-first: Each module owns its seeders under `Modules/<Module>/Database/Seeders/`.
- Simple contract: A seeder is a class that implements `run(DatabaseAdapterInterface $adapter, LoggerInterface $logger): void`. Extend `BaseSeeder` to get a default empty `dependsOn(): array` and helper behavior.
- Deterministic ordering: Seeders can declare dependencies via `dependsOn(): string[]`. The runner builds a dependency graph and executes seeders in a deterministic topological order.
- Entrypoint orchestration: If a `DatabaseSeeder` class exists inside the module folder, the runner resolves and executes that entrypoint and all of its transitive dependencies. If there is no `DatabaseSeeder`, the runner executes all seeders in the folder in a deterministic order.
- Environment guard: By default, seeding is only permitted in development/test/local environments. You must explicitly pass a force/override to seed in other environments.
- Idempotency by design: Seeders should be re-runnable. Use unique keys and “check then insert” or adapter-level upsert so running seeders multiple times does not create duplicates.
- Logging: Every run is logged (plan + each seeder + summary) via PSR-3 so you can diagnose ordering and data applied.

Why `DatabaseSeeder` and an “example” seeder exist
- `DatabaseSeeder` is the module’s coordinator. It typically does minimal work itself and lists the seeders to run using `dependsOn()`. This makes your intent explicit and keeps the module’s seeding flow predictable and discoverable.
- A sample concrete seeder (like `ExampleSeeder`) demonstrates the contract and idempotent pattern you should follow for your own data seeders.

What this means for the Blog tutorial
- We’ll create a concrete seeder `AuthorsAndPostsSeeder` that inserts two authors and two posts, but only if they don’t already exist.
- We’ll wire it into the module’s `DatabaseSeeder` so normal module-level seeding picks it up.

Generate the seeder class

```bash
php vendor/bin/ish make:seeder Blog AuthorsAndPostsSeeder
```

Edit `Modules/Blog/Database/Seeders/AuthorsAndPostsSeeder.php` and replace its contents with the following compliant implementation (extends `BaseSeeder`, accepts `$adapter` + `$logger`, uses deterministic checks):

```php
<?php

declare(strict_types=1);

use Ishmael\Core\Database\Seeders\BaseSeeder;
use Ishmael\Core\DatabaseAdapters\DatabaseAdapterInterface;
use Psr\Log\LoggerInterface;

final class AuthorsAndPostsSeeder extends BaseSeeder
{
    /**
     * If this seeder depends on other seeders, list them here.
     * For this example, none.
     * @return string[]
     */
    public function dependsOn(): array
    {
        return [];
    }

    /**
     * Insert sample authors and posts deterministically (safe to re-run).
     */
    public function run(DatabaseAdapterInterface $adapter, LoggerInterface $logger): void
    {
        // Ensure Ada exists
        $ada = $adapter->query('SELECT id FROM authors WHERE email = :e', [':e' => 'ada@example.test'])->first();
        if (!$ada) {
            $adapter->execute(
                'INSERT INTO authors (name, email, created_at, updated_at) VALUES (:n, :e, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)',
                [':n' => 'Ada Lovelace', ':e' => 'ada@example.test']
            );
            $adaId = (int) $adapter->lastInsertId();
        } else {
            $adaId = (int) $ada['id'];
        }

        // Ensure Grace exists
        $grace = $adapter->query('SELECT id FROM authors WHERE email = :e', [':e' => 'grace@example.test'])->first();
        if (!$grace) {
            $adapter->execute(
                'INSERT INTO authors (name, email, created_at, updated_at) VALUES (:n, :e, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)',
                [':n' => 'Grace Hopper', ':e' => 'grace@example.test']
            );
            $graceId = (int) $adapter->lastInsertId();
        } else {
            $graceId = (int) $grace['id'];
        }

        // Ensure post for Ada exists
        $post1 = $adapter->query('SELECT id FROM posts WHERE slug = :s', [':s' => 'hello-ishmael'])->first();
        if (!$post1) {
            $adapter->execute(
                'INSERT INTO posts (title, slug, body, author_id, created_at, updated_at) VALUES (:t,:s,:b,:a,CURRENT_TIMESTAMP,CURRENT_TIMESTAMP)',
                [
                    ':t' => 'Hello Ishmael',
                    ':s' => 'hello-ishmael',
                    ':b' => 'First post body',
                    ':a' => $adaId,
                ]
            );
        }

        // Ensure post for Grace exists
        $post2 = $adapter->query('SELECT id FROM posts WHERE slug = :s', [':s' => 'soft-deletes'])->first();
        if (!$post2) {
            $adapter->execute(
                'INSERT INTO posts (title, slug, body, author_id, created_at, updated_at) VALUES (:t,:s,:b,:a,CURRENT_TIMESTAMP,CURRENT_TIMESTAMP)',
                [
                    ':t' => 'Soft deletes later',
                    ':s' => 'soft-deletes',
                    ':b' => 'A second post',
                    ':a' => $graceId,
                ]
            );
        }

        $logger->info('AuthorsAndPostsSeeder completed.');
    }
}
```

Wire the seeder into the module’s DatabaseSeeder

Open `Modules/Blog/Database/Seeders/DatabaseSeeder.php` and add the new seeder to `dependsOn()` so it runs during standard module seeding:

```php
public function dependsOn(): array
{
    return [
        ExampleSeeder::class,            // existing sample seeder (optional)
        AuthorsAndPostsSeeder::class,    // add this line
    ];
}
```

Run the seeder(s)

- Module-level (executes DatabaseSeeder plus its dependency graph):

```bash
php vendor/bin/ish seed --module=Blog
```

- A specific seeder (and its dependencies). Depending on your runner configuration, you can pass the short class name or FQCN:

```bash
php vendor/bin/ish seed --module=Blog --class=AuthorsAndPostsSeeder
# or, if FQCN is required in your app setup:
php vendor/bin/ish seed --module=Blog --class=Modules\\Blog\\Database\\Seeders\\AuthorsAndPostsSeeder
```

Environment guard reminder

- Seeding runs only in dev/test/local by default. To run elsewhere, you must explicitly force it and provide an env override (dangerous; ensure you know what you’re doing):

```bash
php vendor/bin/ish seed --module=Blog --force --env=production
```

Verification

- Confirm rows exist in `authors` and `posts`, and that `posts.author_id` references a valid author ID. Re-run the same command to confirm idempotency (no duplicates should be created).

---

## 4) Scaffold services, controllers, and views (new commands)

We’ll scaffold minimal services and controllers for authors and posts, plus standard CRUD views for each resource.

Generate services:

```bash
php vendor/bin/ish make:service Blog Author
php vendor/bin/ish make:service Blog Post
```

Generate controllers:

```bash
php vendor/bin/ish make:controller Blog Authors
php vendor/bin/ish make:controller Blog Posts
```

Generate standard views (index, show, create, edit, _form):

```bash
php vendor/bin/ish make:views Blog authors
php vendor/bin/ish make:views Blog posts
```

This creates files such as:
- `Modules/Blog/Services/AuthorService.php`
- `Modules/Blog/Services/PostService.php`
- `Modules/Blog/Controllers/AuthorsController.php`
- `Modules/Blog/Controllers/PostsController.php`
- `Modules/Blog/Views/authors/*`
- `Modules/Blog/Views/posts/*`

### Add minimal service logic

Open `AuthorService.php` and `PostService.php` and paste the following implementations.

`Modules/Blog/Services/AuthorService.php`:

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

`Modules/Blog/Services/PostService.php`:

```php
<?php

declare(strict_types=1);

namespace Modules\Blog\Services;

use Ishmael\Core\Database;

final class PostService
{
    public function listWithAuthors(): array
    {
        $sql = 'SELECT p.*, a.name AS author_name, a.email AS author_email
                FROM posts p
                JOIN authors a ON a.id = p.author_id
                ORDER BY p.created_at DESC';
        return Database::query($sql)->fetchAll();
    }

    public function findBySlug(string $slug): ?array
    {
        $row = Database::query('SELECT * FROM posts WHERE slug = :slug', ['slug' => $slug])->fetch();
        return $row ?: null;
    }
}
```

### Minimal controllers (JSON for quick checks)

Open the generated controllers and paste the following code.

`Modules/Blog/Controllers/AuthorsController.php`:

```php
<?php

declare(strict_types=1);

namespace Modules\Blog\Controllers;

use Ishmael\Core\Controller;
use Modules\Blog\Services\AuthorService;

final class AuthorsController extends Controller
{
    public function __construct(private AuthorService $authors) {}

    public function index(): string
    {
        return $this->json($this->authors->all());
    }
}
```

`Modules/Blog/Controllers/PostsController.php`:

```php
<?php

declare(strict_types=1);

namespace Modules\Blog\Controllers;

use Ishmael\Core\Controller;
use Modules\Blog\Services\PostService;

final class PostsController extends Controller
{
    public function __construct(private PostService $posts) {}

    public function index(): string
    {
        return $this->json($this->posts->listWithAuthors());
    }
}
```

### Add routes (Phase 14 ready: session + CSRF)

If `Modules/Blog/routes.php` doesn’t exist, create it with the wrapper function below. If it exists, add these routes inside the function. Since Phase 14, browser routes should run with StartSessionMiddleware first and then VerifyCsrfToken to avoid CSRF/session errors.

```php
<?php

declare(strict_types=1);

use Ishmael\Core\Router;
use Ishmael\Core\Http\Middleware\StartSessionMiddleware;
use Ishmael\Core\Http\Middleware\VerifyCsrfToken;
use Modules\Blog\Controllers\AuthorsController;
use Modules\Blog\Controllers\PostsController;

return function (Router $router): void {
    // Web group: start session then enforce CSRF
    $router->group(['middleware' => [StartSessionMiddleware::class, VerifyCsrfToken::class]], function (Router $r): void {
        // Authors
        $r->get('/blog/authors', [AuthorsController::class, 'index'])->name('blog.authors.index');

        // Posts
        $r->get('/blog/posts', [PostsController::class, 'index'])->name('blog.posts.index');
    });
};
```

Note
- Wrapping routes in this web group ensures the CSRF helpers (csrfMeta/csrfToken) work during GET and that POST/PUT/DELETE are protected.
- For stateless APIs, use a separate group without session/CSRF and rely on token auth + CORS instead.

Navigate to:
- `/blog/authors` → JSON list of authors.
- `/blog/posts` → JSON list of posts with joined author fields.

Later, you can switch the controllers to render the generated views under `Modules/Blog/Views/...` instead of returning JSON.

---

## 5) Apply this pattern to other modules

The approach generalizes cleanly:
- Model relationships with foreign keys using `foreignId()` (or `foreignKey()` for more control). Create parent tables first, then child tables that reference them.
- Add `timestamps()` everywhere you write rows; it helps with sorting and audit.
- Put data access in services and HTTP concerns in controllers. It’s easy to add more controllers to the same module or reuse services from other modules.
- Scaffold incrementally with `make:service`, `make:controller`, and `make:views` to grow an existing module (e.g., Blog adds Authors; Sales adds Customers, Orders, and OrderItems).

Examples:
- Sales: `customers` (parent) → `orders` (child) → `order_items` (grandchild). Use `foreignId('customer_id', 'customers')` from `orders`, and `foreignId('order_id', 'orders')` from `order_items`.
- Docs: `authors` (shared) → `articles` (child), optionally `revisions` referencing `articles`.

---



---

## What you learned
- How to define a pair of related tables in a module using the Blueprint API.
- How to run migrations and seed linked data.
- How to scaffold controllers, services, and views using the new `make:*` commands.
- How to apply these patterns to richer modules with multiple interlinked models.
