<?php

namespace Ishmael\McpServer\Tools;

use Ishmael\McpServer\Contracts\Tool;
use Ishmael\McpServer\Project\ProjectContext;

/**
 * Tool to register a new vendor on the Ishmael Registry.
 */
class VendorRegisterTool implements Tool
{
    private ProjectContext $context;
    private const DEFAULT_REGISTRY_URL = "http://vtl-ishmael-registry.test";

    public function __construct(ProjectContext $context)
    {
        $this->context = $context;
    }

    public function getName(): string
    {
        return "vendor:register";
    }

    public function getDescription(): string
    {
        return "Registers a new vendor on the Ishmael Registry and captures the confirmation via a local listener.";
    }

    public function getInputSchema(): array
    {
        return [
            "type" => "object",
            "properties" => [
                "name" => ["type" => "string", "description" => "Vendor name (slug-style)"],
                "email" => ["type" => "string", "description" => "Developer email"],
                "url" => ["type" => "string", "description" => "Developer website"],
                "port" => ["type" => "integer", "description" => "Local listener port", "default" => 8080],
                "registryUrl" => ["type" => "string", "description" => "Registry base URL override"]
            ]
        ];
    }

    public function getOutputSchema(): array
    {
        return [
            "type" => "object",
            "properties" => [
                "success" => ["type" => "boolean"],
                "message" => ["type" => "string"],
                "vendor" => ["type" => "string"],
                "registered" => ["type" => "boolean"],
                "authUrl" => ["type" => "string"]
            ]
        ];
    }

    public function execute(array $input): array
    {
        $port = $input["port"] ?? 8080;
        $registryUrl = isset($input["registryUrl"]) ? (string)$input["registryUrl"] : $this->getRegistryBaseUrl();
        $redirectUri = "http://localhost:$port/callback";

        $params = [
            "redirect_uri" => $redirectUri
        ];
        if (!empty($input["name"])) $params["name"] = $input["name"];
        if (!empty($input["email"])) $params["email"] = $input["email"];
        if (!empty($input["url"])) $params["url"] = $input["url"];

        $authUrl = rtrim($registryUrl, '/') . "/auth/register?" . http_build_query($params);

        // Start listener
        $server = @stream_socket_server("tcp://127.0.0.1:$port", $errno, $errstr);
        if (!$server) {
            return [
                "success" => false,
                "message" => "Could not start local listener on port $port: $errstr ($errno). You may need to use a different port.",
                "authUrl" => $authUrl
            ];
        }

        stream_set_blocking($server, false);

        // Try to open browser early
        if (PHP_OS_FAMILY === "Windows") {
            @shell_exec("start " . escapeshellarg($authUrl));
        }

        $start = time();
        $timeout = 60; // 60 seconds
        $resultData = null;

        while (time() - $start < $timeout) {
            $client = @stream_socket_accept($server, 1); // 1 second timeout for accept
            if ($client) {
                $request = fread($client, 2048);
                if ($request && preg_match("/GET \/callback\?(.*?) HTTP/i", $request, $matches)) {
                    parse_str($matches[1], $resultData);

                    $responseBody = "<html><head><title>Ishmael Registry</title><style>body { font-family: sans-serif; display: flex; align-items: center; justify-content: center; height: 100vh; margin: 0; background: #f4f4f4; } .card { background: white; padding: 2rem; border-radius: 8px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); }</style></head><body><div class='card'><h1>Registration Successful</h1><p>Vendor: " . htmlspecialchars($resultData['vendor'] ?? 'Unknown') . "</p><p>You can close this window now.</p></div></body></html>";
                    $response = "HTTP/1.1 200 OK\r\n";
                    $response .= "Content-Type: text/html\r\n";
                    $response .= "Content-Length: " . strlen($responseBody) . "\r\n";
                    $response .= "Connection: close\r\n\r\n";
                    $response .= $responseBody;

                    fwrite($client, $response);
                    fclose($client);
                    break;
                }
                fclose($client);
            }
            usleep(100000); // 100ms
        }
        fclose($server);

        if ($resultData) {
            return [
                "success" => true,
                "message" => "Vendor registration successful.",
                "vendor" => $resultData["vendor"] ?? null,
                "registered" => ($resultData["registered"] ?? "0") === "1"
            ];
        }

        return [
            "success" => false,
            "message" => "Registration timed out. Ensure you completed the form in your browser.",
            "authUrl" => $authUrl
        ];
    }

    private function getRegistryBaseUrl(): string
    {
        if ($this->context->getRoot() !== null) {
            $configPath = $this->context->getRoot() . DIRECTORY_SEPARATOR . "config" . DIRECTORY_SEPARATOR . "app.php";
            if (is_file($configPath)) {
                $content = file_get_contents($configPath);
                if (preg_match("/'registry_url'\s*=>\s*['\"](.*?)['\"]/", $content, $matches)) {
                    $url = $matches[1];
                    // If it ends with /api/..., we want the base
                    if (strpos($url, '/api') !== false) {
                        return rtrim(substr($url, 0, strpos($url, '/api')), '/');
                    }
                    return rtrim($url, '/');
                }
            }
        }
        return self::DEFAULT_REGISTRY_URL;
    }
}
