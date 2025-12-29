<?php

declare(strict_types=1);

namespace IshmaelPHP\McpServer\Tools;

use IshmaelPHP\McpServer\Contracts\Tool;
use IshmaelPHP\McpServer\Project\ProjectContext;

/**

 * ish:container:describe — Enumerate container services and aliases.

 * Incubation: returns an empty listing; future versions may introspect Ishmael container.

 */

final class ContainerDescribeTool implements Tool
{
    public function __construct()
    {
    }



    public function getName(): string
    {

        return 'ish:container:describe';
    }



    public function getDescription(): string
    {

        return 'Describe DI container services and aliases (read-only; incubation placeholder).';
    }



    public function getInputSchema(): array
    {

        return [

            'type' => 'object',

            'additionalProperties' => false,

            'properties' => [

                'id' => ['type' => ['string','null']],

                'tag' => ['type' => ['string','null']],

            ],

        ];
    }



    public function getOutputSchema(): array
    {

        return [

            'type' => 'object',

            'required' => ['services','aliases'],

            'properties' => [

                'services' => [

                    'type' => 'array',

                    'items' => [

                        'type' => 'object',

                        'required' => ['id'],

                        'properties' => [

                            'id' => ['type' => 'string'],

                            'class' => ['type' => ['string','null']],

                            'singleton' => ['type' => ['boolean','null']],

                            'tags' => ['type' => 'array', 'items' => ['type' => 'string']],

                            'aliases' => ['type' => 'array', 'items' => ['type' => 'string']],

                        ],

                        'additionalProperties' => false,

                    ],

                ],

                'aliases' => [

                    'type' => 'array',

                    'items' => [

                        'type' => 'object',

                        'required' => ['alias','target'],

                        'properties' => [

                            'alias' => ['type' => 'string'],

                            'target' => ['type' => 'string'],

                        ],

                        'additionalProperties' => false,

                    ],

                ],

            ],

        ];
    }



    public function execute(array $input): array
    {

        // Placeholder: no container introspection yet in incubation stage

        return [

            'services' => [],

            'aliases' => [],

        ];
    }
}
