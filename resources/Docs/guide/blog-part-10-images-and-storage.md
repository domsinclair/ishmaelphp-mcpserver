# Blog Tutorial — Part 10: Images, Uploads, and Storage

In this part we focus on handling images in your blog: how authors upload them, where to store them, and how to reference them from posts — including Markdown posts.

What you’ll learn:
- Provide a simple image upload endpoint protected by author permissions.
- Choose storage locations (public vs private) and URL generation.
- Reference images from Markdown and plain‑text posts.
- Optional: process/resize images and name them predictably.

Prerequisites:
- Completed Part 9 (roles/authoring and content format), or equivalent auth.

## 1) Storage options: where do images live?

Common approaches:

- Public filesystem folder under your web root, e.g. `/public/uploads/blog/`.
  - Pros: simple, no controller needed for serving.
  - Cons: anything placed here is publicly readable.

- Private storage folder plus a controller to stream files (e.g. `/storage/uploads/blog/`), checking permissions as needed.
  - Pros: can protect drafts or private images.
  - Cons: requires a route/controller to serve files.

For a public blog, the public folder is usually fine. We’ll assume:

- Disk path: `SkeletonApp/public/uploads/blog/`
- Public URL base: `/uploads/blog/`

Create the directory if missing and ensure it’s writable by PHP.

## 2) Upload route and controller

Protect uploads with the same RequireAuthor middleware from Part 9.

Modules/Blog/routes.php:

```php
<?php
use Ishmael\Core\Routing\Router;
use Modules\Blog\Controllers\MediaController;
use Modules\Blog\Middleware\RequireAuthor;

/** @var Router $router */
$router->post('/blog/media/upload', [MediaController::class, 'upload'])
    ->name('blog.media.upload')
    ->middleware(RequireAuthor::class);
```

Modules/Blog/Controllers/MediaController.php:

```php
<?php

declare(strict_types=1);

namespace Modules\Blog\Controllers;

use Ishmael\Core\Http\Request;
use Ishmael\Core\Http\Response;

final class MediaController
{
    private string $uploadDir;
    private string $publicBase;

    public function __construct()
    {
        $this->uploadDir = __DIR__ . '/../../../public/uploads/blog'; // adjust to your structure
        $this->publicBase = '/uploads/blog';
    }

    public function upload(Request $request, Response $response): Response
    {
        $file = $request->file('image'); // depends on your Request implementation
        if (!$file) {
            return $response->withStatus(422)->withBody('No file uploaded');
        }

        // Basic validations: size/type
        $allowed = ['image/jpeg','image/png','image/gif','image/webp'];
        if (!in_array($file->getClientMimeType(), $allowed, true)) {
            return $response->withStatus(415)->withBody('Unsupported media type');
        }

        if ($file->getSize() > 5 * 1024 * 1024) { // 5MB
            return $response->withStatus(413)->withBody('File too large');
        }

        // Ensure directory exists
        if (!is_dir($this->uploadDir)) {
            mkdir($this->uploadDir, 0775, true);
        }

        // Build a safe filename: yyyy/mm/slug-rand.ext
        $ext = pathinfo($file->getClientOriginalName(), PATHINFO_EXTENSION);
        $datePath = date('Y/m');
        $dir = $this->uploadDir . DIRECTORY_SEPARATOR . $datePath;
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        $base = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
        $slug = strtolower(preg_replace('~[^a-z0-9]+~i', '-', $base));
        $name = $slug . '-' . substr(bin2hex(random_bytes(4)), 0, 8) . '.' . $ext;

        $targetPath = $dir . DIRECTORY_SEPARATOR . $name;
        $file->moveTo($targetPath); // adjust to your upload handling API

        $url = rtrim($this->publicBase, '/') . '/' . $datePath . '/' . $name;

        // Return JSON with the URL so the editor can insert it
        $payload = json_encode(['url' => $url], JSON_UNESCAPED_SLASHES);
        return $response->withHeader('Content-Type', 'application/json')->withBody($payload);
    }
}
```

Notes:
- Adjust file APIs to your Request implementation. Many frameworks expose getClientOriginalName(), getClientMimeType(), moveTo(), etc.
- The year/month subfolders keep things tidy and scalable.

## 3) Adding an image from the post editor

If you keep a simple HTML form (textarea), you can add a small upload form that POSTs to `/blog/media/upload` and inserts the returned URL into the textarea.

