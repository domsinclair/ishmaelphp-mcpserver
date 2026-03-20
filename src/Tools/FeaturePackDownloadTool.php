<?php

declare(strict_types=1);

namespace Ishmael\McpServer\Tools;

use Ishmael\McpServer\Contracts\Tool;
use Ishmael\McpServer\Project\ProjectContext;
use Ishmael\McpServer\Support\RegistryToolHelper;

final class FeaturePackDownloadTool implements Tool
{
    private const DEFAULT_REGISTRY_BASE = 'https://vtl-ishmael-registry.test';
    private const USER_AGENT = 'Ishmael-MCP-Server/1.0 (automated)';

    private ?ProjectContext $context;

    public function __construct(?ProjectContext $context = null)
    {
        $this->context = $context;
    }

    public function getName(): string
    {
        return 'ish:featurePack:download';
    }

    public function getDescription(): string
    {
        return 'Download a feature pack ZIP from the Ishmael registry using the slug and version. Saves the file to the specified destination path.';
    }

    public function getInputSchema(): array
    {
        return [
            'type' => 'object',
            'additionalProperties' => false,
            'required' => ['slug', 'version'],
            'properties' => [
                'slug' => [
                    'type' => 'string',
                    'description' => 'The feature pack slug in the form {vendor}/{pack} (e.g. vtl-software/upload).',
                ],
                'version' => [
                    'type' => 'string',
                    'description' => 'The semantic version string to download (e.g. 1.0.0).',
                ],
                'destination' => [
                    'type' => 'string',
                    'description' => 'Absolute or relative path where the ZIP file should be saved. Defaults to the system temp directory.',
                ],
                'registry_url' => [
                    'type' => 'string',
                    'description' => 'Optional registry base URL override (e.g. https://registry.ishmael.io).',
                ],
                'insecure' => [
                    'type' => 'boolean',
                    'description' => 'Skip SSL certificate verification. Use only in development environments.',
                ],
            ],
        ];
    }

    public function getOutputSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['success'],
            'properties' => [
                'success'       => ['type' => 'boolean'],
                'file'          => ['type' => 'string', 'description' => 'Absolute path to the downloaded ZIP file.'],
                'size_bytes'    => ['type' => 'integer'],
                'download_url'  => ['type' => 'string'],
                'error'         => ['type' => 'string'],
            ],
        ];
    }

    public function execute(array $input): array
    {
        $slug    = trim($input['slug'] ?? '');
        $version = trim($input['version'] ?? '');

        if ($slug === '' || $version === '') {
            return ['success' => false, 'error' => 'slug and version are required.'];
        }

        $parts = explode('/', $slug, 2);
        if (count($parts) !== 2 || $parts[0] === '' || $parts[1] === '') {
            return ['success' => false, 'error' => 'slug must be in the form {vendor}/{pack}.'];
        }

        [$vendor, $pack] = $parts;

        $allowedPattern = '/^[a-zA-Z0-9\-_.]+$/';
        foreach (['vendor' => $vendor, 'pack' => $pack, 'version' => $version] as $field => $value) {
            if (!preg_match($allowedPattern, $value)) {
                return ['success' => false, 'error' => "Invalid characters in $field segment: $value"];
            }
        }

        $baseUrl     = $this->resolveBaseUrl($input['registry_url'] ?? null);
        $downloadUrl = rtrim($baseUrl, '/') . "/registry/download/{$vendor}/{$pack}/{$version}.zip";
        $insecure    = (bool)($input['insecure'] ?? (getenv('ISH_MCP_INSECURE_TLS') === '1'));

        $host           = parse_url($downloadUrl, PHP_URL_HOST);
        $isLocalDev     = $host && (
            str_ends_with($host, '.test') ||
            str_ends_with($host, '.local') ||
            str_ends_with($host, '.localhost') ||
            $host === 'localhost'
        );

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $downloadUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 120);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
        curl_setopt($ch, CURLOPT_USERAGENT, self::USER_AGENT);

        if ($insecure || $isLocalDev) {
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        }

        $body      = curl_exec($ch);
        $httpCode  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        $contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
        curl_close($ch);

        if ($body === false || $curlError !== '') {
            return [
                'success'      => false,
                'download_url' => $downloadUrl,
                'error'        => 'cURL error: ' . $curlError,
            ];
        }

        if ($httpCode >= 400) {
            $decoded = json_decode($body, true);
            $message = (is_array($decoded) && isset($decoded['error']))
                ? $decoded['error']
                : "HTTP $httpCode received from registry.";
            return [
                'success'      => false,
                'download_url' => $downloadUrl,
                'error'        => $message,
            ];
        }

        if (!str_contains((string)$contentType, 'application/zip') && !str_contains((string)$contentType, 'application/octet-stream')) {
            $decoded = json_decode($body, true);
            $message = (is_array($decoded) && isset($decoded['error']))
                ? $decoded['error']
                : "Unexpected content-type received: $contentType";
            return [
                'success'      => false,
                'download_url' => $downloadUrl,
                'error'        => $message,
            ];
        }

        $destination = $this->resolveDestination($input['destination'] ?? null, $pack, $version);

        $dir = dirname($destination);
        if (!is_dir($dir) && !mkdir($dir, 0755, true)) {
            return [
                'success'      => false,
                'download_url' => $downloadUrl,
                'error'        => "Could not create destination directory: $dir",
            ];
        }

        $written = file_put_contents($destination, $body);
        if ($written === false) {
            return [
                'success'      => false,
                'download_url' => $downloadUrl,
                'error'        => "Failed to write ZIP to: $destination",
            ];
        }

        return [
            'success'      => true,
            'file'         => $destination,
            'size_bytes'   => $written,
            'download_url' => $downloadUrl,
        ];
    }

    private function resolveBaseUrl(?string $override): string
    {
        if ($override !== null) {
            return $override;
        }

        if ($this->context !== null) {
            return RegistryToolHelper::getRegistryBaseUrl($this->context);
        }

        return self::DEFAULT_REGISTRY_BASE;
    }

    private function resolveDestination(?string $destination, string $pack, string $version): string
    {
        $filename = "{$pack}-{$version}.zip";

        if ($destination !== null && $destination !== '') {
            if (is_dir($destination)) {
                return rtrim($destination, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $filename;
            }
            return $destination;
        }

        return sys_get_temp_dir() . DIRECTORY_SEPARATOR . $filename;
    }
}
