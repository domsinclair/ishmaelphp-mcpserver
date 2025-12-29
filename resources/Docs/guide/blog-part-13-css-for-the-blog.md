# Blog Tutorial — Part 13: CSS for the Blog (Tailwind or Plain CSS)

In this part we cover styling your Blog module. Ishmael recommends Tailwind CSS for speed and consistency — and it’s what we use in many examples — but you can absolutely write your own CSS. This guide shows both approaches in a way that keeps your module portable between apps.

What you’ll learn:
- Add Tailwind to your Ishmael app the right way for production (not just a CDN link).
- Reference compiled CSS from your views and layout.
- Support light/dark themes automatically, and optionally add a manual toggle.
- Embrace responsive design with Tailwind’s breakpoints (or with your own CSS).
- Use ready‑made Tailwind UI kits/components safely.
- If you prefer plain CSS: where to keep it (module‑scoped), how to publish/reference it, and portability tips.

Prerequisites:
- Completed Part 4 (Views and Layout) and Part 5 (Routing and Middleware) for context on where to add links and routes.

## 1) Tailwind for production (recommended)

Early parts used the Tailwind Play CDN for quick prototyping. For production you should compile a minimal CSS bundle so your pages load fast and don’t ship unused styles.

Important: Tailwind CSS v4 introduced a new, simpler install flow that does not require a tailwind.config.js by default. If you are using Tailwind v3, keep following the classic config‑based approach. Both paths are documented below.

### 1A) Tailwind v4 (CLI, zero‑config)

#### 1A.1) Project structure for assets

- App‑wide assets:
  - `SkeletonApp/resources/css/` (your source CSS)
  - `SkeletonApp/public/assets/` (compiled output)
- Module‑scoped assets (portable between apps):
  - `Modules/Blog/Resources/css/` (module source)
  - Publish/copy at build/pack time to: `public/modules/blog/` (compiled output)

Either approach works. For maximum module portability, keep sources under the module and provide a publish step. Examples below show an app‑wide setup; a module‑scoped variant is explained in section 4.

#### 1A.2) Install Tailwind v4 CLI

From the repository root (or your application root):

```bash
npm init -y
npm install tailwindcss @tailwindcss/cli
```

No config file is required for v4.

#### 1A.3) Create source CSS (v4)

Create `SkeletonApp/resources/css/app.css` (or `Modules/Blog/Resources/css/blog.css` if module‑scoped) and import Tailwind:

```css
@import "tailwindcss";

/* Optional blog tweaks (can live here as normal CSS) */
/* You can still use utility classes in your HTML; no @apply required. */
```

You can also keep small component styles here if needed.

#### 1A.4) Build for development/production (v4)

Use the Tailwind v4 CLI to scan your source files and build CSS:

```bash
# Dev (watch)
npx @tailwindcss/cli -i ./SkeletonApp/resources/css/app.css -o ./SkeletonApp/public/assets/app.css --watch

# Production (minified)
npx @tailwindcss/cli -i ./SkeletonApp/resources/css/app.css -o ./SkeletonApp/public/assets/app.css --minify
```

Tip: If you keep CSS inside a module, adjust paths accordingly (input from `Modules/Blog/Resources/css/blog.css`, output to `SkeletonApp/public/modules/blog/blog.css`).

#### 1A.5) Reference CSS from the layout

Update your app layout (see Part 4) to load the compiled file. Example `layout.php` snippet:

```php
<link rel="stylesheet" href="/assets/app.css?v=<?= urlencode($_ENV['ASSETS_VERSION'] ?? '1') ?>">
```

Use a simple cache buster `ASSETS_VERSION` (env var) so you can bump it on deploy.

Note on theming: Tailwind v4 still supports the `dark:` variant. You don’t need a config file to use it — simply add/remove a `.dark` class on `<html>` (see section 2), or rely on CSS media queries for system dark mode.

---

### 1B) Tailwind v3 (legacy, config‑based)

If your project is on Tailwind v3 or you prefer the classic PostCSS flow, follow this path.

#### 1B.1) Install Tailwind v3

```bash
npm init -y
npm install -D tailwindcss@^3 autoprefixer postcss
npx tailwindcss init -p
```

This creates `tailwind.config.js` and `postcss.config.js`.

