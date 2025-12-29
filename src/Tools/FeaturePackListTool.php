<?php

declare(strict_types=1);

namespace IshmaelPHP\McpServer\Tools;

use IshmaelPHP\McpServer\Contracts\Tool;
use IshmaelPHP\McpServer\FeaturePacks\CuratedIndexScanner;
use IshmaelPHP\McpServer\FeaturePacks\LocalTemplateScanner;
use IshmaelPHP\McpServer\Project\ProjectContext;

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

        return 'List available Feature Packs from local templates and curated index (read-only).';
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

                            'description' => ['type' => ['string','null']],

                            'version' => ['type' => 'string'],

                            'package' => ['type' => ['string','null']],

                            'repoUrl' => ['type' => ['string','null']],

                            'keywords' => ['type' => 'array', 'items' => ['type' => 'string']],

                            'requires' => ['type' => 'array', 'items' => ['type' => 'string']],

                            'stability' => ['type' => 'string'],

                            'source' => ['type' => 'string'],

                        ],

                        'required' => ['name','version','stability','source'],

                        'additionalProperties' => true,

                    ],

                ],

            ],

        ];
    }



    public function execute(array $input): array
    {

        $filters = [

            'query' => isset($input['query']) && is_string($input['query']) ? $input['query'] : null,

            'vendorPrefix' => isset($input['vendorPrefix']) && is_string($input['vendorPrefix']) ? $input['vendorPrefix'] : null,

            'includePrerelease' => (bool)($input['includePrerelease'] ?? false),

        ];



        $packs = [];

        $sandbox = $this->context->getSandbox();

        $root = $this->context->getRoot();

        if ($sandbox !== null && $root !== null) {
            $local = new LocalTemplateScanner($sandbox, $root);

            foreach ($local->list($filters) as $p) {
                $packs[$p['name'] . '|' . ($p['package'] ?? '')] = $p;
            }
        }

        $curated = new CuratedIndexScanner();

        foreach ($curated->list($filters) as $p) {
            $key = ($p['name'] ?? '') . '|' . ($p['package'] ?? '');

            if (!isset($packs[$key])) {
                $packs[$key] = $p;
            }
        }



        // Note: Composer/Packagist aggregation can be added in a future iteration.



        return [ 'packs' => array_values($packs) ];
    }
}
