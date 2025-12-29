<?php

declare(strict_types=1);

namespace IshmaelPHP\McpServer\Tools;

use IshmaelPHP\McpServer\Contracts\Tool;

final class HealthVersionTool implements Tool
{
    private string $version;



    public function __construct(string $version)
    {

        $this->version = $version;
    }



    public function getName(): string
    {

        return 'health/version';
    }



    public function getDescription(): string
    {

        return 'Return server health and version information.';
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

            'required' => ['version', 'ok'],

            'properties' => [

                'version' => ['type' => 'string'],

                'ok' => ['type' => 'boolean'],

            ],

        ];
    }



    public function execute(array $input): array
    {

        return [

            'version' => $this->version,

            'ok' => true,

        ];
    }
}
