# Blog Tutorial — Part 6: Pagination and Search

In Part 6 you will:
- Add naive full‑text search (title/body LIKE) to the index action.
- Implement simple pagination and render links.
- Optional: extract a tiny pagination view helper.

Prerequisites:
- Parts 1–5 completed. Index view renders posts.

## 1) Extend PostService with search and pagination

Update `PostService::paginate()` to accept an optional `query` string and return enough metadata for pagination links:

```php
public function paginate(int $page = 1, int $perPage = 10, ?string $query = null): array
{
    $offset = ($page - 1) * $perPage;
    $builder = DB::table('posts');
    if ($query !== null && $query !== '') {
        $like = '%' . $query . '%';
        $builder = $builder->whereGroup(function ($q) use ($like): void {
            $q->where('title', 'LIKE', $like)->orWhere('body', 'LIKE', $like);
        });
    }
    $total = (int) $builder->count();
    $items = $builder->orderBy('id', 'desc')->limit($perPage)->offset($offset)->get();
    $pages = (int) max(1, (int) ceil($total / $perPage));
    return compact('items', 'total', 'page', 'perPage', 'pages');
}
```

Adjust the syntax to your query builder if different.

## 2) Update controller index action

```php
public function index(Request $request, Response $response): Response
{
    $page = (int) ($request->query('page') ?? 1);
    $query = (string) ($request->query('q') ?? '');
    $data = $this->posts->paginate($page, 10, $query);
    $data['q'] = $query;
    return $response->view('Modules/Blog/Views/posts/index.php', $data);
}
```

## 3) Update index view: search form and pagination links

In `Views/posts/index.php` add a small search form and pagination section below the list:

```php
<form method="get" class="mb-4 flex gap-2">
    <input type="text" name="q" value="<?php echo htmlspecialchars((string)($data['q'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" class="border rounded p-2 flex-1" placeholder="Search posts..." />
    <button class="px-3 py-2 bg-gray-800 text-white rounded" type="submit">Search</button>
</form>
```

At the bottom (after the <ul>):

```php
<?php if (($data['pages'] ?? 1) > 1): ?>
<nav class="mt-4 flex gap-2">
    <?php for ($i = 1; $i <= (int)$data['pages']; $i++): ?>
        <?php if ($i === (int)$data['page']): ?>
            <span class="px-3 py-2 bg-blue-600 text-white rounded"><?php echo $i; ?></span>
        <?php else: ?>
            <a class="px-3 py-2 bg-white border rounded" href="?page=<?php echo $i; ?>&q=<?php echo urlencode((string)($data['q'] ?? '')); ?>"><?php echo $i; ?></a>
        <?php endif; ?>
    <?php endfor; ?>
</nav>
<?php endif; ?>
```

## 4) Optional tiny pagination helper

You can extract the link generation to a small helper included by the view:

```php
<?php
/**
 * Render simple pagination links.
 * @param int $page
 * @param int $pages
 * @param array<string, scalar> $params
 */
function renderPagination(int $page, int $pages, array $params = []): void {
    if ($pages <= 1) { return; }
    echo '<nav class="mt-4 flex gap-2">';
    for ($i = 1; $i <= $pages; $i++) {
        $params['page'] = $i;
        $qs = http_build_query($params);
        if ($i === $page) {
            echo '<span class="px-3 py-2 bg-blue-600 text-white rounded">' . $i . '</span>';
        } else {
            echo '<a class="px-3 py-2 bg-white border rounded" href="?' . htmlspecialchars($qs) . '">' . $i . '</a>';
        }
    }
    echo '</nav>';
}
```

## Exact classes and methods referenced
- Service method: `Modules\Blog\Services\PostService::paginate(int $page, int $perPage, ?string $query = null): array`
- Controller: `Modules\Blog\Controllers\PostController::index`

## Related reading
- Guide: [Controllers & Views](./controllers-and-views.md)
- How‑to: [Generate URLs in views/controllers](../how-to/generate-urls-in-views-and-controllers.md)

## What you learned
- How to add search to your index action.
- How to implement and render simple pagination.
