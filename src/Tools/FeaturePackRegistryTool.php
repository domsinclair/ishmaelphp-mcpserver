<?php

declare(strict_types=1);

namespace Ishmael\McpServer\Tools;

use Ishmael\McpServer\Contracts\Tool;
use Ishmael\McpServer\Project\ProjectContext;
use SimpleXMLElement;
use Exception;

/**
 * consults the centralized feature pack registry at Vtlsoftware.co.uk
 */
final class FeaturePackRegistryTool implements Tool
{
    /**
     * The default registry URL.
     */
    private const DEFAULT_REGISTRY_URL = 'http://vtl-ishmael-registry.test/registry/feature-packs.xml';

    private ?ProjectContext $context;

    public function __construct(?ProjectContext $context = null)
    {
        $this->context = $context;
    }

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
                            'name' => ['type' => 'string', 'description' => 'Canonical identifier (id)'],
                            'package' => ['type' => 'string', 'description' => 'Canonical identifier (id)'],
                            'tier' => ['type' => 'string', 'description' => 'community or commercial'],
                            'distribution' => [
                                'type' => 'object',
                                'properties' => [
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
                                ]
                            ],
                        ],
                    ],
                ],
                'error' => ['type' => 'string'],
            ],
        ];
    }

    private function getRegistryUrl(): string
    {
        if ($this->context !== null && $this->context->getRoot() !== null) {
            $configPath = $this->context->getRoot() . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'app.php';
            if (is_file($configPath)) {
                // We need to define these functions in the global namespace before requiring the config file.
                if (!function_exists('env')) {
                    eval('function env($key, $default = null) { return $_ENV[$key] ?? $_SERVER[$key] ?? $default; }');
                }
                if (!function_exists('base_path')) {
                    eval('function base_path($path = "") { return $path; }');
                }

                $config = require $configPath;
                if (is_array($config) && isset($config['registry_url'])) {
                    return (string)$config['registry_url'];
                }
            }
        }

        return self::DEFAULT_REGISTRY_URL;
    }

    public function execute(array $input): array
    {
        try {
            $registryUrl = $this->getRegistryUrl();

            // Note: In a real-world scenario, we'd use a more robust HTTP client and caching.
            // For now, simple file_get_contents with a timeout should suffice for demonstration.
            $context = stream_context_create([
                'http' => ['timeout' => 5]
            ]);
            
            $xmlContent = @file_get_contents($registryUrl, false, $context);
            
            if ($xmlContent === false) {
                return [
                    'features' => [],
                    'error' => 'Could not fetch registry from ' . $registryUrl
                ];
            }

            $xml = new SimpleXMLElement($xmlContent);
            $features = [];

            $query = isset($input['query']) ? strtolower($input['query']) : null;

            foreach ($xml->{'feature-pack'} as $fp) {
                $id = (string)$fp->id;
                $vendor = (string)$fp->vendor;
                $license = (string)$fp->license;
                $download = (string)$fp->download;
                
                $capabilities = [];
                if (isset($fp->capabilities)) {
                    foreach ($fp->capabilities->capability as $cap) {
                        $capabilities[] = (string)$cap['id'];
                    }
                }

                // Filtering by query (id or capabilities)
                if ($query) {
                    $match = str_contains(strtolower($id), $query) ||
                             in_array($query, array_map('strtolower', $capabilities));
                    
                    if (!$match) continue;
                }

                $features[] = [
                    'name' => $id,
                    'package' => $id,
                    'tier' => $license,
                    'distribution' => [
                        'url' => $download,
                    ],
                    'capabilities' => $capabilities,
                    'author' => [
                        'name' => $vendor,
                    ],
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
