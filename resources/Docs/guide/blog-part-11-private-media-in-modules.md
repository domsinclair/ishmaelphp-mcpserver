# Blog Tutorial — Part 11: Private Media in Modules (Storage, Upload, and Retrieval)

In this part we go deep on private media for module authors. You will design a storage layout that works regardless of which host application your module is installed into, implement secure streaming, and provide an author workflow for uploading and embedding private images in posts.

What you’ll learn:
- Module‑scoped private storage layout and why it matters.
- Database shape to track private media and ownership.
- Upload endpoint for private media (authors only).
- Secure retrieval via controller streaming with permission checks.
- Optional: signed, short‑lived URLs for previews and sharing.
- Editor integration: insert a private image link into Markdown.

Prerequisites:
- Completed Part 10 (public uploads and basics).
- Basic understanding of your app’s auth/user representation.

## 1) Storage layout (module‑scoped, private)

Goal: keep private media outside the public web root, in a path that is predictable and does not change if the module is moved to another app.

- Root: `storage/modules/blog/media/`
- Structure: `storage/modules/blog/media/YYYY/MM/filename.ext`
- Ownership: If media relates to a Post, store a `post_id` reference; otherwise keep a `user_id` as owner.

Create the directory on install or at first upload.

## 2) Database schema

A minimal table to track private media:

```
blog_media
- id (int, PK)
- path (string)           // relative path like "2025/11/photo-abc123.webp"
- mime (string)
- size (int)
- post_id (int, nullable) // optional link to posts
- user_id (int)           // uploader/owner
- visibility (string)     // 'private' | 'draft' | 'internal'
- created_at, updated_at
```

You may add `checksum` (SHA-256) to dedupe, and `filename_original` for audit.

## 3) Upload endpoint (private)

Route (Modules/Blog/routes.php):

```php
<?php
use Ishmael\Core\Routing\Router;
use Modules\Blog\Controllers\MediaController;
use Modules\Blog\Middleware\RequireAuthor;

/** @var Router $router */
$router->post('/blog/media/upload-private', [MediaController::class, 'uploadPrivate'])
    ->name('blog.media.upload_private')
    ->middleware(RequireAuthor::class);
```

Controller method (Modules/Blog/Controllers/MediaController.php):

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

    // TODO: persist to blog_media and return its id
    $mediaId = 123; // placeholder

    $url = route('blog.media.private.show', ['id' => $mediaId]);
    $payload = json_encode(['url' => $url], JSON_UNESCAPED_SLASHES);
    return $response->withHeader('Content-Type', 'application/json')->withBody($payload);
}
```

## 4) Retrieval endpoint (streaming)

Route (Modules/Blog/routes.php):

```php
$router->get('/blog/media/{id}', [PrivateMediaController::class, 'show'])
    ->name('blog.media.private.show')
    ->middleware(RequireAuthor::class);
```

Controller (Modules/Blog/Controllers/PrivateMediaController.php):

```php
final class PrivateMediaController
{
    private string $diskRoot;

    public function __construct()
    {
        $this->diskRoot = dirname(__DIR__, 4) . '/storage/modules/blog/media';
    }

    public function show(Request $request, Response $response, string $id): Response
    {
        $user = $request->getAttribute('user');

        // TODO: load from blog_media by $id
        $media = [
            'path' => '2025/11/photo-abc123.webp',
            'user_id' => 1,
            'post_id' => 42,
            'visibility' => 'private',
        ];

        if (!$this->canView($user, $media)) {
            return $response->withStatus(403)->withBody('Forbidden');
        }

        $path = $this->diskRoot . DIRECTORY_SEPARATOR . $media['path'];
        if (!is_file($path)) {
            return $response->withStatus(404)->withBody('Not found');
        }

        $mime = $this->detectMime($path);
        $stream = fopen($path, 'rb');
        return $response
            ->withHeader('Content-Type', $mime)
            ->withHeader('Cache-Control', 'private, max-age=0, no-store')
            ->withBody($stream);
    }
}
```

Implement `canView()` to allow:
- Admins.
- Authors of the linked post.
- The uploader (user_id).

## 5) Signed URLs (optional)

For shared previews, you can add a signed route that validates a short‑lived HMAC token, avoiding a DB permission check per image.

Route:

```php
$router->get('/blog/media/{id}/signed', [PrivateMediaController::class, 'showSigned'])
    ->name('blog.media.private.signed');
```

Controller sketch:

```php
public function showSigned(Request $req, Response $res, string $id): Response
{
    $e = (int) $req->query('e');
    $t = (string) $req->query('t');
    if ($e < time()) return $res->withStatus(403);

    $expected = hash_hmac('sha256', $id . '|' . $e, $_ENV['APP_KEY']);
    if (!hash_equals($expected, $t)) return $res->withStatus(403);

    // then stream like show()
}
```

## 6) Editor integration (Markdown)

Authors can upload to the private endpoint and insert a URL like:

```
![Diagram](<?= route('blog.media.private.show', ['id' => 123]) ?>)
```

During draft review, switch to a signed URL if sharing outside the author team:

```
![Draft Diagram](<?= route('blog.media.private.signed', ['id' => 123, 'e' => $exp, 't' => $token]) ?>)
```

## 7) Security checklist (private media)

- Validate MIME and size; reject dangerous types.
- Generate safe filenames; never trust client names.
- Keep storage under `storage/modules/<module>/media` with restrictive permissions.
- Do not expose directory listings.
- Return `Cache-Control: private` for private responses.
- Log access denials and unexpected states for auditing.

## Related reading
- Previous: [Part 10 — Images and Storage](./blog-part-10-images-and-storage.md)
- Next: [Part 12 — Logging and Debugging](./blog-part-12-logging-and-debugging.md)
