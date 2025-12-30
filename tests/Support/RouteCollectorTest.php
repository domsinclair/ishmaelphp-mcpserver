<?php

declare(strict_types=1);

namespace Ishmael\McpServer\Tests\Support;

if (!class_exists('Ishmael\Core\Router')) {
    // Actually, we need the namespace to exist.
    eval('namespace Ishmael\Core { class Router { 
        public static function group(array $options, callable $callback): void {
            if (self::$active) {
                self::$active->group($options, $callback);
            }
        }
        public static function groupWithCsrf(array $options, callable $callback): void {
            if (self::$active) {
                self::$active->groupWithCsrf($options, $callback);
            }
        }
        public static function groupWithoutCsrf(array $options, callable $callback): void {
            if (self::$active) {
                self::$active->groupWithoutCsrf($options, $callback);
            }
        }
        private static ?Router $active = null;
        public static function setActive(Router $r) { self::$active = $r; }
        public function get(string $path, $handler, array $middleware = []): self { return $this; }
        public function post(string $path, $handler, array $middleware = []): self { return $this; }
        public function put(string $path, $handler, array $middleware = []): self { return $this; }
        public function patch(string $path, $handler, array $middleware = []): self { return $this; }
        public function delete(string $path, $handler, array $middleware = []): self { return $this; }
        public function any(string $path, $handler, array $middleware = []): self { return $this; }
        public function name(string $name): self { return $this; }
        public function middleware(array $mw): self { return $this; }
        public function add(array $methods, string $path, $handler, array $middleware = []): self { return $this; }
        public function withoutCsrf(): self { return $this; }
        public function withCsrf(): self { return $this; }
    } }');
}

use Ishmael\McpServer\Project\ProjectContext;
use Ishmael\McpServer\Support\RouteCollector;
use PHPUnit\Framework\TestCase;

final class RouteCollectorTest extends TestCase
{
    private string $tempRoot;

