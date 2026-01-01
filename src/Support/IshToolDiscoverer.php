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

        $tools = [];
        foreach ($data['commands'] as $cmd) {
            $name = $cmd['name'] ?? null;
            if (!$name) continue;

            // Skip commands that have dedicated MCP tool implementations
            // Dedicated tools usually have prefix ish: and match the CLI command name
            $mcpName = 'ish:' . $name;
            
            // We'll let the registration logic handle de-duplication if needed, 
            // but here we can decide whether to create a DynamicIshTool.
            $tools[] = new DynamicIshTool($this->context, $cmd);
        }

        return $tools;
    }
}
