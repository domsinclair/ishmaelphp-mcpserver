<?php

declare(strict_types=1);

namespace IshmaelPHP\McpServer\Tools;

use IshmaelPHP\McpServer\Contracts\Tool;
use IshmaelPHP\McpServer\Project\ProjectContext;

final class ProjectInfoTool implements Tool
{
    private ProjectContext $context;



    public function __construct(ProjectContext $context)
    {

        $this->context = $context;
    }



    public function getName(): string
    {

        return 'project/info';
    }



    public function getDescription(): string
    {

        return 'Return discovered project root, resolved vendor binaries, and sandbox status (read-only).';
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

            'required' => ['found', 'cwd'],

            'properties' => [

                'found' => ['type' => 'boolean'],

                'cwd' => ['type' => 'string'],

                'root' => ['type' => ['string', 'null']],

                'sandboxRoot' => ['type' => ['string', 'null']],

                'binaries' => [

                    'type' => 'object',

                    'additionalProperties' => ['type' => ['string', 'null']],

                ],

            ],

        ];
    }



    public function execute(array $input): array
    {

        $root = $this->context->getRoot();

        $sandbox = $this->context->getSandbox();



        return [

            'found' => $root !== null,

            'cwd' => getcwd() ?: '',

            'root' => $root,

            'sandboxRoot' => $sandbox ? $sandbox->getRoot() : null,

            'binaries' => $this->context->getBinaries(),

        ];
    }
}
