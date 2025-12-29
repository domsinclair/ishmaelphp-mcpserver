# Phase 12 — Database Additions

This phase focuses on closing core gaps in the database layer: relationships (foreign keys), indexes, custom primary keys, soft deletes, audit fields, and developer‑friendly seeding/reset tools. The milestones are self‑contained and build upon each other.

## Milestones

### 1) Schema and migrations foundation (relationships, indexes, custom PKs)

Goals:

- Define foreign keys with onDelete/onUpdate policies.
- Define indexes (single, composite, unique) in a portable way.
- Allow custom primary key names and strategies (auto increment, UUID/ULID, manual).

Deliverables:

- Model metadata/attributes or config to declare keys, FKs, and indexes.
- Migration DSL and adapter‑specific DDL generation (SQLite, MySQL/MariaDB, Postgres).
- CLI support to run, roll back, and list migrations.

Acceptance criteria:

- Tables can be created with FKs, indexes, and custom PKs across supported adapters.
- Migrations are transactional where supported and are idempotent on re‑runs.

### 2) Soft deletes

Goals:

- Opt‑in soft delete support with a conventional deleted_at column.
- Global query scope that hides soft‑deleted rows by default.

Deliverables:

- Attribute/config toggle per model (e.g., SoftDeletes).
- Helpers: withTrashed, onlyTrashed, restore, forceDelete.

Acceptance criteria:

- Soft‑deleted records are excluded by default and can be retrieved/restored via helpers.

### 3) Audit fields

Goals:

- Automatic created_at/updated_at timestamps.
- Optional created_by/updated_by fields populated from request/job context.

Deliverables:

- Attribute/config (e.g., Auditable) with options for timestamps and user attribution.
- Hooks/middleware to provide the current user subject to the model layer.

Acceptance criteria:

- Timestamps are set/updated correctly; optional user fields are populated when available.

### 4) Seeding and reset tools (dev‑only)

Goals:

- Deterministic, realistic fake data for development/testing.
- Safe purge and reset of auto‑increment/sequence values across adapters.

Deliverables:

- Seeder classes and a registry with ordering.
- CLI: ish db:seed, ish db:reset, and purge/reset options.

Acceptance criteria:

- Seeders run idempotently; tables can be truncated in FK‑safe order; sequences/reset applied.

### Defer: Query builder

Rationale: Can be a separate phase once schema/model conventions and metadata are stable.

## Documentation and examples

This page demonstrates end‑to‑end usage: custom PKs, FKs, indexes, soft deletes, audit, and seeding. The goal is to show the flow from migration to realistic data for development.

See also the top‑level project doc: Docs/Phase-12-Database-Additions.md.

### End‑to‑end example: Posts and Comments

We will model a simple blog with the following:

- Custom primary keys
- A foreign key from comments to posts
- Useful indexes
- Soft deletion support (deleted_at)
- Audit timestamps (created_at/updated_at)
- Seeders and reset tools

#### 1) Migrations with custom PKs, FKs, and indexes

Use the Schema DSL in a migration. Example contents of a new migration (generated via `ish make:migration CreatePostsAndComments` and then edited):

```php
<?php
declare(strict_types=1);

use Ishmael\Core\Database\Migrations\BaseMigration;
use Ishmael\Core\Database\Schema\Schema;
use Ishmael\Core\Database\Schema\Blueprint;

final class CreatePostsAndComments extends BaseMigration
{
    public function up(): void
    {
        // Posts table with a custom primary key name
        Schema::create('posts', function (Blueprint $t): void {
            $t->bigIncrements('post_id'); // custom PK name, bigint auto-increment
            $t->string('title', 255)->index('idx_posts_title');
            $t->text('body');
            $t->boolean('is_published', false, false)->index('idx_posts_is_published');
            $t->timestamp('deleted_at', nullable: true); // soft delete indicator
            $t->timestamps(); // created_at, updated_at

            // Composite index example (title + created_at)
            $t->index(['title', 'created_at'], 'idx_posts_title_created');
        });

        // Comments table referencing posts
        Schema::create('comments', function (Blueprint $t): void {
            $t->bigIncrements('comment_id');
            $t->bigInteger('post_id');
            $t->string('author', 120);
            $t->text('content');
            $t->timestamp('deleted_at', nullable: true);
            $t->timestamps();

            // Foreign key with cascading delete
            $t->foreign(['post_id'])
              ->references('posts', ['post_id'])
              ->onDelete('cascade')
              ->onUpdate('cascade');

            $t->index(['post_id', 'created_at'], 'idx_comments_post_created');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('comments');
        Schema::dropIfExists('posts');
    }
}
```

