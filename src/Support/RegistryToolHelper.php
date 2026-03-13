<?php
    declare(strict_types=1);

    namespace Ishmael\McpServer\Support;

    use Ishmael\McpServer\Project\ProjectContext;

    /**
     * Helper for tools interacting with the Ishmael Registry.
     * 
     * This helper provides configuration discovery and browser launching utilities.
     * Token acquisition is handled via manual copy/paste from the registry web UI
     * to avoid fragile TCP listener-based flows.
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
         * Builds the authentication URL for the registry.
         *
         * @param ProjectContext $context The project context
         * @param string|null $module Optional module name to include in the URL
         * @param string|null $vendor Optional vendor name to prefill
         * @param bool $upgrade Whether to force hardware key upgrade flow
         * @param string|null $registryUrl Optional registry URL override
         * @return string The full authentication URL
         */
        public static function buildAuthUrl(
            ProjectContext $context,
            ?string $module = null,
            ?string $vendor = null,
            bool $upgrade = false,
            ?string $registryUrl = null
        ): string {
            $config = self::getConfig($context);
            $baseUrl = $registryUrl ?? self::getRegistryBaseUrl($context);
            $authBaseUrl = $config['registry_auth_url'] ?? rtrim($baseUrl, '/') . "/auth/publish";

            $params = [];
            if ($module) {
                $params['module'] = $module;
            }
            if ($vendor) {
                $params['vendor'] = $vendor;
            }
            if ($upgrade) {
                $params['upgrade'] = '1';
            }

            $queryString = http_build_query($params);
            if ($queryString) {
                return $authBaseUrl . (str_contains($authBaseUrl, '?') ? '&' : '?') . $queryString;
            }

            return $authBaseUrl;
        }

        /**
         * Opens the given URL in the default system browser.
         */
        public static function openBrowser(string $url): void
        {
            if (PHP_OS_FAMILY === "Windows") {
                // Use PowerShell to start the browser.
                // We need to escape $ symbols as PowerShell will try to interpolate them.
                $escapedUrl = str_replace(["'", "$"], ["''", "`$"], $url);
                $command = "powershell -NoProfile -ExecutionPolicy Bypass -Command \"Start-Process '$escapedUrl'\"";
                @shell_exec($command);
            } elseif (PHP_OS_FAMILY === "Darwin") {
                @shell_exec('open ' . escapeshellarg($url));
            } else {
                @shell_exec('xdg-open ' . escapeshellarg($url) . ' &');
            }
        }
    }