Example view snippet below your Body textarea:

```html
<!-- In your base layout <head>, expose the CSRF token for XHR: -->
<?= function_exists('csrfMeta') ? csrfMeta() : '' ?>

<form id="imgUpload" action="<?= route('blog.media.upload') ?>" method="post" enctype="multipart/form-data">
  <?= function_exists('csrfField') ? csrfField() : '' ?>
  <input type="file" name="image" accept="image/*" required />
  <button type="submit">Upload</button>
  
  <!-- Optional: also include the token in a header for fetch() if your middleware checks headers -->
</form>

<script>
  const form = document.getElementById('imgUpload');
  function csrfTokenFromMeta() {
    const m = document.querySelector('meta[name="csrf-token"]');
    return m ? m.getAttribute('content') : null;
  }
  form.addEventListener('submit', async (e) => {
    e.preventDefault();
    const fd = new FormData(form);
    const headers = {};
    const t = csrfTokenFromMeta();
    if (t) headers['X-CSRF-Token'] = t;
    const res = await fetch(form.action, { method: 'POST', body: fd, headers });
    if (!res.ok) { alert('Upload failed'); return; }
    const { url } = await res.json();
    // Insert Markdown syntax at cursor
    const ta = document.querySelector('textarea[name="content"]');
    const alt = prompt('Alt text for the image?', '');
    const md = `![${alt ?? ''}](${url})`;
    const start = ta.selectionStart;
    const end = ta.selectionEnd;
    ta.value = ta.value.slice(0, start) + md + ta.value.slice(end);
    ta.focus();
    ta.selectionStart = ta.selectionEnd = start + md.length;
  });
</script>
```

This keeps the editor experience lightweight without introducing a full Markdown widget.

## 4) Referencing images in Markdown and plain text

- Markdown: use standard syntax `![Alt text](/uploads/blog/2025/11/photo-abc123.webp)`.
- Plain text: either keep plain text strictly text (no images), or allow a subset of HTML `<img>` tags — but if you allow raw HTML, ensure you sanitize it on output.

With the PostService from Part 9 using CommonMark in `html_input: 'strip'` mode, raw HTML entered by authors will be stripped by default, which is safer. Authors should insert images using Markdown.

## 5) Optional: resizing/processing

You can integrate an image library (e.g., `intervention/image`) to resize or generate thumbnails at upload time. Store multiple sizes next to the original (e.g., `photo-abc123_800.jpg`, `photo-abc123_400.jpg`). Then expose those URLs to the client for selection or pick one automatically based on layout.

## 6) Private images (advanced)

When images must not be publicly readable (draft posts, paid content, internal docs), store them outside the public web root and serve them through a controller that enforces permissions. In a module-based app, prefer a pattern that works no matter which host application the module is installed into.

Recommended design:

- Storage root (private): `SkeletonApp/storage/modules/blog/media/` (module-scoped). On installation, ensure the directory exists and is writable.
- Public URL: none (files are not exposed directly). Access via a route like `/blog/media/{id}` or `/m/blog/{token}`.
- Permission: only Authors/Admins, or owners of the draft post, may fetch.
- Optional signed URLs: generate time-limited URLs to reduce DB lookups for frequently embedded images in preview pages.

6.1) Route and controller (streaming with checks)

Modules/Blog/routes.php:

```php
<?php
use Ishmael\Core\Routing\Router;
use Modules\Blog\Controllers\PrivateMediaController;
use Modules\Blog\Middleware\RequireAuthor;

/** @var Router $router */
$router->get('/blog/media/{id}', [PrivateMediaController::class, 'show'])
    ->name('blog.media.private.show')
    ->middleware(RequireAuthor::class); // baseline: must be an author
```

Modules/Blog/Controllers/PrivateMediaController.php:

