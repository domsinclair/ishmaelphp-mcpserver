<?php

declare(strict_types=1);

namespace Ishmael\McpServer\Tools;

use Ishmael\McpServer\Contracts\Tool;
use Ishmael\McpServer\Project\ProjectContext;
use Ishmael\McpServer\Support\ComposerBridge;

/**
 * ish:featurePack:install — Plan/execute Composer require for Feature Packs.
 *
 * With dryRun=true (default) returns a deterministic plan only.
 * With dryRun=false and confirm=true executes composer require.
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
        return 'Install Feature Pack packages via Composer. Dry-run by default; pass dryRun=false and confirm=true to execute.';
    }

    public function getInputSchema(): array
    {
        return [
            'type'                 => 'object',
            'additionalProperties' => false,
            'required'             => ['packages'],
            'properties'           => [
                'packages'    => ['type' => 'array', 'items' => ['type' => 'string'], 'minItems' => 1],
                'dev'         => ['type' => 'boolean'],
                'preferDist'  => ['type' => 'boolean'],
                'noScripts'   => ['type' => 'boolean'],
                'dryRun'      => ['type' => 'boolean'],
                'confirm'     => ['type' => 'boolean'],
            ],
        ];
    }

    public function getOutputSchema(): array
    {
        return [
            'type'     => 'object',
            'required' => ['planned', 'dependencyGraph', 'composerJsonChanges', 'lockfile', 'scripts', 'executed', 'messages'],
            'properties' => [
                'planned' => [
                    'type'                 => 'object',
                    'required'             => ['packages', 'dev', 'flags'],
                    'additionalProperties' => false,
                    'properties'           => [
                        'packages' => ['type' => 'array', 'items' => ['type' => 'string']],
                        'dev'      => ['type' => 'boolean'],
                        'flags'    => ['type' => 'array', 'items' => ['type' => 'string']],
                    ],
                ],
                'dependencyGraph' => [
                    'type'                 => 'object',
                    'required'             => ['added', 'updated', 'removed'],
                    'additionalProperties' => false,
                    'properties'           => [
                        'added'   => ['type' => 'array', 'items' => ['type' => 'string']],
                        'updated' => ['type' => 'array', 'items' => ['type' => 'string']],
                        'removed' => ['type' => 'array', 'items' => ['type' => 'string']],
                    ],
                ],
                'composerJsonChanges' => [
                    'type'  => 'array',
                    'items' => [
                        'type'                 => 'object',
                        'required'             => ['op', 'path', 'before', 'after'],
                        'additionalProperties' => false,
                        'properties'           => [
                            'op'     => ['type' => 'string'],
                            'path'   => ['type' => 'string'],
                            'before' => ['type' => ['string', 'null']],
                            'after'  => ['type' => ['string', 'null']],
                        ],
                    ],
                ],
                'lockfile'  => [
                    'type'                 => 'object',
                    'required'             => ['wouldChange'],
                    'additionalProperties' => true,
                    'properties'           => [
                        'wouldChange' => ['type' => 'boolean'],
                    ],
                ],
                'scripts'  => ['type' => 'array', 'items' => ['type' => 'string']],
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
        $dev     = (bool)($input['dev'] ?? false);

        $flags = [];
        if (!empty($input['preferDist'])) {
            $flags[] = '--prefer-dist';
        }
        if (!empty($input['noScripts'])) {
            $flags[] = '--no-scripts';
        }

        $packages = [];
        foreach ($input['packages'] as $p) {
            if (is_string($p) && $p !== '') {
                $packages[] = $this->redactToken($p);
            }
        }

        $changes = [];
        $added   = [];
        $updated = [];
        $removed = [];
        $scripts = [];

        if ($root !== null) {
            $composerPath = $root . DIRECTORY_SEPARATOR . 'composer.json';
            $requireKey   = $dev ? 'require-dev' : 'require';
            $current      = ['require' => [], 'require-dev' => []];

            if (is_file($composerPath)) {
                $json = json_decode(file_get_contents($composerPath) ?: '{}', true) ?: [];
                $current['require']     = isset($json['require'])     && is_array($json['require'])     ? $json['require']     : [];
                $current['require-dev'] = isset($json['require-dev']) && is_array($json['require-dev']) ? $json['require-dev'] : [];
            }

            foreach ($packages as $full) {
                [$name, $constraint] = $this->splitPackage($full);
                $before = $current[$requireKey][$name] ?? null;
                $after  = $constraint ?: '*';

                if ($before === null) {
                    $added[]   = $name;
                    $changes[] = ['op' => 'add',    'path' => $requireKey . '.' . $name, 'before' => null,                           'after' => $after];
                } elseif ($before !== $after) {
                    $updated[] = $name;
                    $changes[] = ['op' => 'update', 'path' => $requireKey . '.' . $name, 'before' => is_string($before) ? $before : null, 'after' => $after];
                }
            }

            if (isset($json) && is_array($json)) {
                $scripts = array_keys($json['scripts'] ?? []);
            }
        }

        $messages = [];
        $executed = false;

        if ($dry) {
            $messages[] = 'Dry run only; no changes executed.';
        } elseif ($confirm) {
            $composerFlags = [];
            if (!empty($input['preferDist'])) {
                $composerFlags['prefer-dist'] = true;
            }
            if (!empty($input['noScripts'])) {
                $composerFlags['no-scripts'] = true;
            }
            if ($dev) {
                $composerFlags['dev'] = true;
            }

            $bridge = new ComposerBridge($this->context);
            $result = $bridge->execute('require', $packages, $composerFlags);

            $executed = $result['success'];
            $messages[] = $result['success']
                ? 'composer require executed successfully.'
                : 'composer require failed: ' . ($result['error'] ?? 'unknown error');

            if (!empty($result['output'])) {
                $messages[] = $result['output'];
            }
        } else {
            $messages[] = 'Confirm not set; no changes executed. Pass confirm=true with dryRun=false to apply.';
        }

        return [
            'planned'             => ['packages' => $packages, 'dev' => $dev, 'flags' => $flags],
            'dependencyGraph'     => ['added' => array_values(array_unique($added)), 'updated' => array_values(array_unique($updated)), 'removed' => []],
            'composerJsonChanges' => $changes,
            'lockfile'            => ['wouldChange' => $added !== [] || $updated !== []],
            'scripts'             => $scripts,
            'executed'            => $executed,
            'messages'            => $messages,
        ];
    }

    private function splitPackage(string $spec): array
    {
        $parts      = explode('@', $spec, 2);
        $name       = trim($parts[0]);
        $constraint = isset($parts[1]) ? trim($parts[1]) : '';
        return [$name, $constraint];
    }

    private function redactToken(string $s): string
    {
        if (preg_match('~https?://([^:@]+):([A-Za-z0-9_\-]{20,})@~', $s) === 1) {
            return preg_replace('~(https?://[^:@]+):([A-Za-z0-9_\-]{20,})@~', '$1:***@', $s) ?: $s;
        }
        return $s;
    }
}
