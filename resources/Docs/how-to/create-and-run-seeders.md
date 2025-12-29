# Create and Run Seeders

Date: 2025-11-04 (expanded)

Seeders populate your database with deterministic dev/test data. Ishmael keeps seeding module-first and provides an explicit, minimal contract with environment guards and deterministic execution. This guide explains the intent (theory), the contract, discovery/ordering rules, and provides compliant examples you can copy.

Key points (at a glance)
- Filesystem layout: `Modules/<Module>/Database/Seeders/`
- Contract: `run(DatabaseAdapterInterface $adapter, LoggerInterface $logger): void`; optional `dependsOn(): string[]`
- Ordering: topological sort based on declared dependencies
- Orchestration: optional `DatabaseSeeder` entrypoint per module
- Safety: environment guard (dev/test/local by default) and idempotent logic
- Logging: start, plan, each seeder execution, and summary via PSR‑3

Why seeders exist (the theory)
- Deterministic fixtures: Local dev and CI need predictable, repeatable data that mirrors schema expectations (unique keys, FKs) without hand-editing.
- Module boundaries: Seed data should live near the schema and services it supports, not as a global dump. That’s why Ishmael is module-first.
- Declarative orchestration: Dependencies model real “data prerequisites” (e.g., authors before posts). The runner turns this into a DAG and executes in a stable order.
- Re-runnability: No global “seeded flags.” Seeders must be written so they can be run repeatedly without side effects (no duplication, no errors). This makes development flows fast and safe.

Seeder contract
- Implement `SeederInterface` or extend `BaseSeeder` (recommended). `BaseSeeder` provides a default `dependsOn(): array` and shared ergonomics.
- Required method:
  - `public function run(DatabaseAdapterInterface $adapter, LoggerInterface $logger): void`
- Optional method:
  - `public function dependsOn(): array` returning an array of class names (FQCNs preferred) that must run before this one.

Discovery and execution rules
- Where runner looks: `Modules/<Module>/Database/Seeders/` of each module discovered by your app.
- With DatabaseSeeder present: Runner executes that `DatabaseSeeder` class plus all of its transitive dependencies (from `dependsOn()`), in topological order.
- Without DatabaseSeeder: Runner executes all seeder classes found in the folder, ordered deterministically by dependency resolution.
- Cycles are errors: If A depends on B and B depends on A (directly or indirectly), the runner aborts with a diagnostic.

Environment guard (safety)
- Allowed envs by default: `dev`, `development`, `test`, `testing`, `local`.
- Outside those envs, runner throws an error unless you explicitly override.
- CLI usually exposes `--force` and `--env=` flags. Use sparingly and only when you understand the impact (e.g., staging refresh).

Idempotency strategies (write once, run many times)
- Check-then-insert: Query by a unique key (email, slug) and insert only if missing.
- Upsert/merge: Use adapter-supported upsert for atomic write-once semantics where available.
- Stable anchors: Prefer unique constraints on natural keys (e.g., author email) to ensure re-runs don’t create duplicates.
- Foreign keys first: Ensure parent rows exist before inserting children; this is often naturally expressed via dependsOn() or by checks within a single seeder.

Example: a minimal seeder

```php
<?php
use Ishmael\Core\Database\Seeders\BaseSeeder;
use Ishmael\Core\DatabaseAdapters\DatabaseAdapterInterface;
use Psr\Log\LoggerInterface;

final class ExampleSeeder extends BaseSeeder
{
    /** @return string[] */
    public function dependsOn(): array
    {
        // return [OtherSeeder::class];
        return [];
    }

    public function run(DatabaseAdapterInterface $adapter, LoggerInterface $logger): void
    {
        // Deterministic logic: check then insert
        $row = $adapter->query('SELECT id FROM widgets WHERE slug = :s', [':s' => 'example'])->first();
        if (!$row) {
            $adapter->execute(
                'INSERT INTO widgets (slug, name) VALUES (:s,:n)',
                [':s' => 'example', ':n' => 'Example']
            );
        }
        $logger->info('ExampleSeeder completed.');
    }
}
```

Module entrypoint seeder (orchestration)
- Purpose: Coordinate module seeding. Typically empty `run()` and a populated `dependsOn()` listing concrete seeders.
- Behavior: If present, the runner starts from `DatabaseSeeder` and executes its dependency graph.

```php
<?php
use Ishmael\Core\Database\Seeders\BaseSeeder;
use Ishmael\Core\DatabaseAdapters\DatabaseAdapterInterface;
use Psr\Log\LoggerInterface;

final class DatabaseSeeder extends BaseSeeder
{
    /** @return string[] */
    public function dependsOn(): array
    {
        return [
            ExampleSeeder::class,
            // Add more seeders here, e.g., AuthorsAndPostsSeeder::class
        ];
    }

    public function run(DatabaseAdapterInterface $adapter, LoggerInterface $logger): void
    {
        // Optional: lightweight coordination or summary logging
        $logger->info('DatabaseSeeder completed (module entrypoint).');
    }
}
```

Programmatic API

```php
use Ishmael\Core\Database\Seeders\SeederRunner;
use Ishmael\Core\DatabaseAdapters\DatabaseAdapterInterface;

$adapter = /* resolve your DatabaseAdapterInterface and connect */;
$runner = new SeederRunner($adapter, app('logger'));

// Run all modules (dev/test only by default)
$runner->seed();

// Run for a single module
$runner->seed(module: 'HelloWorld');

// Run a specific seeder (and its dependencies)
$runner->seed(module: 'HelloWorld', class: 'ExampleSeeder');

// Override environment guard (dangerous — know what you are doing)
$runner->seed(module: 'HelloWorld', force: true, env: 'production');
```

CLI usage (typical)
- Module-level: `php vendor/bin/ish seed --module=Blog`
- Specific class (short name): `php vendor/bin/ish seed --module=Blog --class=ExampleSeeder`
- Specific class (FQCN, if required by your app’s resolver):
  `php vendor/bin/ish seed --module=Blog --class=Modules\\Blog\\Database\\Seeders\\ExampleSeeder`

Top-level app orchestration (optional)
- You can create an application-level `DatabaseSeeder` that declares `dependsOn()` entries pointing at module `DatabaseSeeder` classes to coordinate all modules from one place.

Troubleshooting
- “Seeding is disabled”: You are outside allowed environments. Pass `--force --env=<env>` intentionally.
- “Cyclic dependency detected”: Break the cycle in your `dependsOn()` graph.
- Seeder not running: Ensure the class file is in `Modules/<Module>/Database/Seeders/`, class name matches file, autoloading is configured, and it’s either listed by `DatabaseSeeder` or discoverable when no entrypoint exists.
- Duplicate rows on re-run: Add a unique constraint and change logic to check-then-insert or use upsert.

Templates and stubs
- Module templates typically provide `DatabaseSeeder.php` and `ExampleSeeder.php` stubs to copy from.
- The CLI `make:seeder` uses the core stub at `Resources/stubs/Seeder/seeder.php.stub` to generate compliant classes.

Related reference
- Reference: [CLI Route Commands](../reference/cli-route-commands.md)
- Reference: [Config Keys](../reference/config-keys.md)
- Reference: [Core API (Markdown stubs)](../reference/core-api/_index.md)
