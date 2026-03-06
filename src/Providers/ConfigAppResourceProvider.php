<?php

declare(strict_types=1);

namespace Ishmael\McpServer\Providers;

use Ishmael\McpServer\Contracts\ResourceProvider;
use Ishmael\McpServer\Project\ProjectContext;

/**
 * Exposes the application configuration (ish://config/app).
 * Reads config/app.php and relevant .env values from the project.
 */
final class ConfigAppResourceProvider implements ResourceProvider
{
    private ProjectContext $context;

    public function __construct(ProjectContext $context)
    {
        $this->context = $context;
    }

    public function listResources(): array
    {
        return [
            [
                'uri' => 'ish://config/app',
                'name' => 'Ishmael Application Configuration',
                'description' => 'Application configuration from config/app.php and related .env values',
                'mimeType' => 'application/json',
            ],
        ];
    }

    public function readResource(string $uri): ?string
    {
        if ($uri !== 'ish://config/app') {
            return null;
        }

        $root = $this->context->getRoot();
        if ($root === null) {
            return json_encode(['error' => 'Project root not found']);
        }

        $result = [];

        // Read config/app.php if it exists
        $configPath = $root . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'app.php';
        if (is_file($configPath)) {
            try {
                $configData = require $configPath;
                if (is_array($configData)) {
                    $result['config'] = $configData;
                } else {
                    $result['config'] = ['_raw' => 'Config file did not return an array'];
                }
            } catch (\Throwable $e) {
                $result['config'] = [
                    'error' => 'Failed to load config/app.php',
                    'message' => $e->getMessage(),
                ];
            }
        } else {
            $result['config'] = ['error' => 'config/app.php not found'];
        }

        // Read .env values if .env exists
        $envPath = $root . DIRECTORY_SEPARATOR . '.env';
        if (is_file($envPath)) {
            $envValues = $this->parseEnvFile($envPath);
            // Filter to only include app-related env vars (avoid exposing secrets)
            $result['env'] = $this->filterSafeEnvValues($envValues);
        } else {
            $result['env'] = ['error' => '.env file not found'];
        }

        return json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }

    /**
     * Parse a .env file into key-value pairs.
     */
    private function parseEnvFile(string $path): array
    {
        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $env = [];

        foreach ($lines as $line) {
            $line = trim($line);
            // Skip comments
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }

            // Parse KEY=value
            if (str_contains($line, '=')) {
                [$key, $value] = explode('=', $line, 2);
                $key = trim($key);
                $value = trim($value);

                // Remove quotes if present
                if ((str_starts_with($value, '"') && str_ends_with($value, '"')) ||
                    (str_starts_with($value, "'") && str_ends_with($value, "'"))) {
                    $value = substr($value, 1, -1);
                }

                $env[$key] = $value;
            }
        }

        return $env;
    }

    /**
     * Filter env values to only include safe, non-secret keys.
     * Redacts sensitive values like passwords, keys, and secrets.
     */
    private function filterSafeEnvValues(array $env): array
    {
        $sensitivePatterns = [
            'PASSWORD', 'SECRET', 'KEY', 'TOKEN', 'PRIVATE',
            'CREDENTIAL', 'AUTH', 'API_KEY', 'ACCESS_KEY',
        ];

        $filtered = [];
        foreach ($env as $key => $value) {
            $isSensitive = false;
            foreach ($sensitivePatterns as $pattern) {
                if (stripos($key, $pattern) !== false) {
                    $isSensitive = true;
                    break;
                }
            }

            if ($isSensitive) {
                $filtered[$key] = '[REDACTED]';
            } else {
                $filtered[$key] = $value;
            }
        }

        return $filtered;
    }
}
