# Testing Feature Packs

This document explains the kinds of tests a feature pack author should write to ensure a new feature works as intended and remains reliable over time. Examples assume PHPUnit, but Pest or other runners are fine.

## Goals of testing
- Validate the pack’s public API (services, helpers, facades, CLI commands) works as documented.
- Confirm framework wiring: routes, controllers, middleware, and views render correctly.
- Ensure configuration loads/merges correctly and has safe defaults.
- Verify data layer: migrations run up/down cleanly, seeds are idempotent, repositories behave.
- Check failure modes: clear error messages; safe behavior for invalid input or missing dependencies.
- Keep feedback fast: most logic covered by unit/contract tests; focused integration tests for wiring.

## Test types to include

1) Unit tests (fast)
- Pure functions, small classes, value objects.
- Business rules and validation logic.
- Adapters with mocked dependencies.

2) Contract tests (pack-level API)
- Service container bindings resolve expected interfaces/implementations.
- CLI commands exist, show help, accept expected options, and return meaningful exit codes.
- Configuration schema and defaults are present and overridable.
- Events/listeners are registered as expected.

3) Integration tests (with IshmaelPHP runtime)
- Route → Controller → Response lifecycle assertions.
- View rendering with pack-provided templates/layouts.
- Database migrations up/down against a test DB (SQLite in-memory recommended).
- CLI commands executed through the core CLI entry point.

4) Smoke/end-to-end (optional but useful)
- Minimal IshmaelPHP app with only your pack enabled boots without errors.
- One or two happy-path requests exercise the main flow successfully.

## Suggested project layout
- tests/Unit/...
- tests/Contract/...
- tests/Integration/...
- phpunit.xml(.dist) in the repository root.

Composer (excerpt):
```json
{
  "autoload": { "psr-4": { "Vendor\\MyPack\\": "src/" } },
  "autoload-dev": { "psr-4": { "Vendor\\MyPack\\Tests\\": "tests/" } },
  "scripts": { "test": "phpunit" }
}
```

## Using IshmaelPHP in tests
- Bootstrapping: for integration tests, spin up a minimal app context. The SkeletonApp in this repo is a good starting point.
- Module discovery: make sure your module manifest (module.php/json) can be found by the loader (e.g., place the pack under Modules/ in tests or enable vendor discovery).
- Config: load your pack’s config and assert merged values via the config helper.
- HTTP: issue requests and assert status codes, headers, and bodies.
- Database: use SQLite in-memory if possible; run migrations in setUp and roll back in tearDown.
- CLI: execute commands through the core CLI and assert exit codes/output.

## What to cover
- Services/bindings: container resolves primary services; defaults can be overridden.
- Routes/controllers: correct paths/methods; middleware applied; validation and responses.
- Views/templates: required variables present; templates render without exceptions.
- Migrations/seeds: schema created as expected; down reverses; seeds idempotent.
- CLI: discoverable, help text present, success returns 0; errors return non-zero with messages.
- Security/error handling: inputs validated; safe defaults; meaningful exceptions.

## Fast feedback tips
- Favor unit and contract tests for most logic; keep integration tests focused.
- Use small fixtures/factories; mock external services; avoid sleeping/waiting.
- Parallelize if supported; cache Composer dependencies in CI.

## Pre-publish checklist
- Tests pass locally on all supported PHP versions you claim.
- CI runs unit + contract on each push; full integration on main/releases.
- Coverage includes the public API and critical paths.
- README explains how to run the suite.
