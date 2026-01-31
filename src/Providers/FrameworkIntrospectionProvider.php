<?php

declare(strict_types=1);

namespace Ishmael\McpServer\Providers;

use Ishmael\McpServer\Contracts\ResourceProvider;
use Ishmael\McpServer\Project\ProjectContext;

/**
 * Provides functional metadata about the Ishmael framework core.
 * Harvests Middleware, Attributes, Helpers, Container bindings, and Constraints.
 */
final class FrameworkIntrospectionProvider implements ResourceProvider
{
    private ProjectContext $context;

    public function __construct(ProjectContext $context)
    {
        $this->context = $context;
    }

    public function listResources(): array
    {
        return [
            [
                'uri' => 'ish://framework/introspection',
                'name' => 'Ishmael Framework Introspection',
                'description' => 'A semantic index of framework functional DNA: Middleware, Helpers, Attributes, and Core Classes.',
                'mimeType' => 'application/json',
            ]
        ];
    }

    public function readResource(string $uri): ?string
    {
        if ($uri !== 'ish://framework/introspection') {
            return null;
        }

        $data = [
            'middleware' => $this->getMiddlewareStack(),
            'helpers' => $this->getGlobalHelpers(),
            'attributes' => $this->getCoreAttributes(),
            'container_bindings' => $this->getContainerBindings(),
            'route_constraints' => $this->getRouteConstraints(),
            'core_contracts' => $this->getCoreContracts(),
        ];

        return json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }

    private function getMiddlewareStack(): array
    {
        return [
            'description' => 'Default execution order for the HTTP global middleware stack.',
            'stack' => [
                ['class' => 'Ishmael\Core\Http\Middleware\StartSessionMiddleware', 'purpose' => 'Initializes session management.'],
                ['class' => 'Ishmael\Core\Http\Middleware\VerifyCsrfToken', 'purpose' => 'Enforces CSRF protection for state-changing requests.'],
                ['class' => 'Ishmael\Core\Http\Middleware\SecurityHeaders', 'purpose' => 'Applies standard security headers (CSP, XFO, etc.).'],
                ['class' => 'Ishmael\Core\Http\Middleware\RememberMeMiddleware', 'purpose' => 'Wires services and cookie handling for persistent login.'],
                ['class' => 'Ishmael\Core\Http\Middleware\Authenticate', 'purpose' => 'Protects routes by requiring authentication.'],
                ['class' => 'Ishmael\Core\Http\Middleware\Guest', 'purpose' => 'Gates guest-only routes (e.g., login/register).'],
                ['class' => 'Ishmael\Core\Http\Middleware\RequestIdMiddleware', 'purpose' => 'Adds a unique ID to each request for tracing.'],
                ['class' => 'Ishmael\Core\Http\Middleware\CorsMiddleware', 'purpose' => 'Handles Cross-Origin Resource Sharing.'],
                ['class' => 'Ishmael\Core\Http\Middleware\JsonBodyParserMiddleware', 'purpose' => 'Parses JSON request bodies.'],
                ['class' => 'Ishmael\Core\Http\Middleware\ThrottleMiddleware', 'purpose' => 'Rate limits requests.'],
            ]
        ];
    }

    private function getGlobalHelpers(): array
    {
        return [
            'description' => 'Idiomatic shorthand functions for common framework operations.',
            'functions' => [
                'base_path' => 'Resolve path relative to project root.',
                'storage_path' => 'Resolve path relative to storage directory.',
                'public_path' => 'Resolve path relative to public directory.',
                'config' => 'Retrieve configuration values.',
                'env' => 'Retrieve environment variables.',
                'app' => 'Service locator for the DI container.',
                'route' => 'Generate a URL for a named route.',
                'session' => 'Get/set session values.',
                'flash' => 'Get/set flash messages.',
                'auth' => 'Resolve the Authentication Manager.',
                'gate' => 'Resolve the Authorization Gate.',
                'authorize' => 'Authorize an ability, throwing on denial.',
                'validate' => 'Validate request input.',
                'cache' => 'Resolve the Cache Manager.',
                'e' => 'HTML-escape a value.',
                'csrfToken' => 'Get the current CSRF token.',
                'csrfField' => 'Render a hidden CSRF input field.',
            ]
        ];
    }

    private function getCoreAttributes(): array
    {
        return [
            'description' => 'PHP 8 Attributes used to trigger framework behaviors.',
            'attributes' => [
                'Ishmael\Core\Attributes\Auditable' => 'Enables automatic tracking of created_by/updated_by on Models.',
            ]
        ];
    }

    private function getContainerBindings(): array
    {
        return [
            'description' => 'Standard interfaces and their common aliases in the DI container.',
            'bindings' => [
                'session' => 'Ishmael\Core\Session\SessionManager',
                'auth' => 'Ishmael\Core\Auth\AuthManager',
                'gate' => 'Ishmael\Core\Authz\Gate',
                'hasher' => 'Ishmael\Core\Auth\HasherInterface',
                'user_provider' => 'Ishmael\Core\Auth\UserProviderInterface',
                'config_repo' => 'Array of merged configuration.',
            ]
        ];
    }

    private function getRouteConstraints(): array
    {
        return [
            'description' => 'Named regex constraints for route parameters.',
            'constraints' => [
                'int' => 'Digits only, cast to integer.',
                'numeric' => 'Integer or decimal, cast to float.',
                'bool' => 'true/false/1/0/yes/no, cast to boolean.',
                'slug' => 'Alphanumeric and dashes, URL-decoded.',
                'alpha' => 'Letters only, URL-decoded.',
                'alnum' => 'Letters and digits, URL-decoded.',
                'uuid' => 'Canonical UUID v1-5 format.',
            ]
        ];
    }

    private function getCoreContracts(): array
    {
        return [
            'description' => 'Key framework classes and interfaces for type-hinting.',
            'classes' => [
                'Ishmael\Core\Http\Request' => 'The current HTTP request.',
                'Ishmael\Core\Http\Response' => 'The HTTP response to be returned.',
                'Ishmael\Core\Http\UploadedFile' => 'Abstraction for uploaded files.',
                'Ishmael\Core\Support\StorageInterface' => 'Interface for storage-agnostic file operations.',
                'Ishmael\Core\Model' => 'Base class for all domain models.',
                'Ishmael\Core\Controller' => 'Base class for all HTTP controllers.',
                'Ishmael\Core\Capability' => 'Service for enforcing community/premium features.',
            ]
        ];
    }
}
