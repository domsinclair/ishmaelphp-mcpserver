<?php

declare(strict_types=1);

namespace Ishmael\McpServer\Tools;

use Ishmael\McpServer\Contracts\Tool;
use Ishmael\McpServer\Project\ProjectContext;

/**
 * ish:featurePack:integrate — Compute and apply integration steps for installed packs.
 *
 * With dryRun=true (default) previews all operations without touching the filesystem.
 * With dryRun=false and confirm=true executes the planned writes.
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
        return 'Integrate Feature Packs (providers, config, assets, routes). Dry-run by default; pass dryRun=false and confirm=true to apply changes.';
    }

    public function getInputSchema(): array
    {
        return [
            'type'                 => 'object',
            'additionalProperties' => false,
            'required'             => ['packs'],
            'properties'           => [
                'packs'   => ['type' => 'array', 'items' => ['type' => 'string'], 'minItems' => 1],
                'options' => [
                    'type'                 => 'object',
                    'additionalProperties' => false,
                    'properties'           => [
                        'registerModules' => ['type' => 'boolean'],
                        'mergeConfig'     => ['type' => 'boolean'],
                        'publishAssets'   => ['type' => 'boolean'],
                        'addRoutes'       => ['type' => 'boolean'],
                    ],
                ],
                'dryRun'  => ['type' => 'boolean'],
                'confirm' => ['type' => 'boolean'],
            ],
        ];
    }

    public function getOutputSchema(): array
    {
        return [
            'type'     => 'object',
            'required' => ['planned', 'operations', 'conflicts', 'executed', 'messages'],
            'properties' => [
                'planned' => [
                    'type'                 => 'object',
                    'required'             => ['packs', 'options'],
                    'additionalProperties' => false,
                    'properties'           => [
                        'packs'   => ['type' => 'array', 'items' => ['type' => 'string']],
                        'options' => [
                            'type'                 => 'object',
                            'additionalProperties' => false,
                            'properties'           => [
                                'registerModules' => ['type' => 'boolean'],
                                'mergeConfig'     => ['type' => 'boolean'],
                                'publishAssets'   => ['type' => 'boolean'],
                                'addRoutes'       => ['type' => 'boolean'],
                            ],
                        ],
                    ],
                ],
                'operations' => [
                    'type'  => 'array',
                    'items' => [
                        'type'                 => 'object',
                        'required'             => ['type', 'path', 'existsBefore', 'wouldChange'],
                        'additionalProperties' => false,
                        'properties'           => [
                            'type'        => ['type' => 'string'],
                            'path'        => ['type' => 'string'],
                            'existsBefore'=> ['type' => 'boolean'],
                            'wouldChange' => ['type' => 'boolean'],
                            'diff'        => ['type' => ['string', 'null']],
                            'note'        => ['type' => ['string', 'null']],
                        ],
                    ],
                ],
                'conflicts' => [
                    'type'  => 'array',
                    'items' => [
                        'type'                 => 'object',
                        'required'             => ['path', 'reason'],
                        'additionalProperties' => false,
                        'properties'           => [
                            'path'   => ['type' => 'string'],
                            'reason' => ['type' => 'string'],
                        ],
                    ],
                ],
                'executed' => ['type' => 'boolean'],
                'messages' => ['type' => 'array', 'items' => ['type' => 'string']],
            ],
        ];
    }

    public function execute(array $input): array
    {
        $root    = $this->context->getRoot();
        $dry     = (bool)($input['dryRun'] ?? true);
        $confirm = (bool)($input['confirm'] ?? false);
        $opts    = $this->normalizeOptions($input['options'] ?? []);

        $packs = [];
        foreach ($input['packs'] as $p) {
            if (is_string($p) && $p !== '') {
                $packs[] = $p;
            }
        }

        $operations = [];
        $conflicts  = [];

        if ($root !== null) {
            foreach ($packs as $pack) {
                $safeName = str_replace(['\\', '/'], '.', $pack);

                if ($opts['registerModules']) {
                    $modulesFile  = $root . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'modules.php';
                    $exists       = is_file($modulesFile);
                    $wouldChange  = $exists ? !$this->fileContains($modulesFile, $pack) : true;
                    $operations[] = $this->op('merge', $modulesFile, $exists, $wouldChange, $wouldChange ? "+ '{$pack}'" : null, 'Add module/provider entry');
                }

                if ($opts['mergeConfig']) {
                    $configPath   = $root . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . $safeName . '.php';
                    $exists       = is_file($configPath);
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
                    $assetsPath   = $root . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . $safeName;
                    $exists       = is_dir($assetsPath);
                    $operations[] = $this->op('copy', $assetsPath, $exists, true, null, 'Copy public assets');
                }

                if ($opts['addRoutes']) {
                    $routesFile   = $root . DIRECTORY_SEPARATOR . 'routes' . DIRECTORY_SEPARATOR . $safeName . '.php';
                    $exists       = is_file($routesFile);
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
        $executed = false;

        if ($dry) {
            $messages[] = 'Dry run only; no changes executed.';
        } elseif ($confirm && $root !== null) {
            $executed = true;
            foreach ($operations as $op) {
                if (!$op['wouldChange']) {
                    continue;
                }

                $opType = $op['type'];
                $path   = $op['path'];
                $diff   = $op['diff'];

                try {
                    if ($opType === 'write') {
                        $dir = dirname($path);
                        if (!is_dir($dir)) {
                            mkdir($dir, 0755, true);
                        }
                        file_put_contents($path, (string)$diff);
                        $messages[] = "Written: $path";

                    } elseif ($opType === 'merge' && str_ends_with($path, 'modules.php')) {
                        $this->mergeModulesFile($path, $packs, $messages);

                    } elseif ($opType === 'append') {
                        $dir = dirname($path);
                        if (!is_dir($dir)) {
                            mkdir($dir, 0755, true);
                        }
                        file_put_contents($path, (string)$diff, FILE_APPEND);
                        $messages[] = "Appended: $path";

                    } elseif ($opType === 'copy') {
                        $messages[] = "Skipped asset copy (no source path known at plan time): $path";
                    }
                } catch (\Throwable $e) {
                    $executed     = false;
                    $conflicts[]  = ['path' => $path, 'reason' => $e->getMessage()];
                    $messages[]   = "Error writing $path: " . $e->getMessage();
                }
            }

            if ($executed && empty($conflicts)) {
                $messages[] = 'Integration complete.';
            }
        } else {
            $messages[] = 'Confirm not set; no changes executed. Pass confirm=true with dryRun=false to apply.';
        }

        return [
            'planned'    => ['packs' => $packs, 'options' => $opts],
            'operations' => $operations,
            'conflicts'  => $conflicts,
            'executed'   => $executed,
            'messages'   => $messages,
        ];
    }

    private function mergeModulesFile(string $path, array $packs, array &$messages): void
    {
        $dir = dirname($path);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        if (!is_file($path)) {
            $entries = implode(",\n    ", array_map(fn($p) => "'$p'", $packs));
            file_put_contents($path, "<?php\n\nreturn [\n    $entries,\n];\n");
            $messages[] = "Created: $path";
            return;
        }

        $content = file_get_contents($path);
        foreach ($packs as $pack) {
            if ($this->fileContains($path, $pack)) {
                $messages[] = "Already registered in modules.php: $pack";
                continue;
            }
            $content    = preg_replace('/(\];?\s*)$/', "    '$pack',\n$1", (string)$content);
            $messages[] = "Registered in modules.php: $pack";
        }
        file_put_contents($path, $content);
    }

    private function normalizeOptions(mixed $opt): array
    {
        $defaults = [
            'registerModules' => true,
            'mergeConfig'     => true,
            'publishAssets'   => true,
            'addRoutes'       => true,
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
            'type'         => $type,
            'path'         => $path,
            'existsBefore' => $exists,
            'wouldChange'  => $wouldChange,
            'diff'         => $diff,
            'note'         => $note,
        ];
    }

    private function fileContains(string $path, string $needle): bool
    {
        $contents = @file_get_contents($path);
        return $contents !== false && str_contains($contents, $needle);
    }
}
