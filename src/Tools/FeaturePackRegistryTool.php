<?php

declare(strict_types=1);

namespace Ishmael\McpServer\Tools;

use Ishmael\McpServer\Contracts\Tool;
use SimpleXMLElement;
use Exception;

/**
 * consults the centralized feature pack registry at Vtlsoftware.co.uk
 */
final class FeaturePackRegistryTool implements Tool
{
    private const REGISTRY_URL = 'https://vtlsoftware.co.uk/ishmael/registry.xml';

    public function getName(): string
    {
        return 'ish:featurePack:registry';
    }

    public function getDescription(): string
    {
        return 'Search and discover feature packs from the Ishmael centralized registry (VtlSoftware).';
    }

    public function getInputSchema(): array
    {
        return [
            'type' => 'object',
            'additionalProperties' => false,
            'properties' => [
                'query' => [
                    'type' => 'string',
                    'description' => 'Optional search query to filter features by title, synopsis, or capabilities.'
                ],
                'category' => [
                    'type' => 'string',
                    'description' => 'Optional category filter.'
                ],
            ],
        ];
    }

    public function getOutputSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['features'],
            'properties' => [
                'features' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'name' => ['type' => 'string'],
                            'title' => ['type' => 'string'],
                            'category' => ['type' => 'string'],
                            'synopsis' => ['type' => 'string'],
                            'installCommand' => ['type' => 'string'],
                            'license' => [
                                'type' => 'object',
                                'properties' => [
                                    'type' => ['type' => 'string'],
                                    'enforcement' => ['type' => 'string'],
                                    'trial' => ['type' => 'boolean'],
                                ]
                            ],
                            'distribution' => [
                                'type' => 'object',
                                'properties' => [
                                    'method' => ['type' => 'string'],
                                    'url' => ['type' => 'string'],
                                ]
                            ],
                            'capabilities' => [
                                'type' => 'array',
                                'items' => ['type' => 'string']
                            ],
                            'author' => [
                                'type' => 'object',
                                'properties' => [
                                    'name' => ['type' => 'string'],
                                    'email' => ['type' => 'string'],
                                    'url' => ['type' => 'string'],
                                ]
                            ],
                        ],
                    ],
                ],
                'error' => ['type' => 'string'],
            ],
        ];
    }

    public function execute(array $input): array
    {
        try {
            // Note: In a real-world scenario, we'd use a more robust HTTP client and caching.
            // For now, simple file_get_contents with a timeout should suffice for demonstration.
            $context = stream_context_create([
                'http' => ['timeout' => 5]
            ]);
            
            $xmlContent = @file_get_contents(self::REGISTRY_URL, false, $context);
            
            if ($xmlContent === false) {
                return [
                    'features' => [],
                    'error' => 'Could not fetch registry from ' . self::REGISTRY_URL
                ];
            }

            $xml = new SimpleXMLElement($xmlContent);
            $features = [];

            $query = isset($input['query']) ? strtolower($input['query']) : null;
            $categoryFilter = isset($input['category']) ? strtolower($input['category']) : null;

            foreach ($xml->feature as $f) {
                $name = (string)$f['name'];
                $title = (string)$f->title;
                $category = (string)$f->category;
                $synopsis = (string)$f->synopsis;
                $installCommand = (string)$f->{'install-command'};
                
                $capabilities = [];
                if (isset($f->capabilities)) {
                    foreach ($f->capabilities->capability as $cap) {
                        $capabilities[] = (string)$cap;
                    }
                }

                // Filtering
                if ($categoryFilter && strtolower($category) !== $categoryFilter) {
                    continue;
                }

                if ($query) {
                    $match = str_contains(strtolower($title), $query) ||
                             str_contains(strtolower($synopsis), $query) ||
                             in_array($query, array_map('strtolower', $capabilities));
                    
                    if (!$match) continue;
                }

                $license = [];
                if (isset($f->license)) {
                    $license = [
                        'type' => (string)$f->license['type'],
                        'enforcement' => (string)$f->license['enforcement'],
                        'trial' => ((string)$f->license['trial']) === 'true',
                    ];
                }

                $distribution = [];
                if (isset($f->distribution)) {
                    $distribution = [
                        'method' => (string)$f->distribution->method,
                        'url' => (string)$f->distribution->url,
                    ];
                }

                $author = [];
                if (isset($f->author)) {
                    $author = [
                        'name' => (string)$f->author->name,
                        'email' => (string)$f->author->email,
                        'url' => (string)$f->author->url,
                    ];
                }

                $features[] = [
                    'name' => $name,
                    'title' => $title,
                    'category' => $category,
                    'synopsis' => $synopsis,
                    'installCommand' => $installCommand,
                    'license' => $license,
                    'distribution' => $distribution,
                    'capabilities' => $capabilities,
                    'author' => $author,
                ];
            }

            return ['features' => $features];

        } catch (Exception $e) {
            return [
                'features' => [],
                'error' => 'Error parsing registry: ' . $e->getMessage()
            ];
        }
    }
}
