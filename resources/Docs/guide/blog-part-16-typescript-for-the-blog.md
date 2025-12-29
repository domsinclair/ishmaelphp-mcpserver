# Blog Tutorial — Part 16: TypeScript for the Blog (Safer, Typed Client Code)

JavaScript is great for light enhancements (see Part 14) and HTMX can take you far (Part 15). For larger or more complex interactions, TypeScript (TS) adds static types and tooling that catch bugs early and improve IDE assistance.

What you’ll learn:
- Why and when to adopt TypeScript in an Ishmael app/module.
- Two setup paths: simple `tsc` (no bundler) and Vite (bundled).
- Module‑local vs app‑wide asset layout and how to publish compiled JS.
- Practical examples: typed fetch to a controller, DOM helpers, and module namespaces.
- Pros and cons to help you decide.

Prerequisites:
- Part 13 (CSS build pipeline options), Part 14 (JavaScript patterns), Part 15 (HTMX alternatives).

## 1) Why TypeScript?

Pros:
- Catch errors at compile time (null/undefined, wrong property names, etc.).
- Self‑documenting code with interfaces and enums.
- Excellent editor/IDE support and refactoring tools.
- Scales better as interactions grow.

Cons:
- Build step required (compile TS → JS).
- Minor learning curve for types and generics.
- Configuration overhead if you need advanced features.

Rule of thumb: If your Blog’s JS grows beyond a few small files, TS quickly pays for itself.

## 2) Project layout for TS sources and outputs

Two common strategies — both work with Ishmael’s module approach.

- Module‑local (portable):
  - Source: `Modules/Blog/Resources/ts/`
  - Output (compiled JS): `SkeletonApp/public/modules/blog/`
  - Load in views: `<script src="/modules/blog/blog.js?v=..." defer></script>`

- App‑wide (centralized):
  - Source: `SkeletonApp/resources/ts/`
  - Output: `SkeletonApp/public/assets/`
  - Load in layout: `<script src="/assets/app.js?v=..." defer></script>`

Pick one and document the publish/build step for your team.

## 3) Path A — Simple `tsc` (no bundler)

This path keeps things simple: compile a few `.ts` files to plain JS without bundling.

### 3.1) Install TypeScript

```bash
npm init -y
npm install --save-dev typescript
npx tsc --init --rootDir ./Modules/Blog/Resources/ts --outDir ./SkeletonApp/public/modules/blog --module ES2020 --target ES2020 --sourceMap true
```

The `tsconfig.json` created by `--init` can be edited later; the flags above set sensible defaults for modern browsers and module output.

Example `tsconfig.json` (tailored for a module‑local setup):

```json
{
  "compilerOptions": {
    "target": "ES2020",
    "module": "ES2020",
    "strict": true,
    "rootDir": "Modules/Blog/Resources/ts",
    "outDir": "SkeletonApp/public/modules/blog",
    "sourceMap": true,
    "forceConsistentCasingInFileNames": true,
    "skipLibCheck": true
  },
  "include": ["Modules/Blog/Resources/ts/**/*"]
}
```

### 3.2) Author TypeScript files

Create `Modules/Blog/Resources/ts/blog.ts`:

```ts
// A tiny namespace to avoid polluting window
export const Blog = {
  qs<T extends Element = HTMLElement>(sel: string, root: Document | Element = document): T | null {
    return root.querySelector(sel) as T | null;
  },
};

// Example: typed response from a Like endpoint
interface LikeResponse {
  liked: boolean;
}

function bindLikeButton() {
  const btn = Blog.qs<HTMLButtonElement>('[data-like-url]');
  if (!btn) return;
  btn.addEventListener('click', async () => {
    const res = await fetch(btn.dataset.likeUrl!, {
      method: 'POST',
      headers: { 'Accept': 'application/json' },
      credentials: 'same-origin',
    });
    if (!res.ok) {
      console.warn('Request failed', res.status);
      return;
    }
    const data = (await res.json()) as LikeResponse;
    btn.dataset.liked = data.liked ? '1' : '0';
    const label = btn.querySelector('.like-label');
    if (label) label.textContent = data.liked ? 'Unlike' : 'Like';
  });
}

document.addEventListener('DOMContentLoaded', () => {
  bindLikeButton();
});
```

Compile:

```bash
npx tsc --watch
# or single build
npx tsc
```

The compiler outputs `SkeletonApp/public/modules/blog/blog.js` and `blog.js.map`.

### 3.3) Load the compiled JS

In your layout or post view:

