<?php

declare(strict_types=1);

namespace IshmaelPHP\McpServer\Tools;

use IshmaelPHP\McpServer\Contracts\Tool;
use IshmaelPHP\McpServer\Project\ProjectContext;

/**

 * ish:migrate — Apply/rollback migrations with dry‑run and confirmation.

 * Incubation: reports planned action; no file or DB modifications are performed.

 */

final class MigrateTool implements Tool
{
    public function __construct()
    {
    }

    public function getName(): string
    {
        return 'ish:migrate';
    }



    public function getDescription(): string
    {

        return 'Apply or rollback migrations (dry-run by default; incubation does not execute).';
    }



    public function getInputSchema(): array
    {

        return [

            'type' => 'object',

            'additionalProperties' => false,

            'properties' => [

                'action' => ['type' => 'string', 'enum' => ['apply','rollback']],

                'steps' => ['type' => ['integer','null']],

                'dryRun' => ['type' => 'boolean'],

                'confirm' => ['type' => 'boolean'],

            ],

        ];
    }



    public function getOutputSchema(): array
    {

        return [

            'type' => 'object',

            'required' => ['planned','executed','messages'],

            'properties' => [

                'planned' => [

                    'type' => 'object',

                    'required' => ['action','steps'],

                    'properties' => [

                        'action' => ['type' => 'string'],

                        'steps' => ['type' => 'integer'],

                    ],

                    'additionalProperties' => false,

                ],

                'executed' => ['type' => 'boolean'],

                'messages' => ['type' => 'array', 'items' => ['type' => 'string']],

            ],

        ];
    }



    public function execute(array $input): array
    {

        $action = isset($input['action']) && in_array($input['action'], ['apply','rollback'], true)

            ? $input['action']

            : 'apply';

        $steps = isset($input['steps']) && is_int($input['steps']) ? max(1, $input['steps']) : 1;

        $dry = (bool)($input['dryRun'] ?? true);

        $confirm = (bool)($input['confirm'] ?? false);



        $willExecute = $confirm && !$dry;

        $messages = [];

        if (!$confirm) {
            $messages[] = 'Confirmation not provided; no changes executed.';
        }

        if ($dry) {
            $messages[] = 'Dry run mode; no changes executed.';
        }



        return [
            'planned' => [
                'action' => $action,
                'steps' => $steps,
            ],
            'executed' => false, // never execute in incubation
            'messages' => $messages,
        ];
    }
}
