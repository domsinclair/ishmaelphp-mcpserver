# ish pack — Build environment-specific bundles

This command creates a deployable bundle by scanning modules, applying environment filters, and including exported assets.

Synopsis

```
ish pack --env=production [--include-dev] [--target=webhost|container] [--out=./dist] [--dry-run]
```

Options
- --env=ENV — Environment name (production|development|testing).
- --include-dev — Include development modules even when env=production.
- --target=TARGET — Deployment target (webhost|container). Reserved for future behavior adjustments.
- --out=PATH — Output directory (default: ./dist under the app root).
- --dry-run — Do not copy files; print the bundle plan.

Behavior
- Modules are discovered via ModuleManager with environment-aware filtering.
- In production, development modules are excluded unless --include-dev is set.
- The packer gathers files from each module's manifest `export` list. If omitted, defaults include Controllers, Models, Views, routes.php, schema.php (if present), and module manifests.
- Includes caches when present (storage/cache/routes.cache.php, storage/cache/modules.cache.json) and the config directory.
- Generates manifest.json with sha256 checksums of included files.

Examples

```
# Production bundle
ish pack --env=production --out=./dist

# Include development modules explicitly
ish pack --env=production --include-dev --out=./dist

# Dry-run preview
ish pack --env=production --dry-run
```

Notes
- Vendor inclusion policy is documented separately; by default this command does not copy vendor/.
- All new code follows camelCase/PascalCase conventions and includes PHPDoc.

Security and CI

- In production, development modules are excluded by default. To include them, either set ALLOW_DEV_MODULES=true in the environment or pass --include-dev explicitly.
- Recommended CI guardrail: run `ish modules:cache --env=production` and inspect `storage/cache/modules.cache.json`; fail the build if any module has `env: development` unless explicitly allowed.
- See the Security and Policies reference for ready-to-copy CI snippets and a production packaging checklist.

Troubleshooting

- Error: "Unable to locate Composer autoload.php. Run 'composer install'."
  - Cause: The CLI requires your application vendor/autoload.php. Run `composer install` in your app root before invoking `ish`.
- Dry-run does not list caches (routes/modules):
  - Ensure you generated caches in the documented locations:
    - `ish modules:cache --env=production` → `storage/cache/modules.cache.json`
    - `ish route:cache` → `storage/cache/routes.cache.php`
- Expected module files are missing from the plan:
  - Verify each module’s manifest `export` list. If omitted, the packer uses conservative defaults (Controllers, Models, Views, routes.php, schema.php, manifests). Add explicit entries for custom paths (e.g., `assets`, `config`, `Resources`).
- Permissions denied when writing to ./dist (Windows or CI):
  - Use `--out` to choose a writable directory (e.g., `--out=./build/dist`). Ensure the CI workspace user has write access.
- Vendor dependencies not included:
  - By default, `vendor/` is not copied. Install dependencies on the target or handle vendor packaging according to your deployment policy.

See also
- Module Types: modules/types.md
- Security and Policies: ../security-policies.md
- Quick Start: ../guide/quick-start-modules-and-packer.md
