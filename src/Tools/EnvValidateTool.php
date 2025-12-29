<?php

declare(strict_types=1);

namespace IshmaelPHP\McpServer\Tools;

use IshmaelPHP\McpServer\Contracts\Tool;
use IshmaelPHP\McpServer\Project\ProjectContext;

/**

 * ish:env:validate — Validate .env against a declared schema.

 * Incubation: supports a simple built-in schema format (required keys list).

 */

final class EnvValidateTool implements Tool
{
    private ProjectContext $context;



    public function __construct(ProjectContext $context)
    {

        $this->context = $context;
    }



    public function getName(): string
    {

        return 'ish:env:validate';
    }



    public function getDescription(): string
    {

        return 'Validate .env file against a minimal schema (incubation).';
    }



    public function getInputSchema(): array
    {

        return [

            'type' => 'object',

            'additionalProperties' => false,

            'properties' => [

                'schemaPath' => ['type' => ['string','null']],

                'requiredKeys' => ['type' => 'array', 'items' => ['type' => 'string']],

            ],

        ];
    }



    public function getOutputSchema(): array
    {

        return [

            'type' => 'object',

            'required' => ['envPath','exists','violations'],

            'properties' => [

                'envPath' => ['type' => ['string','null']],

                'exists' => ['type' => 'boolean'],

                'violations' => [

                    'type' => 'array',

                    'items' => [

                        'type' => 'object',

                        'required' => ['key','message'],

                        'properties' => [

                            'key' => ['type' => 'string'],

                            'message' => ['type' => 'string'],

                        ],

                        'additionalProperties' => false,

                    ],

                ],

            ],

        ];
    }



    public function execute(array $input): array
    {

        $root = $this->context->getRoot();

        $envPath = $root ? $root . DIRECTORY_SEPARATOR . '.env' : null;

        $env = [];

        $exists = false;

        if ($envPath && is_file($envPath)) {
            $exists = true;

            $env = $this->parseEnv(file_get_contents($envPath) ?: '');
        }



        $required = [];

        if (isset($input['requiredKeys']) && is_array($input['requiredKeys'])) {
            foreach ($input['requiredKeys'] as $k) {
                if (is_string($k)) {
                    $required[] = $k;
                }
            }
        } elseif (isset($input['schemaPath']) && is_string($input['schemaPath'])) {
            $schemaPath = $input['schemaPath'];

            if (is_file($schemaPath)) {
                $schema = json_decode(file_get_contents($schemaPath) ?: '[]', true);

                if (is_array($schema) && isset($schema['required']) && is_array($schema['required'])) {
                    foreach ($schema['required'] as $k) {
                        if (is_string($k)) {
                            $required[] = $k;
                        }
                    }
                }
            }
        }



        $violations = [];

        foreach ($required as $key) {
            if (!array_key_exists($key, $env) || $env[$key] === '') {
                $violations[] = [ 'key' => $key, 'message' => 'Missing or empty' ];
            }
        }



        return [

            'envPath' => $envPath,

            'exists' => $exists,

            'violations' => $violations,

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

                $val = trim($parts[1]);

                $val = trim($val, "\"' ");

                $out[$key] = $val;
            }
        }

        return $out;
    }
}
