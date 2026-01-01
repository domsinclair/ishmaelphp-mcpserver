<?php

declare(strict_types=1);

namespace Ishmael\McpServer\Providers;

use Ishmael\McpServer\Contracts\ResourceProvider;
use Ishmael\McpServer\Project\ProjectContext;
use Ishmael\McpServer\Support\IshCliBridge;

/**
 * Exposes project health status (ish://project/health).
 */
final class IshHealthResourceProvider implements ResourceProvider
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
                'uri' => 'ish://project/health',
                'name' => 'Ishmael Project Health',
                'description' => 'Summary of environment status, pending migrations, and module health',
                'mimeType' => 'application/json',
            ],
        ];
    }

    public function readResource(string $uri): ?string
    {
        if ($uri !== 'ish://project/health') {
            return null;
        }

        $root = $this->context->getRoot();
        if ($root === null) {
            return json_encode(['error' => 'Project root not found']);
        }

        $bridge = new IshCliBridge($this->context);
        
        // 1. Check environment
        $envResult = $bridge->execute('env:validate');
        
        // 2. Check migrations
        // Note: we don't have a direct 'migrate:status --json', but we can try to infer from output or just report result
        $migrateResult = $bridge->execute('migrate', ['pretend' => true]);

        $health = [
            'timestamp' => date('c'),
            'environment' => [
                'success' => $envResult['success'],
                'output' => $envResult['output'],
            ],
            'migrations' => [
                'pending' => $migrateResult['success'] && str_contains($migrateResult['output'], 'Nothing to migrate') === false,
                'output' => $migrateResult['output'],
            ],
            'modules' => is_dir($root . DIRECTORY_SEPARATOR . 'Modules') ? count(array_filter(glob($root . DIRECTORY_SEPARATOR . 'Modules' . DIRECTORY_SEPARATOR . '*'), 'is_dir')) : 0,
        ];

        return json_encode($health, JSON_PRETTY_PRINT);
    }
}