    protected function setUp(): void
    {
        $this->tempRoot = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'ish_mcp_test_' . uniqid();
        mkdir($this->tempRoot, 0777, true);
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->tempRoot);
    }

    private function removeDirectory(string $dir): void
    {
        if (!is_dir($dir)) return;
        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            (is_dir("$dir/$file")) ? $this->removeDirectory("$dir/$file") : unlink("$dir/$file");
        }
        rmdir($dir);
    }

    public function testCollectsLegacyArrayRoutes(): void
    {
        $modulesPath = $this->tempRoot . DIRECTORY_SEPARATOR . 'Modules' . DIRECTORY_SEPARATOR . 'Blog';
        mkdir($modulesPath, 0777, true);
        
        $routesContent = <<<'PHP'
<?php
return [
    '^posts$' => 'PostController@index',
    '^posts/(\d+)$' => [
        'handler' => 'PostController@show',
        'name' => 'post.show'
    ],
];
PHP;
        file_put_contents($modulesPath . DIRECTORY_SEPARATOR . 'routes.php', $routesContent);

        $context = new ProjectContext($this->tempRoot, null, []);
        $collector = new RouteCollector($context);
        $routes = $collector->collect();

        $this->assertCount(2, $routes);
        $this->assertEquals('ANY', $routes[0]['method']);
        $this->assertEquals('/^posts$', $routes[0]['path']);
        $this->assertEquals('PostController@index', $routes[0]['handler']);
    }

    public function testCollectsFluentRoutes(): void
    {
        $modulesPath = $this->tempRoot . DIRECTORY_SEPARATOR . 'Modules' . DIRECTORY_SEPARATOR . 'Api';
        mkdir($modulesPath, 0777, true);

        $routesContent = <<<'PHP'
<?php
use Ishmael\Core\Router;

return function (Router $router): void {
    $router->get('/users', 'UserController@index');
    $router->post('/users', 'UserController@store');
    
    $router->group(['prefix' => '/v1'], function (Router $r) {
        $r->get('/status', 'StatusController@check');
    });
};
PHP;
        file_put_contents($modulesPath . DIRECTORY_SEPARATOR . 'routes.php', $routesContent);

        $context = new ProjectContext($this->tempRoot, null, []);
        $collector = new RouteCollector($context);
        $routes = $collector->collect();

        // GET /users, POST /users, GET /v1/status
        $this->assertCount(3, $routes);
        
        $this->assertEquals('GET', $routes[0]['method']);
        $this->assertEquals('/users', $routes[0]['path']);
        
        $this->assertEquals('POST', $routes[1]['method']);
        
        $this->assertEquals('GET', $routes[2]['method']);
        $this->assertEquals('/v1/status', $routes[2]['path']);
    }

    public function testCollectsNestedGroups(): void
    {
        $modulesPath = $this->tempRoot . DIRECTORY_SEPARATOR . 'Modules' . DIRECTORY_SEPARATOR . 'Nested';
        mkdir($modulesPath, 0777, true);

        $routesContent = <<<'PHP'
<?php
use Ishmael\Core\Router;

return function (Router $router): void {
    $router->group(['prefix' => '/admin'], function (Router $r) {
        $r->get('/dashboard', 'AdminController@index');
        $r->group(['prefix' => '/users'], function (Router $r2) {
            $r2->get('/', 'UserController@index');
            $r2->get('/{id}', 'UserController@show');
        });
    });
};
PHP;
        file_put_contents($modulesPath . DIRECTORY_SEPARATOR . 'routes.php', $routesContent);

        $context = new ProjectContext($this->tempRoot, null, []);
        $collector = new RouteCollector($context);
        $routes = $collector->collect();

        $this->assertCount(3, $routes);
        $this->assertEquals('/admin/dashboard', $routes[0]['path']);
        $this->assertEquals('/admin/users/', $routes[1]['path']);
        $this->assertEquals('/admin/users/{id}', $routes[2]['path']);
    }

    public function testResilienceToFailingRoutesFile(): void
    {
        // One good module
        $goodPath = $this->tempRoot . DIRECTORY_SEPARATOR . 'Modules' . DIRECTORY_SEPARATOR . 'Good';
        mkdir($goodPath, 0777, true);
        file_put_contents($goodPath . DIRECTORY_SEPARATOR . 'routes.php', '<?php return ["good" => "GoodController@index"];');

        // One bad module that throws
        $badPath = $this->tempRoot . DIRECTORY_SEPARATOR . 'Modules' . DIRECTORY_SEPARATOR . 'Bad';
        mkdir($badPath, 0777, true);
        file_put_contents($badPath . DIRECTORY_SEPARATOR . 'routes.php', '<?php throw new \Exception("Boom");');

        $context = new ProjectContext($this->tempRoot, null, []);
        $collector = new RouteCollector($context);
        
        // Should not throw exception
        $routes = $collector->collect();

        // Should still contain routes from the good module
        $this->assertCount(1, $routes);
        $this->assertEquals('/good', $routes[0]['path']);
    }

    public function testResilienceToFailingClosure(): void
    {
        // One good module
        $goodPath = $this->tempRoot . DIRECTORY_SEPARATOR . 'Modules' . DIRECTORY_SEPARATOR . 'Good';
        mkdir($goodPath, 0777, true);
        file_put_contents($goodPath . DIRECTORY_SEPARATOR . 'routes.php', '<?php return ["good" => "GoodController@index"];');

        // One bad module that has a closure that throws
        $badPath = $this->tempRoot . DIRECTORY_SEPARATOR . 'Modules' . DIRECTORY_SEPARATOR . 'BadClosure';
        mkdir($badPath, 0777, true);
        file_put_contents($badPath . DIRECTORY_SEPARATOR . 'routes.php', '<?php return function($r) { throw new \Exception("Closure Boom"); };');

        $context = new ProjectContext($this->tempRoot, null, []);
        $collector = new RouteCollector($context);

        // Should not throw exception
        $routes = $collector->collect();

        // Should still contain routes from the good module
        $this->assertCount(1, $routes);
        $this->assertEquals('/good', $routes[0]['path']);
    }
}
