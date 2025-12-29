# Blog Tutorial — Part 15: JavaScript Libraries and HTMX (Alternatives to Vanilla JS)

In Part 14 you implemented small client-side behaviors using plain JavaScript. In this part we explore when a library is worth it, survey a few light options, and take a practical look at HTMX — a popular way to add dynamic UX by letting the server continue to render HTML.

What you’ll learn:
- How to choose between Vanilla JS and popular micro‑libraries.
- Pros and cons of Alpine.js, Stimulus, and tiny DOM helpers.
- HTMX fundamentals with concrete, Ishmael‑friendly examples: hx-get, hx-post, hx-boost, partial views, and swapping.
- Handling CSRF, loading indicators, and progressive enhancement.

Prerequisites:
- Parts 4 (Views & Layout), 5 (Routing & Middleware), 6 (Pagination & Search), 10 (Images), and 14 (JavaScript for the Blog).

## 1) Choosing Vanilla JS vs a library

Vanilla JS is often enough:
- Simple interactions (toggle classes, submit forms with fetch, small AJAX updates)
- You control exactly what ships; no dependency risk
- Lowest cognitive overhead for new contributors

When a library helps:
- You repeat the same patterns (binding events to elements, stateful widgets)
- You want declarative, HTML‑first bindings (e.g., data attributes that wire actions)
- You prefer server‑rendered HTML but still want incremental interactivity (HTMX)

Rule of thumb: start with Vanilla JS; add a tiny library only where it clearly reduces code and complexity.

## 2) Micro‑libraries at a glance

- Alpine.js
  - Pros: HTML‑first directives (`x-data`, `x-on`, `x-show`), small footprint, easy to sprinkle in.
  - Cons: Adds a runtime; debugging can feel “magical” at first; patterns may compete with HTMX for control.

- Stimulus
  - Pros: Controller‑based structure that plays well with server‑rendered HTML; explicit class‑based controllers.
  - Cons: Slightly more boilerplate; best with conventions and build tooling.

- Tiny DOM helpers (e.g., Cash, UmbrellaJS)
  - Pros: jQuery‑like APIs in a few KB; handy if you miss `$()` selectors and chaining.
  - Cons: Another dependency; modern DOM APIs already cover most needs.

- HTMX (hypermedia‑driven UX)
  - Pros: No SPA; server continues to render HTML; progressive enhancement; tiny attribute API; great for forms, pagination, partial updates.
  - Cons: Requires thinking in terms of fragments/partials; complex client‑side state machines are a better fit for a JS framework.

## 3) Quick start with HTMX

Install

- Easiest: CDN

```html
<script src="https://unpkg.com/htmx.org@1.9.12" integrity="sha384-..." crossorigin="anonymous" defer></script>
```

- Module‑local (portable):
  - Download htmx.min.js into `Modules/Blog/Resources/js/htmx.min.js` and publish to `public/modules/blog/`.
  - Load in layout: `<script src="/modules/blog/htmx.min.js" defer></script>`

Security note: If your app uses CSRF, add a global header for HTMX requests (section 3.4).

### 3.1) Basics: GET a partial into a target

Routes (Modules/Blog/routes.php):

```php
$router->get('/blog/posts/partial/list', [PostController::class, 'listPartial'])
    ->name('blog.posts.partial.list');
```

Controller excerpt:

```php
public function listPartial(Request $req, Response $res): Response
{
    $search = trim((string) $req->query('search'));
    // $posts = ... fetch filtered list
    // If it's an HTMX request, render only the list partial
    return $res->withBody($this->view('posts/_list', compact('posts')));
}
```

View (index.php):

```php
<form class="mb-4" hx-get="<?= route('blog.posts.partial.list') ?>" hx-target="#results" hx-trigger="keyup changed delay:300ms from:#search">
  <input id="search" type="text" name="search" placeholder="Search posts..." />
</form>
<div id="results">
  <?= $this->insert('posts/_list', ['posts' => $posts]) ?>
</div>
```

Explanation:
- `hx-get` issues an AJAX GET to the route whenever the trigger fires.
- `hx-target="#results"` replaces that element’s inner HTML with the response.
- `delay:300ms` debounces keystrokes.

