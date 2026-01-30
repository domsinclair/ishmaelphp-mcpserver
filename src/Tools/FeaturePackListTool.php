<?php

declare(strict_types=1);

namespace Ishmael\McpServer\Tools;

use Ishmael\McpServer\Contracts\Tool;
use Ishmael\McpServer\FeaturePacks\CuratedIndexScanner;
use Ishmael\McpServer\FeaturePacks\LocalTemplateScanner;
use Ishmael\McpServer\Project\ProjectContext;

final class FeaturePackListTool implements Tool
{
    private ProjectContext $context;



    public function __construct(ProjectContext $context)
    {

        $this->context = $context;
    }



    public function getName(): string
    {

        return 'ish:featurePack:list';
    }



    public function getDescription(): string
    {

        return 'List available Feature Packs from local templates, curated index, and the centralized registry.';
    }



    public function getInputSchema(): array
    {

        return [

            'type' => 'object',

            'additionalProperties' => false,

            'properties' => [
                'query' => ['type' => 'string'],
                'vendorPrefix' => ['type' => 'string'],
                'includePrerelease' => ['type' => 'boolean'],
                'project_type' => ['type' => 'string', 'description' => 'Optional project type for context-aware scoring.'],
                'deployment' => ['type' => 'string', 'description' => 'Optional deployment environment.'],
                'ui_required' => ['type' => 'boolean', 'description' => 'Whether a UI is required.'],
            ],

        ];
    }



    public function getOutputSchema(): array
    {

        return [

            'type' => 'object',

            'required' => ['packs'],

            'properties' => [

                'packs' => [

                    'type' => 'array',

                    'items' => [

                        'type' => 'object',

                        'properties' => [

                            'name' => ['type' => 'string'],

                            'title' => ['type' => 'string'],

                            'synopsis' => ['type' => ['string','null']],

                            'description' => ['type' => ['string','null']],

                            'version' => ['type' => 'string'],

                            'package' => ['type' => ['string','null']],

                            'repoUrl' => ['type' => ['string','null']],

                            'keywords' => ['type' => 'array', 'items' => ['type' => 'string']],

                            'requires' => ['type' => 'array', 'items' => ['type' => 'string']],

                            'tier' => ['type' => ['string', 'null'], 'description' => 'community, commercial, or dual'],
                            'license_enforcement' => ['type' => 'string', 'description' => 'none, required, or optional'],
                            'score' => ['type' => 'number', 'description' => 'Relevance score'],
                            'category' => ['type' => 'string'],
                            'stability' => ['type' => 'string'],

                            'source' => ['type' => 'string'],

                        ],

                        'required' => ['name','version','stability','source'],

                        'additionalProperties' => true,

                    ],

                ],

                'registryError' => ['type' => 'string', 'description' => 'Detailed error if the central registry could not be reached.'],

            ],

        ];
    }



    public function execute(array $input): array
    {

        $filters = [
            'query' => isset($input['query']) && is_string($input['query']) ? $input['query'] : null,
            'vendorPrefix' => isset($input['vendorPrefix']) && is_string($input['vendorPrefix']) ? $input['vendorPrefix'] : null,
            'includePrerelease' => (bool)($input['includePrerelease'] ?? false),
            'project_type' => $input['project_type'] ?? null,
            'deployment' => $input['deployment'] ?? null,
            'ui_required' => isset($input['ui_required']) ? (bool)$input['ui_required'] : null,
        ];



        $packs = [];

        $sandbox = $this->context->getSandbox();

        $root = $this->context->getRoot();

        if ($sandbox !== null && $root !== null) {
            $local = new LocalTemplateScanner($sandbox, $root);

            foreach ($local->list($filters) as $p) {
                $p['title'] = $p['title'] ?? $p['name'];
                $p['synopsis'] = $p['synopsis'] ?? $p['description'] ?? null;
                $packs[$p['name'] . '|' . ($p['package'] ?? '')] = $p;
            }
        }

        $curated = new CuratedIndexScanner();

        foreach ($curated->list($filters) as $p) {
            $key = ($p['name'] ?? '') . '|' . ($p['package'] ?? '');

            if (!isset($packs[$key])) {
                $p['title'] = $p['title'] ?? $p['name'];
                $p['synopsis'] = $p['synopsis'] ?? $p['description'] ?? null;
                $packs[$key] = $p;
            }
        }

        $registry = new FeaturePackRegistryTool($this->context);
        $registryResult = $registry->execute($filters);
        
        $registryError = null;
        if (isset($registryResult['error'])) {
            $registryError = $registryResult['error'];
        }

        if (isset($registryResult['features']) && is_array($registryResult['features'])) {
            foreach ($registryResult['features'] as $f) {
                // Use name and version to distinguish between different versions of the same pack
                $key = $f['name'] . '|' . ($f['version'] ?? 'registry');
                if (!isset($packs[$key])) {
                    $packs[$key] = [
                        'name' => $f['name'],
                        'title' => $f['title'] ?? $f['name'],
                        'synopsis' => $f['synopsis'] ?? null,
                        'description' => $f['synopsis'] ?? null,
                        'version' => $f['version'] ?? 'registry',
                        'package' => $f['package'],
                        'repoUrl' => $f['distribution']['url'] ?? null,
                        'keywords' => $f['capabilities'] ?? [],
                        'tier' => $f['tier'] ?? 'community',
                        'license_enforcement' => $f['license_enforcement'] ?? 'none',
                        'score' => $f['score'] ?? 0,
                        'category' => $f['category'] ?? '',
                        'stability' => 'stable',
                        'source' => 'central-registry',
                    ];
                }
            }
        }

        // Sort by score descending if multiple sources are present, otherwise maintain local priority
        uasort($packs, function($a, $b) {
            $scoreA = $a['score'] ?? 0;
            $scoreB = $b['score'] ?? 0;
            return $scoreB <=> $scoreA;
        });

        $result = [ 'packs' => array_values($packs) ];
        if ($registryError) {
            $result['registryError'] = $registryError;
        }

        return $result;
    }
}
