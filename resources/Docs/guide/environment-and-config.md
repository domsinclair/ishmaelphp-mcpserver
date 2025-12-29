# Environment and Config in Ishmael

This guide explains how Ishmael loads environment variables from .env, how configuration files under config/* are merged, and how to safely and predictably override settings per environment. It includes copy‑paste examples and exact key mappings so you can confidently configure your app.

---

## TL;DR

- Put app- and machine‑specific values in .env. Commit config/*.php with sensible defaults that read from env().
- Loading order and precedence (highest wins):
  1) Runtime overrides (rare; e.g., set via tests)  
  2) .env values  
  3) Defaults inside config/*.php  
  4) Framework internal defaults (as a last resort)
- Access values with config('path.to.key') in your code. Only read from env() inside config/*.php.
- Default database engine is SQLite. Without .env settings, Ishmael will use storage/database.sqlite (or storage/ishmael.sqlite in the starter).

---

## How Ishmael loads environment variables

1) Bootstrap locates your application base (ISH_APP_BASE) and reads .env from the project root if present.  
2) Key=value pairs are loaded into the process environment (getenv()/$_ENV).  
3) The env() helper returns typed values where the config expects them (e.g., (int) env('SESSION_LIFETIME', 120)).

Rules and tips:
- .env is never committed — keep secrets there. Use .env.example in repos to show required keys.
- Only use env() inside config/*.php. In application code and modules, always use config(). This makes configuration cachable and testable.

---

## How configuration files are merged

- Each file under config/*.php returns an associative array.  
- At bootstrap, Ishmael loads every file and makes values available via config('filename.key').  
- Where a value is env‑backed, the env value takes precedence over the default in the config file.

Example (config/app.php):
```php
return [
    'name'  => env('APP_NAME', 'Ishmael'),
    'env'   => env('APP_ENV', 'development'),
    'debug' => env('APP_DEBUG', true),
    'url'   => env('APP_URL', 'http://ishmaelphp.test'),
];
```
Usage in code:
```php
$appName = config('app.name');
```

---

## Precedence and override rules

- If a key is provided in .env, it overrides the default specified in config/*.php.  
- If a key is missing from .env, the default defined by the config file applies.  
- If neither is provided, Ishmael’s internal fallback (if any) is used.

Implication: You can commit config defaults that are safe for all environments and override only what changes (credentials, hostnames, feature flags) in .env.

---

## Domains and keys (with mappings)

Below are the most common domains. For each key, we indicate its config() path and default.

Note: values shown here mirror the current defaults in config/*.php within Core. Your Starter may ship with slightly different defaults (e.g., the SQLite path) — adapt as needed.

### App
- APP_NAME → config('app.name') default: Ishmael
- APP_ENV → config('app.env') default: development
- APP_DEBUG → config('app.debug') default: true
- APP_URL → config('app.url') default: http://ishmaelphp.test
- TIMEZONE → config('app.timezone') default: UTC

Routing:
- ROUTING_HERD_BASE → config('app.routing.herd_base') default: true

### Logging (config/logging.php)
- LOG_CHANNEL → config('logging.default') default: stack
- LOG_LEVEL → config('logging.channels.*.level') defaults: debug/info depending on channel
- MONOLOG_HANDLER → config('logging.channels.monolog.handler') default: stream
- MONOLOG_SYSLOG_IDENT → config('logging.channels.monolog.ident') default: ishmael

Where logs are written: storage/logs/ishmael.log (adjusted in tests).

### Cache (config/cache.php)
- CACHE_DRIVER → config('cache.driver') default: file
- CACHE_TTL → config('cache.default_ttl') default: 0 (forever)
- CACHE_PREFIX → config('cache.prefix') default: ish

### Session (config/session.php)
- SESSION_DRIVER → config('session.driver') default: file
- SESSION_LIFETIME → config('session.lifetime') default: 120
- SESSION_COOKIE → config('session.cookie') default: ish_session
- SESSION_PATH → config('session.path') default: /
- SESSION_DOMAIN → config('session.domain') default: ''
- SESSION_SECURE → config('session.secure') default: false
- SESSION_HTTP_ONLY → config('session.http_only') default: true
- SESSION_SAME_SITE → config('session.same_site') default: Lax

### Database (config/database.php)
- DB_CONNECTION → config('database.default') default: sqlite

SQLite (when DB_CONNECTION=sqlite):
- DB_DATABASE → config('database.connections.sqlite.database') default: storage/database.sqlite (Starter may use storage/ishmael.sqlite)

MySQL (when DB_CONNECTION=mysql):
- DB_HOST → config('database.connections.mysql.host') default: 127.0.0.1
- DB_PORT → config('database.connections.mysql.port') default: 3306
- DB_DATABASE → config('database.connections.mysql.database') default: ishmael
- DB_USERNAME → config('database.connections.mysql.username') default: root
- DB_PASSWORD → config('database.connections.mysql.password') default: ''

PostgreSQL (when DB_CONNECTION=pgsql):
- DB_HOST → config('database.connections.pgsql.host') default: 127.0.0.1
- DB_PORT → config('database.connections.pgsql.port') default: 5432
- DB_DATABASE → config('database.connections.pgsql.database') default: ishmael
- DB_USERNAME → config('database.connections.pgsql.username') default: postgres
- DB_PASSWORD → config('database.connections.pgsql.password') default: ''

### Security & CSRF (config/security.php)
CSRF (middleware is enabled by default in config/app.php):
- n/a enable flag in config: config('security.csrf.enabled') default: true
- SECURITY header names are separate (see below). Token field name is config('security.csrf.field_name') default: _token.

Headers (applied by SecurityHeaders middleware when enabled):
- SECURITY_XFO → config('security.headers.x_frame_options') default: SAMEORIGIN
- SECURITY_XCTO → config('security.headers.x_content_type_options') default: nosniff
- SECURITY_REFERRER_POLICY → config('security.headers.referrer_policy') default: no-referrer-when-downgrade
- SECURITY_PERMISSIONS_POLICY → config('security.headers.permissions_policy') default: ''
- SECURITY_CSP → config('security.headers.content_security_policy') default: "default-src 'self'; frame-ancestors 'self'"
- SECURITY_HSTS → config('security.headers.hsts.enabled') default: false
- SECURITY_HSTS_ONLY_HTTPS → config('security.headers.hsts.only_https') default: true
- SECURITY_HSTS_MAX_AGE → config('security.headers.hsts.max_age') default: 15552000
- SECURITY_HSTS_INCLUDE_SUBDOMAINS → config('security.headers.hsts.include_subdomains') default: false
- SECURITY_HSTS_PRELOAD → config('security.headers.hsts.preload') default: false

To enable headers globally, add Ishmael\Core\Http\Middleware\SecurityHeaders to app.http.middleware (config/app.php) and configure the above keys.

### Auth (config/auth.php)
Provider fields:
- AUTH_USERS_TABLE → config('auth.providers.users.table') default: users
- AUTH_ID_COLUMN → config('auth.providers.users.id_column') default: id
- AUTH_USERNAME_COLUMN → config('auth.providers.users.username_column') default: email
- AUTH_PASSWORD_COLUMN → config('auth.providers.users.password_column') default: password

Hashing:
- AUTH_HASH_ALGO → config('auth.passwords.algo') default: bcrypt  
  Supported: bcrypt | argon2i | argon2id  
- AUTH_BCRYPT_COST → config('auth.passwords.cost') default: 12
- AUTH_ARGON2_MEMORY → config('auth.passwords.memory_cost') default: 131072
- AUTH_ARGON2_TIME → config('auth.passwords.time_cost') default: 4
- AUTH_ARGON2_THREADS → config('auth.passwords.threads') default: 2

Remember me:
- AUTH_REMEMBER_ENABLED → config('auth.remember_me.enabled') default: true
- AUTH_REMEMBER_COOKIE → config('auth.remember_me.cookie') default: ish_remember
- AUTH_REMEMBER_TTL → config('auth.remember_me.ttl') default: 43200 (minutes = 30 days)
- AUTH_REMEMBER_BIND_UA → config('auth.remember_me.bind_user_agent') default: true
- AUTH_REMEMBER_PATH/DOMAIN/SECURE/HTTP_ONLY/SAME_SITE → config('auth.remember_me.*') (defaults read from session settings)

Redirects:
- AUTH_LOGIN_PATH → config('auth.redirects.login') default: /login
- AUTH_HOME_PATH → config('auth.redirects.home') default: /

### Rate limiting (config/rate_limit.php)
- Presets are configured in config only. Apply via middleware with a preset name (default/strict/bursty).  
  You can change tokens/intervals in config without changing code.

---

## Example: Database configuration patterns

Default to SQLite (no .env changes required):
```env
DB_CONNECTION=sqlite
# optional: DB_DATABASE=storage/ishmael.sqlite
```

Switch to MySQL:
```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=ishmael
DB_USERNAME=root
DB_PASSWORD=secret
```

Switch to PostgreSQL:
```env
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=ishmael
DB_USERNAME=postgres
DB_PASSWORD=secret
```

---

## Example: Enabling security headers with a CSP

1) Enable the middleware (config/app.php):
```php
'http' => [
  'middleware' => [
    Ishmael\Core\Http\Middleware\StartSessionMiddleware::class,
    Ishmael\Core\Http\Middleware\VerifyCsrfToken::class,
    Ishmael\Core\Http\Middleware\SecurityHeaders::class,
  ],
],
```
2) Configure headers via .env:
```env
SECURITY_CSP="default-src 'self'; img-src 'self' https: data:; script-src 'self' 'unsafe-inline'"
SECURITY_XFO=SAMEORIGIN
SECURITY_XCTO=nosniff
SECURITY_REFERRER_POLICY=strict-origin-when-cross-origin
SECURITY_HSTS=false
```

---

## Example: Auth hashing algorithm

Default is bcrypt; switch to Argon2id if available in your PHP build:
```env
AUTH_HASH_ALGO=argon2id
AUTH_ARGON2_MEMORY=262144
AUTH_ARGON2_TIME=4
AUTH_ARGON2_THREADS=2
```

---

## Feature flags for generators (future‑proofing)

These are read by CLI generators (e.g., make:migration) to pre‑populate columns and naming conventions. If your Starter includes these, document them in .env.example as commented lines.

- FEATURE_MODULE_PK_STYLE (bool): Use <module>_id primary keys by default.
- FEATURE_SOFT_DELETES (bool): Add deleted_at timestamps.
- FEATURE_AUDIT (bool): Add created_at/updated_at and created_by/updated_by.
- AUDIT_ID_TYPE (int|string|uuid): Type for *_by columns.
- DEFAULT_PRIMARY_KEY_TYPE (int|uuid|ulid): PK type when generating migrations.

These map to generator behavior in Core tools rather than a single config file; treat them as “preferences” for scaffolding.

---

## Security and secrets guidance

- Never commit .env to VCS. Commit .env.example with placeholders and comments.  
- Separate per‑environment files: keep development .env locally, and use CI/CD to inject production secrets via environment variables on the server.  
- Rotate secrets regularly; treat APP_KEY, database passwords, and mail credentials as sensitive.

---

## Troubleshooting

- “My .env changes aren’t taking effect”  
  Ensure the process is reading the correct .env (project root) and that no config cache is persisting old values. Clear any app caches if you use a cache layer. Restart the PHP server/worker.

- “CSRF is blocking my API POSTs”  
  Either send the CSRF header/token, or add API paths to config('security.csrf.except_uris'), or separate API routing with appropriate protections.

- “Logs are not being written”  
  Check that storage/logs exists and is writable. Verify LOG_CHANNEL and LOG_LEVEL. In Windows, ensure the path resolves correctly.

---

## See also

- Configuration overview: guide/configuration.md  
- Config cache: guide/config-cache.md  
- CSRF, Idempotency, and Flash: guide/csrf-idempotency-flash.md  
- Security Headers: guide/security-headers.md  
- Writing and running migrations: guide/writing-and-running-migrations.md
