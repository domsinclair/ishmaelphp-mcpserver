# CLI Route Commands

Ishmael CLI provides commands to inspect and manage routes:

## ish route:list

Lists all routes with optional filtering.

Usage:
```
ish route:list [--method=GET] [--module=Name]
```

Columns:
- METHOD: Allowed methods (pipe-delimited)
- PATH: The route path pattern
- NAME: Route name if assigned
- HANDLER: Controller@action or callable
- MODULE: Module hint (if provided via groups)
- MW: Number of middleware entries on the route

## ish route:cache

Compiles the application's routes to a deterministic cache file for faster cold boots.

Usage:
```
ish route:cache [--force]
```

- Fails fast if non-cacheable handlers or middleware are present (closures or object callables). The error will include details and a hint.
- With `--force`, non-cacheable entries are stripped, and warnings are embedded in the cache metadata and printed after writing the cache.

## ish route:clear

Clears the previously generated route cache, restoring dynamic route discovery.

Usage:
```
ish route:clear
```

In production, the Kernel loads the cache when present and fresh; in development, dynamic discovery remains the default.

---

## CSRF and generated routes

The route generation performed by CLI commands and templates does NOT wrap routes in CSRF-specific groups. CSRF protection is enforced globally by the VerifyCsrfToken middleware registered in `config/app.php` starting with Phase 14.

- No need to add `['middleware' => ['csrf']]` to groups; it’s redundant.
- To exempt specific endpoints (e.g., third-party webhooks), add patterns to `config/security.php` → `csrf.except_uris`.
- Token names: form field `_token`; request headers `X-CSRF-Token` or `X-XSRF-Token`.

Quick sanity test:
- POST to any route without a token → expect 419 “CSRF token mismatch.”
- Submit a form with `<input type="hidden" name="_token" value="<?= csrfToken() ?>">` → request should pass.
