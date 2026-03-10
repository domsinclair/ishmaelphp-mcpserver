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
         * Opens the given URL in the default system browser.
         */
        public static function openBrowser(string $url): void
        {
            if (PHP_OS_FAMILY === "Windows") {
                // Use PowerShell to start the browser.
                // Using -WindowStyle Hidden can sometimes cause issues in certain environments
                // where the shell might fail to spawn the process if it's already "hidden" or
                // lack focus. We'll use a more robust way to escape and launch.
                // We also need to escape $ symbols as PowerShell will try to interpolate them
                // even in single-quoted strings if the outer command is double-quoted.
                $escapedUrl = str_replace(["'", "$"], ["''", "`$"], $url);
                $command = "powershell -NoProfile -ExecutionPolicy Bypass -Command \"Start-Process '$escapedUrl'\"";
                @shell_exec($command);
            } elseif (PHP_OS_FAMILY === "Darwin") {
                @shell_exec('open ' . escapeshellarg($url));
            } else {
                @shell_exec('xdg-open ' . escapeshellarg($url) . ' &');
            }
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

        /**
         * Captures a token from a local listener.
         *
         * @param resource $server The server resource from startListener.
         * @param int $timeout Timeout in seconds.
         * @return array|null The captured data or null on timeout.
         */
        public static function captureToken($server, int $timeout = 120): ?array
        {
            $start = time();
            $resultData = null;

            while (time() - $start < $timeout) {
                $read = [$server];
                $write = null;
                $except = null;
                if (stream_select($read, $write, $except, 1) > 0) {
                    $conn = stream_socket_accept($server);
                    if ($conn) {
                        $request = fread($conn, 4096);
                        if ($request && preg_match("/GET \/callback\?(.*?) HTTP/i", $request, $matches)) {
                            parse_str($matches[1], $resultData);

                            $tier = $resultData['tier'] ?? 'B';
                            $tierName = ($tier === 'A' || $tier === 'hardware') ? 'Tier A (Hardware)' : 'Tier B (Community)';

                            $responseBody = "<html><head><title>Ishmael Registry</title><style>body { font-family: sans-serif; display: flex; align-items: center; justify-content: center; height: 100vh; margin: 0; background: #f4f4f4; } .card { background: white; padding: 2rem; border-radius: 8px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); font-size: 1.1rem; text-align: center; } h1 { color: #2c3e50; }</style></head><body><div class='card'><h1>Authentication Successful</h1><p>Token captured successfully.</p><p>Trust Level: <strong>$tierName</strong></p><p>You can close this window now.</p></div></body></html>";
                            $response = "HTTP/1.1 200 OK\r\n";
                            $response .= "Content-Type: text/html\r\n";
                            $response .= "Content-Length: " . strlen($responseBody) . "\r\n";
                            $response .= "Connection: close\r\n\r\n";
                            $response .= $responseBody;

                            fwrite($conn, $response);
                            fclose($conn);
                            return $resultData;
                        }
                        fclose($conn);
                    }
                }
            }
            return null;
        }
    }
