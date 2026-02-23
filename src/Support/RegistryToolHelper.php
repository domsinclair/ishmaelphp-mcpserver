<?php
declare(strict_types=1);

namespace Ishmael\McpServer\Support;

use Ishmael\McpServer\Project\ProjectContext;

/**
 * Helper for tools interacting with the Ishmael Registry.
 */
final class RegistryToolHelper
{
    private const DEFAULT_REGISTRY_URL = "https://vtl-ishmael-registry.test";

    /**
     * Loads the project configuration if available.
     * 
     * @return array<string, mixed>
     */
    public static function getConfig(ProjectContext $context): array
    {
        if ($context->getRoot() === null) {
            return [];
        }

        $configPath = $context->getRoot() . DIRECTORY_SEPARATOR . "config" . DIRECTORY_SEPARATOR . "app.php";
        if (!is_file($configPath)) {
            return [];
        }

        // Define helper functions if they don't exist before requiring
        if (!function_exists('env')) {
            eval('function env($key, $default = null) { return $_ENV[$key] ?? $_SERVER[$key] ?? $default; }');
        }
        if (!function_exists('base_path')) {
            eval('function base_path($path = "") { return $path; }');
        }

        try {
            $config = require $configPath;
            return is_array($config) ? $config : [];
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * Discovers the Registry Base URL from the project configuration.
     */
    public static function getRegistryBaseUrl(ProjectContext $context): string
    {
        $config = self::getConfig($context);

        if (isset($config['registry_base_url'])) {
            return rtrim((string)$config['registry_base_url'], '/');
        }

        if (isset($config['registry_url'])) {
            $url = (string)$config['registry_url'];
            
            // Strip API or Registry specific paths to get the base URL
            if (($pos = strpos($url, '/api')) !== false) {
                return rtrim(substr($url, 0, $pos), '/');
            }
            if (($pos = strpos($url, '/registry')) !== false) {
                return rtrim(substr($url, 0, $pos), '/');
            }
            
            return rtrim($url, '/');
        }

        return self::DEFAULT_REGISTRY_URL;
    }

    /**
     * Returns the default listener port from config or fallback.
     */
    public static function getListenerPort(ProjectContext $context, int $default = 8080): int
    {
        $config = self::getConfig($context);
        return isset($config['registry_listener_port']) ? (int)$config['registry_listener_port'] : $default;
    }

    /**
     * Starts a local TCP server on an available port starting from $startPort.
     * 
     * @return array{0: resource, 1: int}|null Returns [server_resource, actual_port] or null on failure.
     */
    public static function startListener(int $startPort, int $maxAttempts = 5): ?array
    {
        for ($i = 0; $i < $maxAttempts; $i++) {
            $port = $startPort + $i;
            $server = @stream_socket_server("tcp://127.0.0.1:$port", $errno, $errstr);
            if ($server) {
                stream_set_blocking($server, false);
                return [$server, $port];
            }
        }
        return null;
    }
}
