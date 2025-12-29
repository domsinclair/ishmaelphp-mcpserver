<?php

declare(strict_types=1);

namespace Ishmael\McpServer\Tests\Support;

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
return function ($router): void {
    $router->get('/users', 'UserController@index');
    $router->post('/users', 'UserController@store');
    
    $router->group(['prefix' => '/v1'], function ($r) {
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
}
