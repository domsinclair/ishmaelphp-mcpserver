# Blog Tutorial — Part 14: JavaScript for the Blog (Client‑side Interactions)

In this part we cover when and how to use JavaScript in your Blog module. Many views render fine without JS, but some features are better with client‑side enhancements: image uploads, search as you type, like buttons, dark‑mode toggles, and small UI affordances.

What you’ll learn:
- Decide when JS is needed and keep it progressive (enhance, don’t depend).
- Where to keep your JS (module‑local vs. app‑wide) and how to publish it.
- How to load it in views (defer, module namespaces, cache busting).
- Build practical examples that interact with your PHP controllers via JSON.
- Share data between views and scripts safely using data‑ attributes.

Prerequisites:
- Parts 4 (Views and Layout), 5 (Routing and Middleware), 9 (Editing and Content), and 10 (Images and Storage).

## 1) Storage patterns for JS

Two common patterns, both valid:

- Module‑local (portable):
  - Source: `Modules/Blog/Resources/js/`
  - Published output: `public/modules/blog/`
  - Pros: self‑contained; easy to ship with the module.
  - Cons: you need a publish step to copy files to public.

- App‑wide (centralized):
  - Source: `SkeletonApp/resources/js/`
  - Output: `SkeletonApp/public/assets/`
  - Pros: single pipeline for the whole app.
  - Cons: module JS mixes with app JS unless you namespace carefully.

Pick one and document it for your team. For open‑source modules, prefer module‑local.

## 2) Loading strategies

- Use `<script src="..." defer></script>` so parsing doesn’t block rendering.
- Place tags in the layout’s `<head>` or right before `</body>`. With `defer`, both are fine.
- Add a simple cache buster: `?v=<?= urlencode($_ENV['ASSETS_VERSION'] ?? '1') ?>`.
- Namespacing: expose a global like `window.Blog = window.Blog || {}` to avoid collisions.

Example layout snippet:

```php
<script src="/modules/blog/blog.js?v=<?= urlencode($_ENV['ASSETS_VERSION'] ?? '1') ?>" defer></script>
```

Or app‑wide:

```php
<script src="/assets/app.js?v=<?= urlencode($_ENV['ASSETS_VERSION'] ?? '1') ?>" defer></script>
```

## 3) Passing data from PHP views to JS

Prefer data attributes in your HTML over inline script values. They’re safe to render and easy to read from JS.

```php
<button
  class="btn"
  data-like-url="<?= route('blog.posts.like', ['id' => $post['id']]) ?>"
  data-liked="<?= $post['liked_by_me'] ? '1' : '0' ?>">
  <span class="like-label"><?= $post['liked_by_me'] ? 'Unlike' : 'Like' ?></span>
</button>
```

In JS:

```js
const btn = document.querySelector('[data-like-url]');
const url = btn.dataset.likeUrl; // already absolute/site‑relative
```

## 4) Example: Like/Unlike with fetch() and a controller

Routes (Modules/Blog/routes.php):

```php
$router->post('/blog/posts/{id}/like', [PostController::class, 'like'])->name('blog.posts.like');
```

Controller action (simplified, returns JSON):

```php
public function like(Request $req, Response $res, int $id): Response
{
    $user = $req->getAttribute('user');
    if (!$user) { return $res->withStatus(401)->withBody('{"error":"auth"}'); }

    // toggle like (pseudo)
    $liked = $this->likes->toggle($id, $user->id); // returns true if now liked

    $payload = json_encode(['liked' => $liked], JSON_UNESCAPED_SLASHES);
    return $res->withHeader('Content-Type', 'application/json')->withBody($payload);
}
```

View button (from section 3) and JS to wire it up:

```js
(function () {
  const btn = document.querySelector('[data-like-url]');
  if (!btn) return;
  btn.addEventListener('click', async () => {
    const res = await fetch(btn.dataset.likeUrl, {
      method: 'POST',
      headers: { 'Accept': 'application/json' },
      credentials: 'same-origin'
    });
    if (!res.ok) return alert('Request failed');
    const data = await res.json();
    btn.dataset.liked = data.liked ? '1' : '0';
    btn.querySelector('.like-label').textContent = data.liked ? 'Unlike' : 'Like';
  });
})();
```

Notes:
- If your app uses CSRF protection, include the token in headers or in the body.
- Consider disabling the button while the request is pending.