```php
<script type="module" src="/modules/blog/blog.js?v=<?= urlencode($_ENV['ASSETS_VERSION'] ?? '1') ?>" defer></script>
```

Note: `type="module"` enables modern ES modules output by our `tsconfig`. If you target older browsers, set `module` to `system` or `commonjs` and adjust accordingly.

## 4) Path B — Vite (bundled, recommended for larger apps)

Vite provides fast dev server, bundling, code splitting, and integrates with TS out of the box.

### 4.1) Install Vite + TS

```bash
npm init -y
npm install --save-dev vite typescript
```

Create `SkeletonApp/vite.config.js` (app‑wide example that emits to `public/assets`):

```js
import { defineConfig } from 'vite';

export default defineConfig({
  root: '.',
  build: {
    outDir: 'SkeletonApp/public/assets',
    emptyOutDir: false,
    rollupOptions: {
      input: {
        app: 'SkeletonApp/resources/ts/app.ts',
      },
      output: {
        entryFileNames: `[name].js`,
        chunkFileNames: `chunks/[name]-[hash].js`,
        assetFileNames: `assets/[name]-[hash][extname]`,
      },
    },
    sourcemap: true,
  },
});
```

Create `SkeletonApp/resources/ts/app.ts` and move/port your TS there if you prefer an app‑wide bundle. For module‑local Vite projects, place a config inside the module and point `outDir` at `SkeletonApp/public/modules/blog`.

Build:

```bash
npx vite build
# Dev server (optional):
npx vite
```

Load in layout:

```php
<script type="module" src="/assets/app.js?v=<?= urlencode($_ENV['ASSETS_VERSION'] ?? '1') ?>" defer></script>
```

Tip: If you also use Tailwind (Part 13), Vite can process CSS and TS together so you ship a minimal, cacheable bundle.

## 5) Example: Typed live search (progressive enhancement)

Controller keeps the same endpoint from Part 6 (`/blog/posts?search=...`), but we’ll build a typed client.

Create `Modules/Blog/Resources/ts/search.ts`:

```ts
interface SearchResultItem {
  id: number;
  title: string;
  excerpt: string;
  url: string;
}

async function fetchResults(url: string): Promise<string> {
  const res = await fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
  if (!res.ok) throw new Error(`HTTP ${res.status}`);
  // Server returns HTML partial; keep it typed as string
  return await res.text();
}

export function bindSearch(formSelector = '#searchForm', resultsSelector = '#results') {
  const form = document.querySelector<HTMLFormElement>(formSelector);
  const results = document.querySelector<HTMLElement>(resultsSelector);
  if (!form || !results) return;
  const input = form.querySelector<HTMLInputElement>('input[name="search"]');
  if (!input) return;
  let timer: number | undefined;
  input.addEventListener('input', () => {
    if (timer) window.clearTimeout(timer);
    timer = window.setTimeout(async () => {
      const url = form.action + '?search=' + encodeURIComponent(input.value);
      try {
        const html = await fetchResults(url);
        results.innerHTML = html;
      } catch (e) {
        console.warn('Search failed', e);
      }
    }, 250);
  });
}

// Auto-bind in dev; in production import explicitly from your entry file
bindSearch();
```

Compile with `tsc` or include it in your Vite entry.

## 6) Pros and cons summary

- TypeScript pros
  - Catches bugs early; safer refactors.
  - Better editor tooling and auto-complete.
  - Encourages modular code and explicit interfaces.

- TypeScript cons
  - Build step and configuration.
  - Extra complexity for very small scripts.
  - Type definitions for some libs may be incomplete and require @types packages.

## 7) Migrating incrementally from JS

- Start by renaming a single `.js` file to `.ts` and fix obvious `any`/null issues.
- Add `// @ts-check` to plain JS files as a stepping stone (TypeScript will type-check JSDoc).
- Use JSDoc types in JS if you cannot add a build yet.
- Gradually enable `strict` checks in `tsconfig.json`.

## Related reading
- Part 4: [Views and Layout](./blog-part-4-views-and-layout.md)
- Part 5: [Routing and Middleware](./blog-part-5-routing-and-middleware.md)
- Part 6: [Pagination and Search](./blog-part-6-pagination-and-search.md)
- Part 10: [Images and Storage](./blog-part-10-images-and-storage.md)
- Part 13: [CSS for the Blog](./blog-part-13-css-for-the-blog.md)
- Part 14: [JavaScript for the Blog](./blog-part-14-javascript-for-the-blog.md)
- Previous: [Part 15 — JavaScript Libraries and HTMX](./blog-part-15-javascript-libraries-and-htmx.md)