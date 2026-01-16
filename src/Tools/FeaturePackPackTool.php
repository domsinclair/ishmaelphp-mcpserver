<?php

declare(strict_types=1);

namespace Ishmael\McpServer\Tools;

use Ishmael\McpServer\Contracts\Tool;
use Ishmael\McpServer\Project\ProjectContext;
use Ishmael\McpServer\Support\IshCliBridge;

/**
 * Packages an existing module into a feature pack using ish feature:pack.
 */
final class FeaturePackPackTool implements Tool
{
    private ProjectContext $context;
    private IshCliBridge $cli;

    public function __construct(ProjectContext $context)
    {
        $this->context = $context;
        $this->cli = new IshCliBridge($context);
    }

    public function getName(): string
    {
        return 'ish:featurePack:pack';
    }

    public function getDescription(): string
    {
        return 'Packages an existing module into a feature pack ZIP file and optionally generates a registry XML snippet.';
    }

    public function getInputSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['module'],
            'properties' => [
                'module' => [
                    'type' => 'string',
                    'description' => 'The name of the module to package (e.g., "Blog").'
                ],
                'type' => [
                    'type' => 'string',
                    'description' => 'The type of feature pack (e.g., "community", "commercial"). Defaults to module manifest license or "community".',
                    'enum' => ['community', 'commercial']
                ],
                'registryOut' => [
                    'type' => 'string',
                    'description' => 'Optional file path to save the generated registry XML snippet.'
                ],
            ],
        ];
    }

    public function getOutputSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['success'],
            'properties' => [
                'success' => ['type' => 'boolean'],
                'output' => ['type' => 'string'],
                'registrySnippet' => [
                    'type' => 'string',
                    'description' => 'The generated XML snippet for registry.xml (if produced by the command).'
                ],
                'error' => ['type' => 'string'],
            ],
        ];
    }

    public function execute(array $input): array
    {
        $module = (string)$input['module'];
        $type = isset($input['type']) ? (string)$input['type'] : null;
        $registryOut = isset($input['registryOut']) ? (string)$input['registryOut'] : null;

        $options = [];
        if ($type) {
            $options['type'] = $type;
        }
        if ($registryOut) {
            $options['registry-out'] = $registryOut;
        }

        $result = $this->cli->execute('feature:pack', $options, [$module]);

        $output = $result['output'];
        $registrySnippet = '';

        // Extract registry snippet from output if it was printed to console
        // The ish CLI command usually prints it between markers or at the end
        if (preg_match('/<!-- Entry for a .* Feature Pack -->.*<\/feature>/s', $output, $matches)) {
            $registrySnippet = $matches[0];
        }

        return [
            'success' => $result['success'],
            'output' => $output,
            'registrySnippet' => $registrySnippet,
            'error' => $result['error']
        ];
    }
}
