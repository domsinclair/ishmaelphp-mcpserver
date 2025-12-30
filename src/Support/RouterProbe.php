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
    public static array $collected = [];

    /** @var string[] */
    private static array $groupStack = [];

    /**
     * Start recording for a module.
     */
    public static function begin(string $moduleName): void
    {
        self::$collected = [];
        self::$groupStack = [];
        if (method_exists(Router::class, 'setActive')) {
            Router::setActive(self::instance());
        }
    }

    /**
     * Internal singleton-ish instance to handle both static and instance calls.
     */
    private static function instance(): self
    {
        static $inst = null;
        if (!$inst) {
            $ref = new \ReflectionClass(self::class);
            $inst = $ref->newInstanceWithoutConstructor();
        }
        return $inst;
    }

    // ——— Router API surface (must match signatures in Ishmael\Core\Router) ———

    /** @return self */
    public static function get(string $pattern, $handler, array $middleware = []): Router
    {
        return self::instance()->add(['GET'], $pattern, $handler, $middleware);
    }

    /** @return self */
    public static function post(string $pattern, $handler, array $middleware = []): Router
    {
        return self::instance()->add(['POST'], $pattern, $handler, $middleware);
    }

    /** @return self */
    public static function put(string $pattern, $handler, array $middleware = []): Router
    {
        return self::instance()->add(['PUT'], $pattern, $handler, $middleware);
    }

    /** @return self */
    public static function patch(string $pattern, $handler, array $middleware = []): Router
    {
        return self::instance()->add(['PATCH'], $pattern, $handler, $middleware);
    }

    /** @return self */
    public static function delete(string $pattern, $handler, array $middleware = []): Router
    {
        return self::instance()->add(['DELETE'], $pattern, $handler, $middleware);
    }

    /** @return self */
    public static function any(string $pattern, $handler, array $middleware = []): Router
    {
        return self::instance()->add(['GET','POST','PUT','PATCH','DELETE','OPTIONS','HEAD'], $pattern, $handler, $middleware);
    }

    /** Group support for prefixes (matches Ishmael collector behavior) */
    public static function group(array $options, callable $callback): void
    {
        self::instance()->instanceGroup($options, $callback);
    }

    public static function groupWithCsrf(array $options, callable $callback): void
    {
        self::group($options, $callback);
    }

    public static function groupWithoutCsrf(array $options, callable $callback): void
    {
        self::group($options, $callback);
    }

    /**
     * Use __call to catch instance calls to group/middleware if they exist in base Router.
     */
    public function __call(string $name, array $arguments)
    {
        if (in_array($name, ['group', 'groupWithCsrf', 'groupWithoutCsrf'], true)) {
            return $this->instanceGroup(...$arguments);
        }
        return $this;
    }

    private function instanceGroup(array $options, callable $callback): void
    {
        $prefix = $options['prefix'] ?? '';
        $current = end(self::$groupStack) ?: '';
        $newPrefix = rtrim($current, '/') . '/' . ltrim($prefix, '/');
        self::$groupStack[] = $newPrefix;
        $callback($this);
        array_pop(self::$groupStack);
    }

    /**
     * Core recording method.
     */
    public function add(array $methods, string $path, $handler, array $middleware = []): self
    {
        $current = end(self::$groupStack) ?: '';
        $fullPath = rtrim($current, '/') . '/' . ltrim($path, '/');
        foreach ($methods as $method) {
            self::$collected[] = [
                'method' => (string)$method,
                'path' => '/' . ltrim($fullPath, '/'),
                'handler' => $this->normalizeHandler($handler),
            ];
        }
        return $this;
    }

    public function name(string $name): self { return $this; }
    public function middleware(array $mw): self { return $this; }
    public function withoutCsrf(): self { return $this; }
    public function withCsrf(): self { return $this; }

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
