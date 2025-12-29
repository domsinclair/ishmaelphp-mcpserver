# Blog Tutorial — Part 7: Testing and Transactions

In Part 7 you will:
- Write a controller test for the PostController::store action.
- Wrap tests in a database transaction to isolate changes.

Prerequisites:
- Parts 1–6 completed. The store action creates a post and redirects to show.
- phpunit.xml.dist configured in the repo.

## 1) Test setup: a DB transaction wrapper

Create a base TestCase that begins a transaction before each test and rolls back after.
Adjust namespaces/paths to your project.

```php
<?php

declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\TestCase as BaseTestCase;
use Ishmael\Core\Database\DB;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        DB::beginTransaction();
    }

    protected function tearDown(): void
    {
        DB::rollBack();
        parent::tearDown();
    }
}
```

If your DB adapter uses a different API, adapt the transaction calls accordingly.

## 2) Controller test for store

Create `tests/Modules/Blog/PostControllerStoreTest.php`:

```php
<?php

declare(strict_types=1);

namespace Tests\Modules\Blog;

use Tests\TestCase;
use Ishmael\Core\Http\Request;
use Ishmael\Core\Http\Response;
use Modules\Blog\Controllers\PostController;
use Modules\Blog\Services\PostService;

final class PostControllerStoreTest extends TestCase
{
    public function testStoreCreatesPostAndRedirects(): void
    {
        // Arrange
        $service = new PostService();
        $controller = new PostController($service);
        $request = (new Request())->withParsedBody([
            'title' => 'Test Post',
            'body' => 'This is a test.'
        ]);
        $response = new Response();

        // Act
        $result = $controller->store($request, $response);

        // Assert
        $this->assertSame(302, $result->getStatusCode());
        $location = $result->getHeaderLine('Location');
        $this->assertMatchesRegularExpression('#^/blog/posts/\\d+$#', $location);
    }
}
```

Depending on your actual Request/Response API, you may need to adapt creating request objects and fetching headers.

## 3) Run the tests

```bash
./vendor/bin/phpunit -c phpunit.xml.dist
```

Tests should pass, and any database changes should be rolled back automatically between tests.

## Exact classes and methods referenced
- Base test: `Tests\TestCase::{setUp,tearDown}`
- DB transactions: `Ishmael\Core\Database\DB::{beginTransaction,rollBack}`
- Controller under test: `Modules\Blog\Controllers\PostController::store`
- Service used: `Modules\Blog\Services\PostService`
- HTTP: `Ishmael\Core\Http\Request`, `Ishmael\Core\Http\Response`

## Related reading
- Guide: [Transactions](../guide/transactions.md)
- Guide: [Controllers & Views](../guide/controllers-and-views.md)

## What you learned
- How to structure a simple controller test for a write action.
- How to isolate tests using a database transaction wrapper.
