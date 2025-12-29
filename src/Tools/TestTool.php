<?php

declare(strict_types=1);

namespace IshmaelPHP\McpServer\Tools;

use IshmaelPHP\McpServer\Contracts\Tool;
use IshmaelPHP\McpServer\Project\ProjectContext;

/**

 * ish:test — Execute vendor/bin/phpunit with filters and return summarized results.

 * Note: In this incubation build, we do not execute external processes for safety.

 * We return a deterministic stub indicating capability and discovered binary path.

 */

final class TestTool implements Tool
{
    private ProjectContext $context;



    public function __construct(ProjectContext $context)
    {

        $this->context = $context;
    }



    public function getName(): string
    {

        return 'ish:test';
    }



    public function getDescription(): string
    {

        return 'Run PHPUnit with filters and return summarized results (incubation: dry stub).';
    }



    public function getInputSchema(): array
    {

        return [

            'type' => 'object',

            'additionalProperties' => false,

            'properties' => [

                'filter' => ['type' => ['string','null']],

                'group' => ['type' => ['string','null']],

                'path' => ['type' => ['string','null']],

                'dryRun' => ['type' => 'boolean'],

            ],

        ];
    }



    public function getOutputSchema(): array
    {

        return [

            'type' => 'object',

            'required' => ['supported', 'binary', 'summary', 'failures'],

            'properties' => [

                'supported' => ['type' => 'boolean'],

                'binary' => ['type' => ['string','null']],

                'summary' => [

                    'type' => 'object',

                    'required' => ['tests','assertions','failures','errors','skipped','timeSeconds'],

                    'properties' => [

                        'tests' => ['type' => 'integer'],

                        'assertions' => ['type' => 'integer'],

                        'failures' => ['type' => 'integer'],

                        'errors' => ['type' => 'integer'],

                        'skipped' => ['type' => 'integer'],

                        'timeSeconds' => ['type' => 'number'],

                    ],

                ],

                'failures' => [

                    'type' => 'array',

                    'items' => [

                        'type' => 'object',

                        'required' => ['test','message'],

                        'properties' => [

                            'test' => ['type' => 'string'],

                            'file' => ['type' => ['string','null']],

                            'line' => ['type' => ['integer','null']],

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

        $bin = $this->context->getBinaries()['phpunit'] ?? null;

        return [

            'supported' => $bin !== null,

            'binary' => $bin,

            'summary' => [

                'tests' => 0,

                'assertions' => 0,

                'failures' => 0,

                'errors' => 0,

                'skipped' => 0,

                'timeSeconds' => 0.0,

            ],

            'failures' => [],

        ];
    }
}