### 3.2) POST a form and swap in a flash message

Route:

```php
$router->post('/blog/posts/{id}/like', [PostController::class, 'like'])->name('blog.posts.like');
```

View button (no extra JS needed):

```html
<button
  hx-post="<?= route('blog.posts.like', ['id' => $post['id']]) ?>"
  hx-swap="outerHTML"
  hx-target="this">
  <?= $post['liked_by_me'] ? 'Unlike' : 'Like' ?>
</button>
```

Controller returns the new button HTML (server‑rendered fragment). HTMX swaps it over the old button (`outerHTML`).

### 3.3) Progressive page navigation: hx-boost

Enable hyperlink/form boosting so normal links submit via AJAX and replace `body`:

```html
<body hx-boost="true" hx-target="body">
  <!-- normal <a> links now fetch and swap body with the response body -->
</body>
```

Use sparingly; for blogs it can make pagination and post navigation feel instant, while still preserving normal navigation if JS is disabled.

### 3.4) CSRF headers, loading indicators, and errors

Add a global header for all HTMX requests if your app checks CSRF tokens:

```html
<meta name="csrf-token" content="<?= htmlspecialchars($_SESSION['csrf'] ?? '') ?>">
<script>
  document.addEventListener('htmx:configRequest', (e) => {
    const token = document.querySelector('meta[name="csrf-token"]').content;
    e.detail.headers['X-CSRF-TOKEN'] = token;
  });
</script>
```

Loading indicator for a specific target:

```html
<div id="results" hx-indicator="#resultsSpinner">
  <div id="resultsSpinner" class="hidden">Loading…</div>
  <!-- list content here -->
</div>
```

Global error handler:

```html
<script>
  document.addEventListener('htmx:httpError', (e) => {
    alert('Request failed: ' + e.detail.xhr.status);
  });
</script>
```

### 3.5) Partial view conventions

- Keep reusable fragments under `Views/posts/_list.php`, `_item.php`, etc.
- Controller actions can detect HTMX via `HX-Request` header if you need different behavior.
- Prefer returning HTML from controllers for HTMX endpoints; keep JSON endpoints for API use.

## 4) Pros and cons summary

- Vanilla JS
  - Pros: zero deps, full control, no runtime cost.
  - Cons: repetitive wiring if many interactive widgets; you’ll build small conventions yourself.

- Alpine.js / Stimulus
  - Pros: declarative bindings reduce boilerplate; good for moderate UI state.
  - Cons: adds a runtime; mental model differs from HTMX’s request‑driven approach.

- HTMX
  - Pros: server keeps rendering HTML; excellent for forms, filters, pagination, CRUD; easy to adopt incrementally; progressive by default.
  - Cons: complex client‑side state machines are awkward; think in fragments and swaps; still need JS for rich widgets.

## 5) Putting it together: search + pagination with HTMX

Search form:

```php
<form class="mb-4" hx-get="<?= route('blog.posts.partial.list') ?>" hx-target="#results" hx-push-url="true">
  <input type="text" name="search" value="<?= htmlspecialchars($search ?? '') ?>" placeholder="Search posts..." />
</form>
<div id="results">
  <?= $this->insert('posts/_list', ['posts' => $posts]) ?>
</div>
```

List partial with pagination links (normal anchors):

```php
<nav class="mt-4" hx-boost="true" hx-target="#results">
  <a href="?page=<?= $page-1 ?>">Prev</a>
  <a href="?page=<?= $page+1 ?>">Next</a>
</nav>
```

Because of `hx-boost`, clicking pagination links fetches and swaps `#results` only, and `hx-push-url="true"` updates the browser URL.

## Related reading
- Part 4: [Views and Layout](./blog-part-4-views-and-layout.md)
- Part 5: [Routing and Middleware](./blog-part-5-routing-and-middleware.md)
- Part 6: [Pagination and Search](./blog-part-6-pagination-and-search.md)
- Part 10: [Images and Storage](./blog-part-10-images-and-storage.md)
- Part 14: [JavaScript for the Blog](./blog-part-14-javascript-for-the-blog.md)
- Next: [Part 16 — TypeScript for the Blog](./blog-part-16-typescript-for-the-blog.md)
