<?php

declare(strict_types=1);

namespace Ishmael\McpServer\Tests\Tools;

use Ishmael\McpServer\Project\ProjectContext;
use Ishmael\McpServer\Support\RouteIntegrityChecker;
use PHPUnit\Framework\TestCase;

final class RouteIntegrityTest extends TestCase
{
    private string $tempRoot;

    protected function setUp(): void
    {
        $this->tempRoot = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'ish_mcp_integrity_test_' . uniqid();
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

    public function testCheckIdentifiesValidControllerAction(): void
    {
        // Define a mock controller class in global namespace for simplicity in test
        $className = 'Modules\Blog\Controllers\PostController';
        if (!class_exists($className)) {
            eval('namespace Modules\Blog\Controllers { class PostController { public function index() {} protected function secret() {} } }');
        }

        $context = new ProjectContext($this->tempRoot, null, []);
        $checker = new RouteIntegrityChecker($context);

        $route = [
            'method' => 'GET',
            'path' => '/posts',
            'handler' => 'PostController@index',
            'module' => 'Blog'
        ];

        $result = $checker->check($route);
        $this->assertTrue($result['valid']);
        $this->assertNull($result['error']);
    }

    public function testCheckIdentifiesMissingController(): void
    {
        $context = new ProjectContext($this->tempRoot, null, []);
        $checker = new RouteIntegrityChecker($context);

        $route = [
            'method' => 'GET',
            'path' => '/missing',
            'handler' => 'MissingController@index',
            'module' => 'Ghost'
        ];

        $result = $checker->check($route);
        $this->assertFalse($result['valid']);
        $this->assertStringContainsString('Controller class Modules\Ghost\Controllers\MissingController not found', $result['error']);
    }

    public function testCheckIdentifiesMissingAction(): void
    {
        $className = 'Modules\Blog\Controllers\ActionTestController';
        if (!class_exists($className)) {
            eval('namespace Modules\Blog\Controllers { class ActionTestController { public function index() {} } }');
        }

        $context = new ProjectContext($this->tempRoot, null, []);
        $checker = new RouteIntegrityChecker($context);

        $route = [
            'method' => 'GET',
            'path' => '/missing-action',
            'handler' => 'ActionTestController@missing',
            'module' => 'Blog'
        ];

        $result = $checker->check($route);
        $this->assertFalse($result['valid']);
        $this->assertStringContainsString('Action missing not found on Modules\Blog\Controllers\ActionTestController', $result['error']);
    }

    public function testCheckIdentifiesNonPublicAction(): void
    {
        $className = 'Modules\Blog\Controllers\VisibilityController';
        if (!class_exists($className)) {
            eval('namespace Modules\Blog\Controllers { class VisibilityController { protected function privateAction() {} } }');
        }

        $context = new ProjectContext($this->tempRoot, null, []);
        $checker = new RouteIntegrityChecker($context);

        $route = [
            'method' => 'GET',
            'path' => '/private',
            'handler' => 'VisibilityController@privateAction',
            'module' => 'Blog'
        ];

        $result = $checker->check($route);
        $this->assertFalse($result['valid']);
        $this->assertStringContainsString('Action privateAction on Modules\Blog\Controllers\VisibilityController is not public', $result['error']);
    }

    public function testCheckHandlesClosures(): void
    {
        $context = new ProjectContext($this->tempRoot, null, []);
        $checker = new RouteIntegrityChecker($context);

        $route = [
            'method' => 'GET',
            'path' => '/closure',
            'handler' => 'Closure',
            'module' => 'Blog'
        ];

        $result = $checker->check($route);
        $this->assertTrue($result['valid']);
    }
}
