<?php

declare(strict_types=1);

namespace Ishmael\McpServer\Tests\Tools;

if (!class_exists('Ishmael\Core\Router')) {
    eval('namespace Ishmael\Core { class Router { 
        public static function group(array $options, callable $callback): void {
            if (self::$active) { self::$active->group($options, $callback); }
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
use Ishmael\McpServer\Tools\RoutesListTool;
use PHPUnit\Framework\TestCase;

final class RoutesListToolTest extends TestCase
{
    private string $tempRoot;

    protected function setUp(): void
    {
        $this->tempRoot = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'ish_mcp_tool_test_' . uniqid();
        mkdir($this->tempRoot, 0777, true);
        
        $modulesPath = $this->tempRoot . DIRECTORY_SEPARATOR . 'Modules' . DIRECTORY_SEPARATOR . 'Blog';
        mkdir($modulesPath, 0777, true);
        
        $routesContent = <<<'PHP'
<?php
return function ($router): void {
    $router->get('/posts', 'PostController@index');
    $router->get('/posts/{id}', 'PostController@show');
    $router->post('/posts', 'PostController@store');
    $router->get('/about', 'PageController@about');
};
PHP;
        file_put_contents($modulesPath . DIRECTORY_SEPARATOR . 'routes.php', $routesContent);
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

    public function testExecuteReturnsAllRoutes(): void
    {
        $context = new ProjectContext($this->tempRoot, null, []);
        $tool = new RoutesListTool($context);
        $result = $tool->execute([]);

        $this->assertCount(4, $result['routes']);
        $this->assertEquals(4, $result['total']);
        $this->assertFalse($result['truncated']);
    }

    public function testExecuteFiltersRoutes(): void
    {
        $context = new ProjectContext($this->tempRoot, null, []);
        $tool = new RoutesListTool($context);
        $result = $tool->execute(['filter' => 'post']);

        $this->assertCount(3, $result['routes']);
        foreach ($result['routes'] as $route) {
            $this->assertStringContainsString('post', strtolower($route['path'] . $route['handler']));
        }
    }

    public function testExecuteAppliesPagination(): void
    {
        $context = new ProjectContext($this->tempRoot, null, []);
        $tool = new RoutesListTool($context);
        $result = $tool->execute(['limit' => 2, 'offset' => 1]);

        $this->assertCount(2, $result['routes']);
        $this->assertEquals(4, $result['total']);
        $this->assertEquals(2, $result['limit']);
        $this->assertEquals(1, $result['offset']);
        $this->assertTrue($result['truncated']);
    }

    public function testExecuteHandlesFatalErrorGracefully(): void
    {
        // Use a mock to force a failure
        $context = $this->createMock(ProjectContext::class);
        $context->method('getRoot')->willThrowException(new \RuntimeException("Forced failure"));
        
        $tool = new RoutesListTool($context);
        $result = $tool->execute([]);
        
        $this->assertArrayHasKey('error', $result);
        $this->assertEquals(500, $result['error']['code']);
        $this->assertStringContainsString('Forced failure', $result['error']['message']);
    }
}
