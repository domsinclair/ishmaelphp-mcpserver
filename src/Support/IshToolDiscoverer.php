<?php

declare(strict_types=1);

namespace Ishmael\McpServer\Support;

use Ishmael\McpServer\Project\ProjectContext;
use Ishmael\McpServer\Tools\DynamicIshTool;

final class IshToolDiscoverer
{
    private ProjectContext $context;

    public function __construct(ProjectContext $context)
    {
        $this->context = $context;
    }

    /**
     * @return \Ishmael\McpServer\Contracts\Tool[]
     */
    public function discover(): array
    {
        $root = $this->context->getRoot();
        if ($root === null) {
            return [];
        }

        $metadataPath = $root . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'cli_commands.json';
        if (!is_file($metadataPath)) {
            // Try to generate it by running an ish command that triggers metadata persistence.
            // In Ishmael, bin/ish usually persists metadata on every run.
            // We can run 'help' to trigger it.
            $bridge = new IshCliBridge($this->context);
            $bridge->execute('help');
            
            if (!is_file($metadataPath)) {
                return [];
            }
        }

        $data = json_decode(file_get_contents($metadataPath), true);
        if (!is_array($data) || !isset($data['commands'])) {
            return [];
        }

        $dedicatedCommands = [
            'make:module',
            'make:controller',
            'make:service',
            'make:migration',
            'make:resource',
            'make:view',
            'migrate',
            'env:validate',
            'project:info',
            'feature-pack:list',
            'feature-pack:create',
            'feature-pack:install',
            'feature-pack:integrate',
            'test',
            'lint',
            'log:tail',
            'container:describe',
            'routes:list',
            'find:usages',
            'docs:sync',
            'setup:run-configs',
        ];

        $tools = [];
        foreach ($data['commands'] as $cmd) {
            $name = $cmd['name'] ?? null;
            if (!$name || in_array($name, $dedicatedCommands)) {
                continue;
            }

            $tools[] = new DynamicIshTool($this->context, $cmd);
        }

        return $tools;
    }
}
