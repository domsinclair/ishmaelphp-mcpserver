# CSRF, Idempotency, and Flash (Phase‑14)

Phase‑14 makes CSRF protection a first‑class, default‑on feature for state‑changing requests and ships two optional helpers: Idempotency tokens for HTML forms and a tiny Flash helper. This page explains concepts, defaults, APIs, and configuration in one place, separate from the tutorials.

## 1) CSRF protection (default‑on)

- Middleware: `Ishmael\Core\Http\Middleware\VerifyCsrfToken`
- Enabled by default via the global middleware stack in `config/app.php`.
- Enforced for all methods except `GET`, `HEAD`, and `OPTIONS` by default.
- Accepted token sources (first present wins):
  - Hidden form field (default name: `_token`)
  - Header `X-CSRF-Token`
  - Header `X-XSRF-Token`

### 1.1 Token helpers

- `csrfToken()` — returns the current session’s token (generates one if missing).
- `csrfField()` — returns a ready‑to‑embed hidden input with the token.
- `csrfMeta()` — returns a `<meta name="csrf-token" content="...">` tag for JavaScript/XHR.

### 1.2 Router controls (escape hatches)

- Globally toggle at runtime: `Router::enableCsrfProtection(bool $enabled)`
- Configure which methods are protected: `Router::setCsrfMethods(['POST','PUT','PATCH','DELETE'])`
- Per‑route: `->withoutCsrf()` to bypass or `->withCsrf()` to force.
- Per‑group: `Router::groupWithoutCsrf([...], fn() => ...)` or `groupWithCsrf(...)`.

Use these sparingly, for example when handling public webhooks that authenticate with HMAC.

### 1.3 Failure behavior

- On mismatch or missing token, the middleware returns status 419.
- If the request prefers JSON (Accept header or XMLHttpRequest), a JSON error is returned; otherwise a minimal HTML page is returned.

### 1.4 Configuration (`config/security.php`)

Key options under `return ['csrf' => [...]]`:

- `enabled` — boolean (default true)
- `field_name` — hidden input name (default `_token`)
- `header_names` — array of header names to check
- `except_methods` — methods to skip (default `GET`, `HEAD`, `OPTIONS`)
- `except_uris` — URI patterns to skip (supports `*` suffix and exact match)
- `failure.status`, `failure.message`, `failure.code` — customize responses

## 2) Idempotency tokens (optional helper)

Namespace: `Ishmael\Core\Security\Idempotency`

Purpose: prevent double‑submissions of HTML forms (double‑clicks, back/forward re‑posts) by accepting a token once within a TTL.

API:
- `Idempotency::mint(): string` — generate a one‑time token and store it in session
- `Idempotency::consume(string $token, int $ttlSeconds = 1800): bool` — return true the first time a valid token is presented; false thereafter or on expiry
- `Idempotency::inputName(): string` — conventional field name (`idem_token`)
- `Idempotency::field(): string` — hidden input with a fresh token

Pattern:
1. GET create/edit → call `mint()` and include the token as a hidden input.
2. POST store/update → call `consume($incoming)` and if false, redirect with 409 or show a gentle warning.

Idempotency is complementary to CSRF: include both hidden fields in forms handling sensitive actions.

## 3) Flash messages (optional helper)

You can use the tiny `flash($key, $value = null)` helper to store one‑time messages across redirects.

- Setter: `flash('success', 'Saved!');`
- Getter (consumes): `$msg = flash('success');`
- Typical usage: set in a controller before redirect; read and render in the next view.

## 4) XHR usage pattern

When submitting forms or JSON via fetch/HTMX:

1. Include `<?= csrfMeta() ?>` in your base layout’s `<head>`.
2. Read the token from the meta tag in your JavaScript.
3. Send it in a header, e.g. `X-CSRF-Token`.

Placement guidance:

- Best practice: place the meta tag and the JavaScript helper in your base layout’s <head> so it is loaded once and available to every page.
- If you don’t have a base layout yet (e.g., early in the Blog Part 3 tutorial), copy the script into the <head> of each view temporarily. In Part 4 you’ll centralize it in a layout.

Example (place in your base layout’s <head>):

```html
<?= function_exists('csrfMeta') ? csrfMeta() : '' ?>
<script>
  function csrf() {
    const m = document.querySelector('meta[name="csrf-token"]');
    return m ? m.getAttribute('content') : null;
  }
  async function postJson(url, data) {
    const headers = { 'Content-Type': 'application/json' };
    const t = csrf(); if (t) headers['X-CSRF-Token'] = t;
    const res = await fetch(url, { method: 'POST', headers, body: JSON.stringify(data) });
    if (!res.ok) throw new Error('Request failed');
    return res.json();
  }
  window.postJson = postJson;
  window.csrf = csrf;
</script>
```

## 5) Security defaults and tips

- Sessions default to `SameSite=Lax`. Consider `Strict` for admin apps when UX allows.
- Prefer HTTPS and set `session.secure = true` outside local development.
- Do not disable CSRF globally. Use the route‑level bypass only when another strong authentication/verification mechanism is in place.

## 6) Troubleshooting 419 errors

- Missing field: Ensure `<?= csrfField() ?>` is inside every POST/PUT/PATCH/DELETE form.
- XHR: Ensure the token is present and sent via `X-CSRF-Token`.
- Sessions: Confirm session cookies are being set and persisted between requests.
- Mismatched domain/path or SameSite: verify cookie options in `config/session.php` or relevant keys in your app.