```php
<?php

declare(strict_types=1);

namespace Modules\Blog\Controllers;

use Ishmael\Core\Http\Request;
use Ishmael\Core\Http\Response;

final class PrivateMediaController
{
    private string $diskRoot;

    public function __construct()
    {
        // App-agnostic: resolve relative to module, then fall back to app storage
        $this->diskRoot = dirname(__DIR__, 4) . '/storage/modules/blog/media';
    }

    public function show(Request $request, Response $response, string $id): Response
    {
        // Example lookup: map ID -> absolute file path stored in DB
        // In real code, fetch a Media row by $id and check ownership/permissions.
        $media = $this->findMediaRecord($id); // pseudo call
        if (!$media) {
            return $response->withStatus(404)->withBody('Not found');
        }

        $user = $request->getAttribute('user');
        if (!$this->canView($user, $media)) {
            return $response->withStatus(403)->withBody('Forbidden');
        }

        $path = $this->diskRoot . DIRECTORY_SEPARATOR . ltrim($media['path'], '\\/');
        if (!is_file($path)) {
            return $response->withStatus(404)->withBody('Not found');
        }

        $mime = $this->detectMime($path);
        $body = fopen($path, 'rb');
        return $response
            ->withHeader('Content-Type', $mime)
            ->withHeader('Cache-Control', 'private, max-age=0, no-store')
            ->withBody($body); // stream file contents
    }

    private function detectMime(string $path): string
    {
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        return match ($ext) {
            'jpg','jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif',
            'webp' => 'image/webp',
            default => 'application/octet-stream',
        };
    }
}
```

6.2) Uploading to private storage

Add another action on MediaController (or a new controller) to accept uploads into the private disk.

```php
public function uploadPrivate(Request $request, Response $response): Response
{
    $file = $request->file('image');
    if (!$file) {
        return $response->withStatus(422)->withBody('No file');
    }

    $allowed = ['image/jpeg','image/png','image/gif','image/webp'];
    if (!in_array($file->getClientMimeType(), $allowed, true)) {
        return $response->withStatus(415)->withBody('Unsupported');
    }

    $root = dirname(__DIR__, 4) . '/storage/modules/blog/media';
    $datePath = date('Y/m');
    $dir = $root . DIRECTORY_SEPARATOR . $datePath;
    if (!is_dir($dir)) {
        mkdir($dir, 0775, true);
    }

    $ext = pathinfo($file->getClientOriginalName(), PATHINFO_EXTENSION);
    $base = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
    $slug = strtolower(preg_replace('~[^a-z0-9]+~i', '-', $base));
    $name = $slug . '-' . substr(bin2hex(random_bytes(4)), 0, 8) . '.' . $ext;

    $targetPath = $dir . DIRECTORY_SEPARATOR . $name;
    $file->moveTo($targetPath);

    // Persist a media record with relative path "Y/m/name.ext" and owner/post ref.
    // $mediaId = $repo->create([...]);

    // Return a module route URL for embedding in Markdown (authors only)
    $url = route('blog.media.private.show', ['id' => /*$mediaId*/ '123']); // example
    $payload = json_encode(['url' => $url], JSON_UNESCAPED_SLASHES);
    return $response->withHeader('Content-Type', 'application/json')->withBody($payload);
}
```

6.3) Signed URLs (optional)

For preview pages or shared drafts, generate a short‑lived signed URL to avoid running a full permission check for every image request:

```php
// Generate token
data: media_id + expires + HMAC(secret)

// Example helper (pseudo):
$expires = time() + 300; // 5 min
$token = hash_hmac('sha256', $mediaId . '|' . $expires, $_ENV['APP_KEY']);
$url = route('blog.media.signed', ['id' => $mediaId, 'e' => $expires, 't' => $token]);
```

Route and controller verify the HMAC and expiry, then stream the same file. Never expose the real file path.

6.4) Why module‑scoped paths?

- Portability: the Blog module can be dropped into another app without path changes.
- Encapsulation: avoids collisions between modules.
- Security defaults: everything under `storage/modules/blog/media` is private until a controller serves it.

See Part 11 for a dedicated walkthrough with full snippets, DB shape, and author workflow.

## 7) Security checklist

- Validate file type and size on upload.
- Never trust the original filename; generate your own.
- Store under a dedicated directory with limited permissions.
- If you allow HTML in posts, sanitize on input or output.
- Prefer absolute or site‑relative URLs in Markdown so links remain valid after moves.

## Related reading
- Part 9: [Authors, Editing Workflow, and Content Format](./blog-part-9-content-format-and-editing.md)
- Part 4: [Views and Layout](./blog-part-4-views-and-layout.md)
- Part 5: [Routing and Middleware](./blog-part-5-routing-and-middleware.md)
- Next: [Part 11 — Private Media in Modules](./blog-part-11-private-media-in-modules.md)
