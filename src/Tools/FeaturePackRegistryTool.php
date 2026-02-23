<?php

declare(strict_types=1);

namespace Ishmael\McpServer\Tools;

use Ishmael\McpServer\Contracts\Tool;
use Ishmael\McpServer\Project\ProjectContext;
use Ishmael\McpServer\Support\RegistryToolHelper;
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
    private const DEFAULT_REGISTRY_URL = 'https://vtl-ishmael-registry.test/registry/feature-packs.json';

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
                'project_type' => [
                    'type' => 'string',
                    'description' => 'Optional project type for context-aware scoring (e.g., blog, e-commerce, api).'
                ],
                'deployment' => [
                    'type' => 'string',
                    'description' => 'Optional deployment environment (e.g., cloud, on-premise, docker).'
                ],
                'ui_required' => [
                    'type' => 'boolean',
                    'description' => 'Whether a UI is required for the feature pack.'
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
                            'name' => ['type' => 'string', 'description' => 'Canonical identifier (slug/id)'],
                            'title' => ['type' => 'string', 'description' => 'Human readable title'],
                            'synopsis' => ['type' => 'string', 'description' => 'Short description'],
                            'package' => ['type' => 'string', 'description' => 'Composer package name (deprecated in 0.4)'],
                            'tier' => ['type' => 'string', 'description' => 'community or commercial'],
                            'license_enforcement' => ['type' => 'string', 'description' => 'none, required, or optional'],
                            'category' => ['type' => 'string', 'description' => 'Feature pack category'],
                            'version' => ['type' => 'string', 'description' => 'The specific version of the feature pack'],
                            'score' => ['type' => 'number', 'description' => 'Relevance score'],
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
                                    'email' => ['type' => 'string'],
                                    'url' => ['type' => 'string'],
                                    'trust_tier' => ['type' => 'string', 'description' => 'community or hardware'],
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
        $config = RegistryToolHelper::getConfig($this->context);
        if (isset($config['registry_url'])) {
            return (string)$config['registry_url'];
        }

        return self::DEFAULT_REGISTRY_URL;
    }

    public function execute(array $input): array
    {
        try {
            $registryUrl = $this->getRegistryUrl();

            // Check if we need the base for some API calls or keep it as is
            // RegistryToolHelper::getRegistryBaseUrl($this->context); 
            // but here we need the full JSON URL.

            // Append context parameters to the URL for scoring/filtering
            $params = [];
            if (isset($input['query'])) $params['query'] = $input['query'];
            if (isset($input['category'])) $params['category'] = $input['category'];
            if (isset($input['project_type'])) $params['project_type'] = $input['project_type'];
            if (isset($input['deployment'])) $params['deployment'] = $input['deployment'];
            if (isset($input['ui_required'])) $params['ui_required'] = $input['ui_required'] ? '1' : '0';

            // Filter out query/category from URL params if the target is a local file
            $isLocalFile = (str_starts_with($registryUrl, '/') || str_contains($registryUrl, ':\\') || str_contains($registryUrl, ':/'));
            
            if (!empty($params) && !$isLocalFile) {
                $separator = (str_contains($registryUrl, '?')) ? '&' : '?';
                $registryUrl .= $separator . http_build_query($params);
            }

            // Note: In a real-world scenario, we'd use a more robust HTTP client and caching.
            // For now, simple file_get_contents with a timeout should suffice for demonstration.
            $context = stream_context_create([
                'http' => ['timeout' => 5]
            ]);
            
            $content = @file_get_contents($registryUrl, false, $context);
            
            if ($content === false) {
                $error = error_get_last();
                $errorMessage = 'Could not fetch registry from ' . $registryUrl;
                if ($error) {
                    $errorMessage .= ': ' . $error['message'];
                }
                
                // Add DNS diagnostic info if it's a connection failure
                $host = parse_url($registryUrl, PHP_URL_HOST);
                if ($host) {
                    $ip = gethostbyname($host);
                    $errorMessage .= " (Host: $host, Resolved IP: $ip)";
                }

                return [
                    'features' => [],
                    'error' => $errorMessage
                ];
            }

            $data = json_decode($content, true);
            if ($data === null && json_last_error() !== JSON_ERROR_NONE) {
                $jsonError = json_last_error_msg();
                // Fallback for XML if it's still returning XML
                if (str_starts_with(trim($content), '<')) {
                    try {
                        $xmlResult = $this->parseXml($content, $input);
                        if (!empty($xmlResult['features'])) {
                            return $xmlResult;
                        }
                    } catch (Exception $e) {
                        // Ignore XML error if JSON was expected
                    }
                }

                return [
                    'features' => [],
                    'error' => "JSON Decode Error: $jsonError. Raw response starts with: " . substr(trim($content), 0, 100)
                ];
            }

            if ($data === null) {
                return [
                    'features' => [],
                    'error' => 'Registry returned empty or invalid response.'
                ];
            }

            return $this->parseJson($data, $input);

        } catch (Exception $e) {
            return [
                'features' => [],
                'error' => 'Error processing registry: ' . $e->getMessage()
            ];
        }
    }

    private function parseJson(array $data, array $input): array
    {
        $features = [];
        $query = isset($input['query']) ? strtolower($input['query']) : null;
        $version = $data['registryVersion'] ?? '0.2';

        $packs = $data['result']['packs'] ?? $data['packs'] ?? [];

        foreach ($packs as $pack) {
            if ($version === '0.4') {
                $name = $pack['slug'] ?? '';
                $title = $pack['title'] ?? $name;
                $description = $pack['description'] ?? '';
                $package = ''; // Removed in 0.4
                $license = $pack['license_type'] ?? 'community';
                $enforcement = $pack['license_enforcement'] ?? 'none';
                $vendor = $pack['vendor'] ?? [];
                $vendorName = $vendor['name'] ?? '';
                $vendorEmail = $vendor['email'] ?? '';
                $vendorUrl = $vendor['url'] ?? '';
                $trustTier = $vendor['trust_tier'] ?? 'community';
                $version_num = $pack['version'] ?? '1.0.0';
                $download = $pack['download'] ?? '';
                $capabilities = $pack['capabilities'] ?? [];
                $category = $pack['category'] ?? '';
                $score = $pack['score'] ?? 0;
            } else {
                // Legacy 0.2
                $name = $pack['name'] ?? '';
                $title = $name;
                $description = $pack['description'] ?? '';
                $package = $pack['package'] ?? $name;
                $license = $pack['license'] ?? 'community';
                $enforcement = 'none';
                $vendorName = $pack['vendor'] ?? '';
                $vendorEmail = '';
                $vendorUrl = '';
                $trustTier = 'community';
                $version_num = $pack['version'] ?? '1.0.0';
                $download = $pack['download'] ?? '';
                $capabilities = $pack['capabilities'] ?? [];
                $category = $pack['category'] ?? '';
                $score = 0;
            }

            // Filtering by query (name, description or capabilities)
            if ($query) {
                $match = str_contains(strtolower($name), $query) ||
                         str_contains(strtolower($title), $query) ||
                         str_contains(strtolower($description), $query) ||
                         str_contains(strtolower($package), $query) ||
                         in_array($query, array_map('strtolower', $capabilities));
                
                if (!$match) continue;
            }

            $features[] = [
                'name' => $name,
                'title' => $title,
                'synopsis' => $description,
                'package' => $package,
                'tier' => $license,
                'license_enforcement' => $enforcement,
                'category' => $category,
                'version' => $version_num,
                'score' => $score,
                'distribution' => [
                    'url' => $download,
                ],
                'capabilities' => $capabilities,
                'author' => [
                    'name' => $vendorName,
                    'email' => $vendorEmail,
                    'url' => $vendorUrl,
                    'trust_tier' => $trustTier,
                ],
            ];
        }

        // Sort by score descending if scores are present
        usort($features, function($a, $b) {
            return $b['score'] <=> $a['score'];
        });

        return ['features' => $features];
    }

    private function parseXml(string $xmlContent, array $input): array
    {
        $xml = new SimpleXMLElement($xmlContent);
        $features = [];

        $query = isset($input['query']) ? strtolower($input['query']) : null;

        foreach ($xml->{'feature-pack'} as $fp) {
            $id = (string)$fp->id;
            $vendor = (string)$fp->vendor;
            $license = (string)$fp->license;
            $download = (string)$fp->download;
            $version_num = (string)($fp->version ?? '1.0.0');
            $description = (string)($fp->description ?? '');
            
            $capabilities = [];
            if (isset($fp->capabilities)) {
                foreach ($fp->capabilities->capability as $cap) {
                    $capabilities[] = (string)$cap['id'];
                }
            }

            // Filtering by query (id, description or capabilities)
            if ($query) {
                $match = str_contains(strtolower($id), $query) ||
                         str_contains(strtolower($description), $query) ||
                         in_array($query, array_map('strtolower', $capabilities));
                
                if (!$match) continue;
            }

            $features[] = [
                'name' => $id,
                'title' => $id,
                'synopsis' => $description,
                'package' => $id,
                'tier' => $license,
                'version' => $version_num,
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
    }
}