#### 1B.2) Configure content paths (v3)

Make sure Tailwind can see your view files so it can remove unused styles. Include both the app and module view directories:

```js
// tailwind.config.js
module.exports = {
  content: [
    './SkeletonApp/Modules/**/*.php',
    './SkeletonApp/Views/**/*.php',
    './IshmaelPHP-Core/Modules/**/*.php',
    './IshmaelPHP-Core/Resources/Views/**/*.php',
    './IshmaelPHP-Core/Documentation/**/*.md' // optional if you style docs with Tailwind
  ],
  theme: {
    extend: {},
  },
  darkMode: 'media', // or 'class' (see theming below)
  plugins: [],
};
```

Adjust the paths to match where your production views actually live. If your app keeps module views strictly under `SkeletonApp/Modules`, you can omit the Ishmael core paths.

#### 1B.3) Create source CSS (v3)

Create `SkeletonApp/resources/css/app.css` (or module‑scoped source):

```css
@tailwind base;
@tailwind components;
@tailwind utilities;

/* Optional blog tweaks */
.prose img { @apply rounded-md; }
.btn { @apply inline-flex items-center px-3 py-2 rounded-md bg-blue-600 text-white hover:bg-blue-700; }
```

#### 1B.4) Build for development/production (v3)

Use the Tailwind CLI (v3) to build to your public assets folder:

```bash
npx tailwindcss -i ./SkeletonApp/resources/css/app.css -o ./SkeletonApp/public/assets/app.css --watch
# Production (minified, purge applied via content)
npx tailwindcss -i ./SkeletonApp/resources/css/app.css -o ./SkeletonApp/public/assets/app.css --minify
```

If using Vite, create `vite.config.js`, import `app.css`, and run `vite build` — the output path should still land in `public/assets` for easy referencing.

## 2) Light/Dark theming

You have two great options:

- Automatic (system preference)
- Manual toggle (class strategy)

Tailwind v4 notes:
- You don’t need a config file to use dark mode. For automatic dark mode, rely on the standard CSS `prefers-color-scheme` media query. For manual dark mode, add/remove a `.dark` class on `<html>` and use Tailwind’s `dark:` variants in your markup.

Tailwind v3 notes:
- Set `darkMode: 'media'` (automatic) or `darkMode: 'class'` (manual) in `tailwind.config.js` as shown in section 1B.

### 2.1) Automatic theming (media)

Use the `prefers-color-scheme` media query implicitly; Tailwind’s `dark:` variants will follow automatically when the page is in dark mode.

```html
<body class="bg-white text-gray-900 dark:bg-slate-900 dark:text-slate-100">
  <article class="prose dark:prose-invert">
    <!-- post content -->
  </article>
</body>
```

No extra JS required. In Tailwind v3, set `darkMode: 'media'` in config. In v4, no config is required.

### 2.2) Manual toggle (class)

Add a tiny toggle script to set a `.dark` class on `<html>`. In Tailwind v3, ensure `darkMode: 'class'` in config; in v4, no config is required.

```html
<html class="" data-theme="light">
<head>
  <script>
    // Respect stored preference, fallback to system
    const preferDark = localStorage.getItem('theme') ?? (matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
    if (preferDark === 'dark') document.documentElement.classList.add('dark');
  </script>
</head>
<body class="bg-white text-gray-900 dark:bg-slate-900 dark:text-slate-100">
  <button id="themeToggle" class="btn">Toggle theme</button>
  <script>
    document.getElementById('themeToggle').addEventListener('click', () => {
      const el = document.documentElement;
      const on = el.classList.toggle('dark');
      localStorage.setItem('theme', on ? 'dark' : 'light');
    });
  </script>
</body>
</html>
```

## 3) Responsive design

Tailwind’s responsive prefixes make layouts intuitive:

```html
<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
  <!-- blog cards -->
</div>

<img class="w-full sm:w-1/2 lg:w-1/3 rounded" src="..." alt="...">
```

On the blog list page, you might stack posts on mobile and show multiple columns on larger screens. For a post header image:

```html
<header class="container mx-auto px-4">
  <h1 class="text-2xl sm:text-3xl lg:text-4xl font-bold">Post Title</h1>
  <p class="mt-2 text-gray-600 dark:text-gray-300">Subtitle text</p>
</header>
```

