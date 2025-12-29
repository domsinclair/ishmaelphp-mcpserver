# Glossary

## Environment‑Aware Modules

Environment‑Aware Modules are Ishmael modules that declare their intended runtime environment via a manifest file. The manifest indicates whether a module is suitable for development, shared (usable in both development and production), or production.

Key points:
- Manifest formats: `module.php` (preferred) or `module.json` (fallback).
- Precedence: if both files exist, `module.php` is used.
- `env` values: `development`, `shared`, `production`.
- Tooling and runtime may filter modules based on the current environment.

Example `module.php` manifest:
```php
<?php
/**
 * Example development‑only module manifest.
 * @return array<string, mixed>
 */
return [
    'name' => 'FakerSeeder',
    'version' => '1.0.0',
    'env' => 'development',
    'routes' => __DIR__ . '/routes.php',
    'export' => ['src', 'assets'],
];
```

CLI naming:
- The CLI binary is named `ishmael`.
  - Example: `php IshmaelPHP-Core/bin/ishmael pack --env=production`

Style and documentation rules:
- Use PascalCase for classes and camelCase for methods, properties, and variables.
- Avoid snake_case in new examples and code.
- Include PHPDoc in examples where functions or closures are shown.
