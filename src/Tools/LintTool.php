<?php

declare(strict_types=1);

namespace IshmaelPHP\McpServer\Tools;

use IshmaelPHP\McpServer\Contracts\Tool;
use IshmaelPHP\McpServer\Project\ProjectContext;

/**

 * ish:lint — Run PHPStan/Psalm and PHPCS; return structured findings.

 * Incubation note: does not execute external processes; reports support and returns empty findings.

 */

final class LintTool implements Tool
{
    private ProjectContext $context;



    public function __construct(ProjectContext $context)
    {

        $this->context = $context;
    }



    public function getName(): string
    {

        return 'ish:lint';
    }



    public function getDescription(): string
    {

        return 'Run PHPStan/Psalm/PHPCS and return structured findings (incubation: capability report).';
    }



    public function getInputSchema(): array
    {

        return [

            'type' => 'object',

            'additionalProperties' => false,

            'properties' => [

                'linters' => ['type' => 'array', 'items' => ['type' => 'string']],

                'paths' => ['type' => 'array', 'items' => ['type' => 'string']],

                'level' => ['type' => ['string','null']],

                'dryRun' => ['type' => 'boolean'],

            ],

        ];
    }



    public function getOutputSchema(): array
    {

        return [

            'type' => 'object',

            'required' => ['supported', 'findings'],

            'properties' => [

                'supported' => [

                    'type' => 'object',

                    'additionalProperties' => ['type' => ['string','null']],

                ],

                'findings' => [

                    'type' => 'array',

                    'items' => [

                        'type' => 'object',

                        'required' => ['tool','level','message'],

                        'properties' => [

                            'tool' => ['type' => 'string'],

                            'file' => ['type' => ['string','null']],

                            'line' => ['type' => ['integer','null']],

                            'column' => ['type' => ['integer','null']],

                            'level' => ['type' => 'string'],

                            'rule' => ['type' => ['string','null']],

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

        $bins = $this->context->getBinaries();

        return [

            'supported' => [

                'phpstan' => $bins['phpstan'] ?? null,

                'phpcs' => $bins['phpcs'] ?? null,

                'psalm' => null, // not resolved by VendorBinaryResolver yet

            ],

            'findings' => [],

        ];
    }
}
