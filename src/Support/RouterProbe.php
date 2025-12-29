<?php

declare(strict_types=1);

namespace Ishmael\McpServer\Support;

use Ishmael\Core\Router;

/**
 * A lightweight probe that satisfies the Router type-hint and records routes.
 * It’s instantiated without running the parent constructor.
 */
final class RouterProbe extends Router
{
    /** @var array<int, array{method:string,path:string,handler:string}> */
    public array $collected = [];

    private string $moduleName = '';
    /** @var string[] */
    private array $groupStack = [];

    /**
     * Create an instance without calling Router’s constructor.
     */
    public static function make(string $moduleName): self
    {
        $ref = new \ReflectionClass(self::class);
        /** @var self $obj */
        $obj = $ref->newInstanceWithoutConstructor();
        $obj->moduleName = $moduleName;
        return $obj;
    }

    // ——— Router API surface we need ———
    public function get(string $path, $handler, array $middleware = []): self { return $this->add(['GET'], $path, $handler); }
    public function post(string $path, $handler, array $middleware = []): self { return $this->add(['POST'], $path, $handler); }
    public function put(string $path, $handler, array $middleware = []): self { return $this->add(['PUT'], $path, $handler); }
    public function patch(string $path, $handler, array $middleware = []): self { return $this->add(['PATCH'], $path, $handler); }
    public function delete(string $path, $handler, array $middleware = []): self { return $this->add(['DELETE'], $path, $handler); }
    public function any(string $path, $handler, array $middleware = []): self {
        return $this->add(['GET','POST','PUT','PATCH','DELETE','OPTIONS','HEAD'], $path, $handler);
    }

    /** Group support for prefixes (matches your anonymous collector behavior) */
    public static function group(array $options, callable $callback): void
    {
        // Find the active instance to delegate to
        $ref = new \ReflectionClass(Router::class);
        $prop = $ref->getProperty('active');
        $prop->setAccessible(true);
        $active = $prop->getValue();

        if ($active instanceof self) {
            $active->instanceGroup($options, $callback);
        }
    }

    /**
     * Use __call to catch instance calls to group if we can't override it easily.
     * Actually, if we define it as static, we can't easily get the instance.
     */
    public function __call(string $name, array $arguments)
    {
        if ($name === 'group' || $name === 'groupWithCsrf' || $name === 'groupWithoutCsrf') {
            return $this->instanceGroup(...$arguments);
        }
        return $this;
    }

    private function instanceGroup(array $options, callable $callback): void
    {
        $prefix = $options['prefix'] ?? '';
        $current = end($this->groupStack) ?: '';
        $newPrefix = rtrim($current, '/') . '/' . ltrim($prefix, '/');
        $this->groupStack[] = $newPrefix;
        $callback($this);
        array_pop($this->groupStack);
    }

    // Since we extend Router, we should probably override the methods properly.
    // Router.php has many static methods that forward to an 'active' instance.
    // However, the fluent closure receives an instance of Router.

    public function add(array $methods, string $path, $handler, array $middleware = []): self
    {
        $current = end($this->groupStack) ?: '';
        $fullPath = rtrim($current, '/') . '/' . ltrim($path, '/');
        foreach ($methods as $method) {
            $this->collected[] = [
                'method' => (string)$method,
                'path' => '/' . ltrim($fullPath, '/'),
                'handler' => $this->normalizeHandler($handler),
            ];
        }
        return $this;
    }

    public function name(string $name): self { return $this; }
    public function middleware(array $mw): self { return $this; }

    private function normalizeHandler($handler): string
    {
        if (is_string($handler)) {
            return $handler;
        }
        if (is_array($handler) && count($handler) >= 2) {
            $class = is_object($handler[0]) ? get_class($handler[0]) : (string)$handler[0];
            return $class . '@' . (string)$handler[1];
        }
        if ($handler instanceof \Closure) {
            return 'Closure';
        }
        return 'unknown';
    }
}
