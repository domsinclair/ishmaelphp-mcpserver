# CLI Commands (generated)

This page is generated from Ishmael's CLI registry.


## ui:tailwind

Install Tailwind CSS (v4+) and scaffold CSS input/output for Ishmael.

**Options**

| Option | Description | Accepts |
|---|---|---|
| `--css` | Input CSS path (default: resources\css\app.css) | PATH |
| `--out` | Output CSS path (default: public\assets\app.css) | PATH |
| `--force` | Overwrite existing files if present |  |
| `--dry-run` | Print actions without making changes |  |
| `--no-build` | Skip initial build step |  |


## help

Show CLI usage and available commands.

**Options**

| Option | Description | Accepts |
|---|---|---|
| `--config` | Path to database config file | PATH |
| `--templates` | Override templates directory | PATH |
| `--app-root` | Override application root | PATH |


## make:module

Scaffold a new module skeleton (controllers, models, views, routes.php, module.json).

**Options**

| Option | Description | Accepts |
|---|---|---|
| `--templates` | Override template source directory | PATH |


## make:resource

Scaffold a CRUD resource inside a module (controller, routes, views).

**Options**

| Option | Description | Accepts |
|---|---|---|
| `--templates` | Override template source directory | PATH |


## make:controller

Create a controller class inside a module.

**Options**

| Option | Description | Accepts |
|---|---|---|
| `--templates` | Override template source directory | PATH |
| `--invokable` | Generate an invokable controller (__invoke) |  |
| `--api` | Hint API-style responses in the stub (if available) |  |


## make:service

Create a service class inside a module.

**Options**

| Option | Description | Accepts |
|---|---|---|
| `--templates` | Override template source directory | PATH |


## make:view

Create a single view file inside a module from the view stub.

**Options**

| Option | Description | Accepts |
|---|---|---|
| `--templates` | Override template source directory | PATH |


## make:views

Create a standard set of CRUD views inside a module (index, show, create, edit, _form).

**Options**

| Option | Description | Accepts |
|---|---|---|
| `--templates` | Override template source directory | PATH |


## make:migration

Create a timestamped migration file (supports --module=Name or positional module).

**Options**

| Option | Description | Accepts |
|---|---|---|
| `--module` | Target module name for the migration | Name |


## make:seeder

Create a seeder class inside a module.


## migrate

Run outstanding migrations.

**Options**

| Option | Description | Accepts |
|---|---|---|
| `--module` | Limit to a specific module | Name |
| `--steps` | Limit number of steps | N |
| `--pretend` | Dry-run without executing |  |


## migrate:rollback

Rollback the last or N batches.

**Options**

| Option | Description | Accepts |
|---|---|---|
| `--module` | Limit to a specific module | Name |
| `--steps` | Number of steps to rollback | N |


## status

Show migration status.

**Options**

| Option | Description | Accepts |
|---|---|---|
| `--module` | Limit to a specific module | Name |


## seed

Run database seeders.

**Options**

| Option | Description | Accepts |
|---|---|---|
| `--module` | Limit to a specific module | Name |
| `--class` | Seeder FQCN to run | FQCN |
| `--force` | Bypass environment guard |  |
| `--env` | Environment name | ENV |


## modules:cache

Discover and cache module metadata for faster boot.

**Options**

| Option | Description | Accepts |
|---|---|---|
| `--env` | Environment (production|development|testing) | ENV |
| `--allow-dev` | Include dev-only modules |  |
| `--modules` | Modules directory path | PATH |
| `--cache` | Cache file path | PATH |


## modules:clear

Clear module metadata cache.

**Options**

| Option | Description | Accepts |
|---|---|---|
| `--cache` | Cache file path | PATH |


## route:cache

Compile and cache routes for faster dispatch.

**Options**

| Option | Description | Accepts |
|---|---|---|
| `--out` | Output file path | PATH |
| `--modules` | Modules directory path | PATH |
| `--env` | Environment name | ENV |


## route:clear

Clear route cache file.

**Options**

| Option | Description | Accepts |
|---|---|---|
| `--file` | Route cache file path | PATH |


## examples:list

List example modules available in the core repository.


## examples:install

Install example module(s) into your app.

**Options**

| Option | Description | Accepts |
|---|---|---|
| `--all` | Install all examples |  |
| `--force` | Overwrite existing files if present |  |
| `--path` | Destination modules path | PATH |


## pack

Bundle the app for deployment (webhost or container).

**Options**

| Option | Description | Accepts |
|---|---|---|
| `--env` | Environment (production recommended) | ENV |
| `--include-dev` | Include dev dependencies |  |
| `--target` | Target (webhost|container) | TARGET |
| `--out` | Output directory | PATH |
| `--dry-run` | Print actions without writing files |  |