## 5) Example: Live search (progressive enhancement)

Keep the existing server‑rendered search page from Part 6. Enhance it with client‑side fetch to update results without a full reload.

Route (existing): `/blog/posts?search=...`

Search form in the view:

```php
<form id="searchForm" action="/blog/posts" method="get">
  <input type="text" name="search" value="<?= htmlspecialchars($search ?? '') ?>" placeholder="Search posts..." />
  <button type="submit">Search</button>
</form>
<div id="results"> <?= $this->insert('posts/_list', ['posts' => $posts]) ?> </div>
```

JS enhancement:

```js
(function () {
  const form = document.getElementById('searchForm');
  const results = document.getElementById('results');
  if (!form || !results) return;
  const input = form.querySelector('input[name="search"]');
  let timer;
  input.addEventListener('input', () => {
    clearTimeout(timer);
    timer = setTimeout(async () => {
      const url = form.action + '?search=' + encodeURIComponent(input.value);
      const res = await fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
      if (!res.ok) return; // fall back to server
      const html = await res.text();
      results.innerHTML = html; // server returns partial (list only) when AJAX
    }, 250);
  });
})();
```

In your controller, detect `X-Requested-With: XMLHttpRequest` and render only the list partial for AJAX requests.

## 6) Example: Image upload UX (building on Part 10)

From Part 10, we had a simple form that uploads an image and inserts Markdown. You can keep the same approach or add drag‑and‑drop.

```js
(function () {
  const drop = document.getElementById('imgUpload');
  if (!drop) return;
  drop.addEventListener('dragover', (e) => { e.preventDefault(); drop.classList.add('ring'); });
  drop.addEventListener('dragleave', () => drop.classList.remove('ring'));
  drop.addEventListener('drop', async (e) => {
    e.preventDefault();
    const file = [...e.dataTransfer.files].find(f => f.type.startsWith('image/'));
    if (!file) return;
    const fd = new FormData();
    fd.append('image', file);
    const res = await fetch(drop.action, { method: 'POST', body: fd });
    if (!res.ok) return alert('Upload failed');
    const { url } = await res.json();
    const ta = document.querySelector('textarea[name="content"]');
    const md = `![](${url})`;
    const start = ta.selectionStart, end = ta.selectionEnd;
    ta.value = ta.value.slice(0, start) + md + ta.value.slice(end);
  });
})();
```

## 7) App‑wide vs module‑local build pipelines

If you already installed Tailwind (Part 13) you likely have Node tooling set up. You can also bundle/minify JS if desired:

- Simple: keep plain `.js` files, served directly from `public/` with `defer`.
- Advanced: use Vite/Rollup, emit `public/assets/app.js` and reference it in the layout.

Module‑local example with a publish step:

- Source: `Modules/Blog/Resources/js/blog.js`
- Build/copy to: `SkeletonApp/public/modules/blog/blog.js`
- Load with: `<script src="/modules/blog/blog.js?v=..." defer></script>`

## 8) Security & robustness tips

- Treat JSON responses as untrusted; validate before using.
- Use `textContent` to avoid injecting HTML unless you sanitize it.
- Respect CSRF protections if enabled in your app.
- Keep JS optional; the server should still return full pages.
- Avoid polluting the global scope; namespace under `window.Blog` if needed.

## 9) Putting it together in the layout

A minimal layout including both CSS (from Part 13) and a module JS file:

```php
<link rel="stylesheet" href="/assets/app.css?v=<?= urlencode($_ENV['ASSETS_VERSION'] ?? '1') ?>">
<script src="/modules/blog/blog.js?v=<?= urlencode($_ENV['ASSETS_VERSION'] ?? '1') ?>" defer></script>
```

## Related reading
- Part 4: [Views and Layout](./blog-part-4-views-and-layout.md)
- Part 5: [Routing and Middleware](./blog-part-5-routing-and-middleware.md)
- Part 6: [Pagination and Search](./blog-part-6-pagination-and-search.md)
- Part 9: [Authors, Editing Workflow, and Content Format](./blog-part-9-content-format-and-editing.md)
- Part 10: [Images and Storage](./blog-part-10-images-and-storage.md)
- Previous: [Part 13 — CSS for the Blog](./blog-part-13-css-for-the-blog.md)
- Next: [Part 15 — JavaScript Libraries and HTMX](./blog-part-15-javascript-libraries-and-htmx.md)
