<?php

declare(strict_types=1);

namespace Ishmael\McpServer\Support;

use Ishmael\McpServer\Project\ProjectContext;

/**
 * Verifies if route handlers (Controller@method) exist and are accessible.
 */
final class RouteIntegrityChecker
{
    private ProjectContext $context;

    public function __construct(ProjectContext $context)
    {
        $this->context = $context;
    }

    /**
     * @param array{method: string, path: string, handler: string, module: string} $route
     * @return array{valid: bool, error: ?string}
     */
    public function check(array $route): array
    {
        $handler = $route['handler'];
        $module = $route['module'];

        if ($handler === 'Closure') {
            return ['valid' => true, 'error' => null];
        }

        if ($handler === 'unknown') {
            return ['valid' => false, 'error' => 'Unknown handler format'];
        }

        // Handle Controller@method
        if (str_contains($handler, '@')) {
            [$controller, $action] = explode('@', $handler, 2);
            return $this->verifyControllerAction($module, $controller, $action);
        }

        // Handle FQCN
        if (class_exists($handler)) {
            if (method_exists($handler, '__invoke')) {
                return ['valid' => true, 'error' => null];
            }
            return ['valid' => false, 'error' => "Class {$handler} exists but has no __invoke method"];
        }

        return ['valid' => false, 'error' => "Invalid handler format: {$handler}"];
    }

    private function verifyControllerAction(string $module, string $controller, string $action): array
    {
        // Add Controller suffix if missing, unless it looks like FQCN
        if (substr($controller, -10) !== 'Controller' && !str_contains($controller, '\\')) {
            $controller .= 'Controller';
        }

        // Build FQCN based on Ishmael conventions
        if ($module === 'App') {
            $class = "App\\Controllers\\{$controller}";
        } else {
            $class = "Modules\\{$module}\\Controllers\\{$controller}";
        }

        // Check if class exists
        if (!class_exists($class)) {
            return ['valid' => false, 'error' => "Controller class {$class} not found"];
        }

        // Check if method exists
        if (!method_exists($class, $action)) {
            return ['valid' => false, 'error' => "Action {$action} not found on {$class}"];
        }

        // Check if method is public
        try {
            $ref = new \ReflectionMethod($class, $action);
            if (!$ref->isPublic()) {
                return ['valid' => false, 'error' => "Action {$action} on {$class} is not public"];
            }
        } catch (\ReflectionException $e) {
            return ['valid' => false, 'error' => "Reflection error for {$class}::{$action}: " . $e->getMessage()];
        }

        return ['valid' => true, 'error' => null];
    }
}
