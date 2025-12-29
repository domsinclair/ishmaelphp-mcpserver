<?php

declare(strict_types=1);

namespace IshmaelPHP\McpServer\Tools;

use IshmaelPHP\McpServer\Contracts\Tool;
use IshmaelPHP\McpServer\Project\ProjectContext;

/**

 * ish:featurePack:install — Plan/execute Composer require for Feature Packs.

 * Incubation phase: does NOT execute Composer; returns a deterministic plan only.

 */

final class FeaturePackInstallTool implements Tool
{
    private ProjectContext $context;



    public function __construct(ProjectContext $context)
    {

        $this->context = $context;
    }



    public function getName(): string
    {

        return 'ish:featurePack:install';
    }



    public function getDescription(): string
    {

        return 'Install Feature Pack packages via Composer (dry-run plan; incubation does not execute).';
    }



    public function getInputSchema(): array
    {

        return [

            'type' => 'object',

            'additionalProperties' => false,

            'required' => ['packages'],

            'properties' => [

                'packages' => ['type' => 'array', 'items' => ['type' => 'string'], 'minItems' => 1],

                'dev' => ['type' => 'boolean'],

                'preferDist' => ['type' => 'boolean'],

                'noScripts' => ['type' => 'boolean'],

                'dryRun' => ['type' => 'boolean'],

                'confirm' => ['type' => 'boolean'],

            ],

        ];
    }



    public function getOutputSchema(): array
    {

        return [

            'type' => 'object',

            'required' => ['planned','dependencyGraph','composerJsonChanges','lockfile','scripts','executed','messages'],

            'properties' => [

                'planned' => [

                    'type' => 'object',

                    'required' => ['packages','dev','flags'],

                    'properties' => [

                        'packages' => ['type' => 'array', 'items' => ['type' => 'string']],

                        'dev' => ['type' => 'boolean'],

                        'flags' => ['type' => 'array', 'items' => ['type' => 'string']],

                    ],

                    'additionalProperties' => false,

                ],

                'dependencyGraph' => [

                    'type' => 'object',

                    'required' => ['added','updated','removed'],

                    'properties' => [

                        'added' => ['type' => 'array', 'items' => ['type' => 'string']],

                        'updated' => ['type' => 'array', 'items' => ['type' => 'string']],

                        'removed' => ['type' => 'array', 'items' => ['type' => 'string']],

                    ],

                    'additionalProperties' => false,

                ],

                'composerJsonChanges' => [

                    'type' => 'array',

                    'items' => [

                        'type' => 'object',

                        'required' => ['op','path','before','after'],

                        'properties' => [

                            'op' => ['type' => 'string'],

                            'path' => ['type' => 'string'],

                            'before' => ['type' => ['string','null']],

                            'after' => ['type' => ['string','null']],

                        ],

                        'additionalProperties' => false,

                    ],

                ],

                'lockfile' => [

                    'type' => 'object',

                    'required' => ['wouldChange'],

                    'properties' => [

                        'wouldChange' => ['type' => 'boolean'],

                    ],

                    'additionalProperties' => true,

                ],

                'scripts' => [

                    'type' => 'array',

                    'items' => ['type' => 'string'],

                ],

                'executed' => ['type' => 'boolean'],

                'messages' => ['type' => 'array', 'items' => ['type' => 'string']],

            ],

        ];
    }



    public function execute(array $input): array
    {

        $root = $this->context->getRoot();

        $dry = (bool)($input['dryRun'] ?? true);

        $confirm = (bool)($input['confirm'] ?? false);

        $dev = (bool)($input['dev'] ?? false);

        $flags = [];

        if (isset($input['preferDist']) && $input['preferDist']) {
            $flags[] = '--prefer-dist';
        }

        if (isset($input['noScripts']) && $input['noScripts']) {
            $flags[] = '--no-scripts';
        }



        $packages = [];

        foreach ($input['packages'] as $p) {
            if (is_string($p) && $p !== '') {
                $packages[] = $this->redactToken($p);
            }
        }



        // Read composer.json if available to compute simple deltas

        $changes = [];

        $added = [];

        $updated = [];

        $removed = [];

        $scripts = [];

        if ($root !== null) {
            $composerPath = $root . DIRECTORY_SEPARATOR . 'composer.json';

            $requireKey = $dev ? 'require-dev' : 'require';

            $current = [ 'require' => [], 'require-dev' => [] ];

            if (is_file($composerPath)) {
                $json = json_decode(file_get_contents($composerPath) ?: '{}', true) ?: [];

                $current['require'] = isset($json['require']) && is_array($json['require']) ? $json['require'] : [];

                $current['require-dev'] = isset($json['"require-dev"']) && is_array($json['"require-dev"'])

                    ? $json['"require-dev"']

                    : ($json['require-dev'] ?? []);
            }

            foreach ($packages as $full) {
                // split vendor/name@constraint

                [$name, $constraint] = $this->splitPackage($full);

                $before = $current[$requireKey][$name] ?? null;

                $after = $constraint ?: '*';

                if ($before === null) {
                    $added[] = $name;

                    $changes[] = [

                        'op' => 'add',

                        'path' => $requireKey . '.' . $name,

                        'before' => null,

                        'after' => $after,

                    ];
                } elseif ($before !== $after) {
                    $updated[] = $name;

                    $changes[] = [

                        'op' => 'update',

                        'path' => $requireKey . '.' . $name,

                        'before' => is_string($before) ? $before : null,

                        'after' => $after,

                    ];
                }
            }

            // Detect scripts that would run on post-update/install

            if (isset($json) && is_array($json)) {
                $scripts = array_keys(($json['scripts'] ?? []));
            }
        }



        $messages = [];

        if ($dry) {
            $messages[] = 'Dry run only; no changes executed.';
        }

        if ($confirm && !$dry) {
            $messages[] = 'Incubation build: Composer execution disabled.';
        }



        return [

            'planned' => [

                'packages' => $packages,

                'dev' => $dev,

                'flags' => $flags,

            ],

            'dependencyGraph' => [

                'added' => array_values(array_unique($added)),

                'updated' => array_values(array_unique($updated)),

                'removed' => array_values(array_unique($removed)),

            ],

            'composerJsonChanges' => $changes,

            'lockfile' => [ 'wouldChange' => $added !== [] || $updated !== [] ],

            'scripts' => $scripts,

            'executed' => false,

            'messages' => $messages,

        ];
    }



    private function splitPackage(string $spec): array
    {

        $parts = explode('@', $spec, 2);

        $name = trim($parts[0]);

        $constraint = isset($parts[1]) ? trim($parts[1]) : '';

        return [$name, $constraint];
    }



    private function redactToken(string $s): string
    {

        // Redact basic GitHub token patterns in URLs if present

        if (preg_match('~https?://([^:@]+):([A-Za-z0-9_\-]{20,})@~', $s) === 1) {
            return preg_replace('~(https?://[^:@]+):([A-Za-z0-9_\-]{20,})@~', '$1:***@', $s) ?: $s;
        }

        return $s;
    }
}
