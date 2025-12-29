<?php

declare(strict_types=1);

namespace IshmaelPHP\McpServer\Tools;

use IshmaelPHP\McpServer\Contracts\Tool;
use IshmaelPHP\McpServer\Project\ProjectContext;

/**

 * ish:featurePack:integrate — Compute idempotent integration steps for installed packs.

 * Incubation: preview only; no file modifications are performed.

 */

final class FeaturePackIntegrateTool implements Tool
{
    private ProjectContext $context;



    public function __construct(ProjectContext $context)
    {

        $this->context = $context;
    }



    public function getName(): string
    {

        return 'ish:featurePack:integrate';
    }



    public function getDescription(): string
    {

        return 'Integrate Feature Packs (providers, config, assets, routes). Preview changes; write only with confirm.';
    }



    public function getInputSchema(): array
    {

        return [

            'type' => 'object',

            'additionalProperties' => false,

            'required' => ['packs'],

            'properties' => [

                'packs' => ['type' => 'array', 'items' => ['type' => 'string'], 'minItems' => 1],

                'options' => [

                    'type' => 'object',

                    'additionalProperties' => false,

                    'properties' => [

                        'registerModules' => ['type' => 'boolean'],

                        'mergeConfig' => ['type' => 'boolean'],

                        'publishAssets' => ['type' => 'boolean'],

                        'addRoutes' => ['type' => 'boolean'],

                    ],

                ],

                'dryRun' => ['type' => 'boolean'],

                'confirm' => ['type' => 'boolean'],

            ],

        ];
    }



    public function getOutputSchema(): array
    {

        return [

            'type' => 'object',

            'required' => ['planned','operations','conflicts','executed','messages'],

            'properties' => [

                'planned' => [

                    'type' => 'object',

                    'required' => ['packs','options'],

                    'properties' => [

                        'packs' => ['type' => 'array', 'items' => ['type' => 'string']],

                        'options' => [

                            'type' => 'object',

                            'additionalProperties' => false,

                            'properties' => [

                                'registerModules' => ['type' => 'boolean'],

                                'mergeConfig' => ['type' => 'boolean'],

                                'publishAssets' => ['type' => 'boolean'],

                                'addRoutes' => ['type' => 'boolean'],

                            ],

                        ],

                    ],

                    'additionalProperties' => false,

                ],

                'operations' => [

                    'type' => 'array',

                    'items' => [

                        'type' => 'object',

                        'required' => ['type','path','existsBefore','wouldChange'],

                        'properties' => [

                            'type' => ['type' => 'string'],

                            'path' => ['type' => 'string'],

                            'existsBefore' => ['type' => 'boolean'],

                            'wouldChange' => ['type' => 'boolean'],

                            'diff' => ['type' => ['string','null']],

                            'note' => ['type' => ['string','null']],

                        ],

                        'additionalProperties' => false,

                    ],

                ],

                'conflicts' => [

                    'type' => 'array',

                    'items' => [

                        'type' => 'object',

                        'required' => ['path','reason'],

                        'properties' => [

                            'path' => ['type' => 'string'],

                            'reason' => ['type' => 'string'],

                        ],

                        'additionalProperties' => false,

                    ],

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

        $opts = $this->normalizeOptions($input['options'] ?? []);

        $packs = [];

        foreach ($input['packs'] as $p) {
            if (is_string($p) && $p !== '') {
                $packs[] = $p;
            }
        }



        $operations = [];

        $conflicts = [];

        if ($root !== null) {
            // Heuristic preview locations; in real implementation we'll inspect pack metadata.

            foreach ($packs as $pack) {
                $safeName = str_replace(['\\','/'], '.', $pack);

                if ($opts['registerModules']) {
                    $modulesFile = $root . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'modules.php';

                    $exists = is_file($modulesFile);

                    $wouldChange = $exists ? !$this->fileContains($modulesFile, $pack) : true;

                    $operations[] = $this->op('merge', $modulesFile, $exists, $wouldChange, $wouldChange ? "+ '{$pack}'" : null, 'Add module/provider entry');
                }

                if ($opts['mergeConfig']) {
                    $configPath = $root . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . $safeName . '.php';

                    $exists = is_file($configPath);

                    $operations[] = $this->op(
                        $exists ? 'merge' : 'write',
                        $configPath,
                        $exists,
                        !$exists,
                        $exists ? null : "<?php\nreturn [\n    // {$pack} config stub\n];\n",
                        'Create/merge config'
                    );
                }

                if ($opts['publishAssets']) {
                    $assetsPath = $root . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . $safeName;

                    $exists = is_dir($assetsPath);

                    $operations[] = $this->op($exists ? 'copy' : 'copy', $assetsPath, $exists, true, null, 'Copy public assets');
                }

                if ($opts['addRoutes']) {
                    $routesFile = $root . DIRECTORY_SEPARATOR . 'routes' . DIRECTORY_SEPARATOR . $safeName . '.php';

                    $exists = is_file($routesFile);

                    $operations[] = $this->op(
                        $exists ? 'append' : 'write',
                        $routesFile,
                        $exists,
                        true,
                        $exists ? "// routes for {$pack}\n" : "<?php\nuse Ishmael\\Routing\\Router;\n// routes for {$pack}\n",
                        'Import routes'
                    );
                }
            }
        }



        $messages = [];

        if ($dry) {
            $messages[] = 'Dry run only; no changes executed.';
        }

        if ($confirm && !$dry) {
            $messages[] = 'Incubation build: file writes disabled.';
        }



        return [

            'planned' => [ 'packs' => $packs, 'options' => $opts ],

            'operations' => $operations,

            'conflicts' => $conflicts,

            'executed' => false,

            'messages' => $messages,

        ];
    }



    /** @param mixed $opt */
    private function normalizeOptions($opt): array
    {

        $defaults = [

            'registerModules' => true,

            'mergeConfig' => true,

            'publishAssets' => true,

            'addRoutes' => true,

        ];

        if (!is_array($opt)) {
            return $defaults;
        }

        foreach ($defaults as $k => $v) {
            if (!array_key_exists($k, $opt) || !is_bool($opt[$k])) {
                $opt[$k] = $v;
            }
        }

        return $opt;
    }



    private function op(string $type, string $path, bool $exists, bool $wouldChange, ?string $diff, ?string $note): array
    {

        return [

            'type' => $type,

            'path' => $path,

            'existsBefore' => $exists,

            'wouldChange' => $wouldChange,

            'diff' => $diff,

            'note' => $note,

        ];
    }



    private function fileContains(string $path, string $needle): bool
    {

        $contents = @file_get_contents($path);

        if ($contents === false) {
            return false;
        }

        return strpos($contents, $needle) !== false;
    }
}
