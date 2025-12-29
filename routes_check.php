<?php
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
require __DIR__ . "/vendor/autoload.php"; 
use Ishmael\McpServer\Project\ProjectContext; 
use Ishmael\McpServer\Support\RouteCollector; 
use Ishmael\McpServer\Project\PathSandbox; 
use Ishmael\McpServer\Support\ErrorEnvelope;

$root = getcwd(); 
$sandbox = new PathSandbox($root); 
$ctx = new ProjectContext($root, $sandbox, []); 
$collector = new RouteCollector($ctx); 
try { 
    $routes = $collector->collect(); 
    $result = [
        'routes' => $routes,
        'total' => count($routes),
        'limit' => 2000,
        'offset' => 0,
        'truncated' => false
    ];
    $envelope = ErrorEnvelope::success(42, $result, ['durationMs' => 10]);
    echo json_encode($envelope, JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT), "\n"; 
} catch (Throwable $e) { 
    $envelope = ErrorEnvelope::error(42, 500, "Route enumeration failed: " . $e->getMessage(), null, ['durationMs' => 10]);
    echo json_encode($envelope, JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT), "\n"; 
}
