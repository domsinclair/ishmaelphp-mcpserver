# Using the src/ Folder in Feature Packs

This page explains what the src/ folder in a Feature Pack is for, what belongs there, and how it should be organized and autoloaded.

## Purpose of src/
The src/ directory contains the pack’s PHP source code — the classes that implement your functionality and the public API exposed to host applications. It is autoloaded via Composer (PSR‑4) and should not contain runtime configuration, views, migrations, or other resources.

Important: IshmaelPHP is not Laravel. Feature packs should not assume Laravel components such as Eloquent ORM, facades, or service providers from Laravel. Use plain PHP, PSR interfaces, and the IshmaelPHP facilities (routing, module discovery, CLI, helpers, and the container) to wire your code.

Put in src/:
- Domain and application logic (services, handlers, value objects, repositories, use cases)
- HTTP controllers, middleware, request/response DTOs
- Console command classes
- Service providers/bootstrappers for the container
- Contracts (interfaces) you expect host apps or other packs to implement
- Support utilities that are part of your public API

Do NOT put in src/:
- Config files (config/*.php) — these live under config/ in the pack
- Routes files (routes/*.php)
- Database migrations/seeds (database/migrations, database/seeds)
- Views/templates/assets (resources/views, public/, assets/)
- Tests (tests/)

Keeping boundaries clear helps discovery and packaging tools wire your pack correctly and reduces accidental BC breaks.

## How IshmaelPHP uses src/ at runtime
- Module discovery reads your pack’s manifest (module.php or module.json) and registers the module with the core loader.
- Routes declared in routes.php (inside your module) should point to controllers under src/Http/Controllers.
- CLI integration: expose command classes under src/Console and register them via a small provider in src/Providers or via a services hint in module.php so they appear in the core ish CLI.
- Container/services: register bindings in a provider under src/Providers (e.g., binding Contracts to implementations) and/or through your module manifest conventions.
- Config integration: read configuration via IshmaelPHP’s config helper; don’t hardcode environment-specific values inside src/.

## Autoloading (Composer)
Configure PSR‑4 autoloading for src/ so classes are discoverable:

```json
{
  "name": "vendor/my-pack",
  "autoload": { "psr-4": { "Vendor\\MyPack\\": "src/" } }
}
```

Your namespaces under src/ should start with Vendor\MyPack\ and mirror directory structure.

## Suggested structure inside src/
You don’t have to use all of these; pick what fits your pack. Keep it consistent.

- src/Contracts/ — Interfaces that define your public contracts
- src/Domain/ — Core business rules, entities, value objects
- src/Application/ — Use cases/services orchestrating domain logic
- src/Infrastructure/ — Framework/IO adapters (DB, HTTP clients, mailers)
- src/Http/
  - Controllers/
  - Middleware/
  - Requests/ (DTOs/validators)
- src/Console/ — Command classes
- src/Providers/ — Container/service providers and boot logic
- src/Support/ — Helpers, utilities intended for public use

Example:
```
src/
  Contracts/
    PostRepository.php
  Domain/
    Post.php
  Application/
    CreatePost.php
  Infrastructure/
    Persistence/
      PdoPostRepository.php
  Http/
    Controllers/
      PostController.php
    Requests/
      StorePostRequest.php
  Console/
    SyncPostsCommand.php
  Providers/
    PostServiceProvider.php
```

## Public API and BC guidelines
- Everything under your root namespace is technically importable, but only classes you document as part of the pack’s API should be considered stable. Mark internals with @internal in PHPDoc or place them under an Internal/ namespace.
- Keep Contracts/ stable; bump major versions when changing them.
- Prefer constructor injection and interfaces to make testing and extension easy.

## Interaction with other pack folders
- config/: default configuration published/merged into the host app. src/ classes should read config via helpers, not hardcoded values.
- routes/: define endpoints that point to src/Http/Controllers.
- database/: migrations and seeds used by your repositories/services.
- resources/views: templates rendered by your controllers.

## Service providers
Register your services in a provider class under src/Providers. Typical responsibilities:
- Bind interfaces to implementations (e.g., Contracts\Foo → Infrastructure\FooImpl)
- Merge your config defaults
- Register routes, console commands, event listeners with the IshmaelPHP runtime

Tip: If your pack prefers zero-code bootstrapping, you can also declare certain bindings or command registrations via keys in module.php (for example, a services section) and keep your provider minimal.

## Testing note
Place tests under tests/, not src/. Use autoload-dev in Composer to map your test namespace.
