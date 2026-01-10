<?php

declare(strict_types=1);

namespace Ishmael\McpServer\Tools;

use Ishmael\McpServer\Contracts\Tool;
use Ishmael\McpServer\Project\ProjectContext;

/**
 * ish:env:drift — Compare .env with .env.example.
 */
final class EnvDriftTool implements Tool
{
    private ProjectContext $context;

    public function __construct(ProjectContext $context)
    {
        $this->context = $context;
    }

    public function getName(): string
    {
        return 'ish:env:drift';
    }

    public function getDescription(): string
    {
        return 'Detect drift between .env and .env.example, identifying missing keys in either file.';
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
            'required' => ['drift_detected', 'missing_in_env', 'missing_in_example'],
            'properties' => [
                'drift_detected' => ['type' => 'boolean'],
                'missing_in_env' => [
                    'type' => 'array',
                    'items' => ['type' => 'string'],
                    'description' => 'Keys present in .env.example but missing in .env.'
                ],
                'missing_in_example' => [
                    'type' => 'array',
                    'items' => ['type' => 'string'],
                    'description' => 'Keys present in .env but missing in .env.example.'
                ],
                'env_path' => ['type' => ['string', 'null']],
                'example_path' => ['type' => ['string', 'null']],
            ],
        ];
    }

    public function execute(array $input): array
    {
        $root = $this->context->getRoot();
        if (!$root) {
            return [
                'drift_detected' => false,
                'missing_in_env' => [],
                'missing_in_example' => [],
                'error' => 'Project root not found.'
            ];
        }

        $envPath = $root . DIRECTORY_SEPARATOR . '.env';
        $examplePath = $root . DIRECTORY_SEPARATOR . '.env.example';

        $envKeys = is_file($envPath) ? array_keys($this->parseEnv(file_get_contents($envPath))) : [];
        $exampleKeys = is_file($examplePath) ? array_keys($this->parseEnv(file_get_contents($examplePath))) : [];

        $missingInEnv = array_values(array_diff($exampleKeys, $envKeys));
        $missingInExample = array_values(array_diff($envKeys, $exampleKeys));

        return [
            'drift_detected' => !empty($missingInEnv) || !empty($missingInExample),
            'missing_in_env' => $missingInEnv,
            'missing_in_example' => $missingInExample,
            'env_path' => is_file($envPath) ? $envPath : null,
            'example_path' => is_file($examplePath) ? $examplePath : null,
        ];
    }

    /** @return array<string,string> */
    private function parseEnv(string $contents): array
    {
        $out = [];
        foreach (preg_split('/\r?\n/', $contents) as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }
            $parts = explode('=', $line, 2);
            if (count($parts) === 2) {
                $key = trim($parts[0]);
                $out[$key] = trim(trim($parts[1]), "\"' ");
            }
        }
        return $out;
    }
}
