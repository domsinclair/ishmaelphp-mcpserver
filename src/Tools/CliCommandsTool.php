<?php

declare(strict_types=1);

namespace Ishmael\McpServer\Tools;

use Ishmael\McpServer\Contracts\Tool;
use Ishmael\McpServer\Project\ProjectContext;
use Ishmael\McpServer\Support\IshCliBridge;

/**
 * ish:cli:commands — List all available Ishmael CLI commands.
 */
final class CliCommandsTool implements Tool
{
    private ProjectContext $context;

    public function __construct(ProjectContext $context)
    {
        $this->context = $context;
    }

    public function getName(): string
    {
        return 'ish:cli:commands';
    }

    public function getDescription(): string
    {
        return 'List all available Ishmael CLI commands including their descriptions and options.';
    }

    public function getInputSchema(): array
    {
        return [
            'type' => 'object',
            'additionalProperties' => false,
            'properties' => [],
        ];
    }

    public function getOutputSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['commands'],
            'properties' => [
                'commands' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'required' => ['name', 'description'],
                        'properties' => [
                            'name' => ['type' => 'string'],
                            'description' => ['type' => 'string'],
                            'options' => [
                                'type' => 'array',
                                'items' => [
                                    'type' => 'object',
                                    'properties' => [
                                        'name' => ['type' => 'string'],
                                        'description' => ['type' => 'string'],
                                        'accepts' => ['type' => ['string', 'null']],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }

    public function execute(array $input): array
    {
        $root = $this->context->getRoot();
        if ($root === null) {
            return ['commands' => [], 'error' => 'Project root not found.'];
        }

        $metadataPath = $root . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'cli_commands.json';
        if (!is_file($metadataPath)) {
            $bridge = new IshCliBridge($this->context);
            $bridge->execute('help');
        }

        if (is_file($metadataPath)) {
            $data = json_decode(file_get_contents($metadataPath), true);
            if (is_array($data) && isset($data['commands'])) {
                return ['commands' => $data['commands']];
            }
        }

        return [
            'commands' => [],
            'error' => 'Failed to load CLI commands metadata.',
        ];
    }
}