## 4) Plain CSS (roll your own)

If you’d rather not use Tailwind, that’s fine. The key is to keep your CSS module‑scoped so the Blog module remains portable.

Recommended structure:
- Source: `Modules/Blog/Resources/css/blog.css`
- Published output: `public/modules/blog/blog.css`

A minimal CSS file:

```css
body { font-family: system-ui, -apple-system, Segoe UI, Roboto, sans-serif; }
.btn { display: inline-flex; padding: .5rem .75rem; border-radius: .375rem; background: #1d4ed8; color: #fff; }
.post { max-width: 70ch; margin-inline: auto; padding: 1rem; }
@media (prefers-color-scheme: dark) {
  body { background: #0f172a; color: #e2e8f0; }
}
@media (min-width: 640px) {
  .posts { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 1rem; }
}
```

Publish/copy step options:
- During development, manually copy: `cp Modules/Blog/Resources/css/blog.css SkeletonApp/public/modules/blog/blog.css`
- In CI or a build script, add a task to copy/update module assets to the app’s public folder.

Reference in your layout or specific blog views:

```php
<link rel="stylesheet" href="/modules/blog/blog.css?v=<?= urlencode($_ENV['ASSETS_VERSION'] ?? '1') ?>">
```

This keeps the module self‑contained and easy to move between apps. If you open‑source your Blog module, document the single “publish assets” step.

## 5) Using Tailwind UI and other component libraries

You can adopt prebuilt Tailwind components (Tailwind UI, Flowbite, DaisyUI, HyperUI, Headless UI, etc.). Tips:
- Only import the CSS you need in your templates so Tailwind’s purge removes the rest.
- If the library ships JavaScript (e.g., Flowbite), see Part 14 for how to load module‑scoped JS.
- Keep brand colors and spacing tokens in `tailwind.config.js` so you can swap themes per app.

Example of extending Tailwind theme:

```js
// tailwind.config.js
module.exports = {
  content: [ './SkeletonApp/Modules/**/*.php' ],
  theme: {
    extend: {
      colors: {
        brand: {
          600: '#2563eb',
          700: '#1d4ed8',
        }
      }
    }
  },
  darkMode: 'media'
}
```

Then in views: `class="bg-brand-700 hover:bg-brand-600"`.

## 6) Example: wiring the blog layout

A minimal blog layout using Tailwind (compiled CSS) with dark mode:

```php
<?php /** @var string $content */ ?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title><?= htmlspecialchars($title ?? 'Blog') ?></title>
  <link rel="stylesheet" href="/assets/app.css?v=<?= urlencode($_ENV['ASSETS_VERSION'] ?? '1') ?>">
</head>
<body class="min-h-screen bg-white text-gray-900 dark:bg-slate-900 dark:text-slate-100">
  <header class="border-b border-gray-200 dark:border-slate-700">
    <div class="container mx-auto px-4 py-4 flex items-center justify-between">
      <a href="/blog/posts" class="font-semibold">Blog</a>
      <nav class="flex gap-4 text-sm">
        <a class="hover:underline" href="/blog/posts">Posts</a>
        <a class="hover:underline" href="/blog/about">About</a>
      </nav>
    </div>
  </header>
  <main class="container mx-auto px-4 py-6">
    <?= $content ?>
  </main>
  <footer class="py-8 text-sm text-center text-gray-500 dark:text-gray-400">© <?= date('Y') ?> Your Site</footer>
</body>
</html>
```

Swap `/assets/app.css` for `/modules/blog/blog.css` if you’re on plain CSS.

## Related reading
- Part 4: [Views and Layout](./blog-part-4-views-and-layout.md)
- Part 5: [Routing and Middleware](./blog-part-5-routing-and-middleware.md)
- Part 9: [Authors, Editing Workflow, and Content Format](./blog-part-9-content-format-and-editing.md)
- Part 10: [Images and Storage](./blog-part-10-images-and-storage.md)
- Part 12: [Logging and Debugging](./blog-part-12-logging-and-debugging.md)
- Next: [Part 14 — JavaScript for the Blog](./blog-part-14-javascript-for-the-blog.md)