Notes:

- The DSL maps to adapter‑specific DDL across SQLite, MySQL/MariaDB, and PostgreSQL.
- Some engines require FKs to be declared at creation time; do not rely on adding them after the fact unless your migration handles it explicitly.
- Index helpers accept either a name and columns or just columns (a name will be generated when omitted).

If you need to, you can mix raw SQL via `$this->sql('...')` inside a migration to handle edge cases.

#### 2) Soft deletes and audit fields

In this phase we use the conventional columns to support these behaviors:

- `deleted_at TIMESTAMP NULL` for soft deletion
- `created_at TIMESTAMP` and `updated_at TIMESTAMP` for audit timestamps

The Schema DSL convenience method `$t->timestamps()` adds `created_at` and `updated_at`. Add `deleted_at` explicitly. Your repository/model layer should:

- Treat rows with `deleted_at IS NOT NULL` as deleted by default (global scope)
- Provide optional helpers such as withTrashed/onlyTrashed/restore/forceDelete as your app grows

Tip: When updating rows, ensure `updated_at` is touched accordingly; on creation set both `created_at` and `updated_at`.

Optional user attribution fields can be added as `created_by` and `updated_by` (e.g., INT or UUID depending on your auth model). Populate them in your application service layer based on the current user context.

#### 3) Seeding realistic data

Create a seeder per module in `Modules/<Module>/Database/Seeders`. A simple example:

```php
<?php
declare(strict_types=1);

namespace Modules\Blog\Database\Seeders;

use Ishmael\Core\Database;

final class BlogSeeder
{
    public function run(): void
    {
        $pdo = Database::adapter();

        // Insert some posts
        for ($i = 1; $i <= 5; $i++) {
            $title = "Post #{$i}";
            $pdo->execute('INSERT INTO posts (title, body, is_published, created_at, updated_at) VALUES (:t, :b, :p, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)', [
                ':t' => $title,
                ':b' => "Body for {$title}",
                ':p' => ($i % 2 === 0) ? 1 : 0,
            ]);
            $postId = (int)$pdo->lastInsertId();

            // A couple comments per post
            for ($j = 1; $j <= 2; $j++) {
                $pdo->execute('INSERT INTO comments (post_id, author, content, created_at, updated_at) VALUES (:pid, :a, :c, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)', [
                    ':pid' => $postId,
                    ':a' => 'Author ' . $j,
                    ':c' => "Comment {$j} on {$title}",
                ]);
            }
        }
    }
}
```

Run seeders during development:

- CLI: `ish db:seed --module=Blog` (alias of `ish seed`)
- Programmatically: use `Ishmael\Core\Database\Seeding\SeedManager` to invoke seeders from tests or scripts

Seeders are expected to be idempotent; consider using UPSERTs or guard checks if you re‑run them.

#### 4) Reset and purge for fast iteration

To quickly reset your database during local development/testing:

- Reset sequences/identities only: `ish db:reset`
- Purge all tables and reset identities (FK‑safe): `ish db:reset --purge`

Safety: By default these commands are enabled only in dev/test/local environments. Use `--force` to override when necessary (for CI).

Portability hints:

- SQLite: FK checks are handled automatically; sequences are cleared via `sqlite_sequence` when needed.
- MySQL/MariaDB: TRUNCATE resets AUTO_INCREMENT; FK checks are disabled temporarily.
- PostgreSQL: `TRUNCATE ... RESTART IDENTITY CASCADE` efficiently resets and purges.

## Testing

- Add PHPUnit tests to assert the new documentation files exist and contain the expected headings.
- Run existing database adapter tests; amend only if the new defaults affect them (no changes expected for this phase).

## Notes

- All new PHP code introduced in this phase must include PHPDoc comments and use camelCase/PascalCase names (no snake_case).
