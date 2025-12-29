# How to add Tailwind CSS to an Ishmael project

This guide shows how to install Tailwind CSS (v4+) using the built-in `ish` CLI and wire it into your views.

Prerequisites
- Node.js and npm must be installed and available on your PATH.

Quick start
1) From your project root, run:

```
ish ui:tailwind
```

What this does
- Verifies `node` and `npm` are available.
- Creates/updates `package.json` with convenient scripts:
  - `dev`: `@tailwindcss/cli -i resources/css/app.css -o public/assets/app.css -w`
  - `build`: `@tailwindcss/cli -i resources/css/app.css -o public/assets/app.css --minify`
- Installs Tailwind v4 requirements as dev dependencies: `tailwindcss` and `@tailwindcss/cli`.
- Creates `resources/css/app.css` (if missing) with:

```css
@import "tailwindcss";
@source "../../Modules/**/*.{php,html,js,ts,jsx,tsx,vue}";
```

- Builds `public/assets/app.css` once (minified).

Link it in your layout
Add this to the `<head>` of your layout (or uncomment it in generated stubs):

```html
<!-- <link rel="stylesheet" href="/assets/app.css"> -->
```

Rebuild or watch during development
- One-time build:

```
npm run build
```

- Watch for changes:

```
npm run dev
```

Options
- `--css=PATH` Change the input CSS path (default: `resources\css\app.css`).
- `--out=PATH` Change the output CSS path (default: `public\assets\app.css`).
- `--force` Overwrite existing CSS input if present.
- `--dry-run` Show actions without changing files.
- `--no-build` Skip the initial build step.

Notes
- Paths in `@source` are relative to the CSS file. By default we scan module views: `Modules/*/Views/*.php`.
- You can add more `@source` lines if you keep templates elsewhere (e.g., a top-level `Views/` directory).
- Tailwind v4 does not require a `tailwind.config.js`; using `@source` in CSS keeps setup simple.

Troubleshooting
- Error: "node not found" or "npm not found": ensure Node.js and npm are installed and added to PATH.
- Styles missing from the output: verify the `@source` relative path from `resources/css/app.css` to your `Modules/` directory.