<?php

namespace Ishmael\McpServer\Tools;

use Ishmael\McpServer\Contracts\Tool;
use Ishmael\McpServer\Project\ProjectContext;
use Ishmael\McpServer\Support\RegistryToolHelper;

/**
 * Tool to perform authentication with Ishmael Registry.
 */
class VendorAuthenticateTool implements Tool
{
    private ProjectContext $context;

    public function __construct(ProjectContext $context)
    {
        $this->context = $context;
    }

    public function getName(): string
    {
        return "vendor:authenticate";
    }

    public function getDescription(): string
    {
        return "Obtain an upload token from the Ishmael Registry (supports both Hardware and Community tiers).";
    }

    public function getInputSchema(): array
    {
        return [
            "type" => "object",
            "properties" => [
                "module" => ["type" => "string", "description" => "Module name for which to get a token"],
                "vendor" => ["type" => "string", "description" => "Optional vendor name to prefill"],
                "upgrade" => ["type" => "boolean", "description" => "If true, forces hardware key registration flow", "default" => false],
                "port" => ["type" => "integer", "description" => "Local listener port", "default" => 8080],
                "registryUrl" => ["type" => "string", "description" => "Registry base URL override"],
                "noBrowser" => ["type" => "boolean", "description" => "If true, skips server-initiated browser launch.", "default" => false]
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
                "token" => ["type" => "string"],
                "tier" => ["type" => "string", "description" => "A (Hardware) or B (Community)"],
                "authUrl" => ["type" => "string"]
            ]
        ];
    }

    public function execute(array $input): array
    {
        $registryUrl = isset($input["registryUrl"]) ? (string)$input["registryUrl"] : RegistryToolHelper::getRegistryBaseUrl($this->context);
        $port = $input["port"] ?? RegistryToolHelper::getListenerPort($this->context, 8080);
        
        $config = RegistryToolHelper::getConfig($this->context);
        $authBaseUrl = isset($config['registry_auth_url']) ? (string)$config['registry_auth_url'] : rtrim($registryUrl, '/') . "/auth/publish";

        $listener = RegistryToolHelper::startListener($port);
        if (!$listener) {
            return [
                "success" => false,
                "message" => "Could not start local listener on port $port or nearby ports.",
            ];
        }

        [$server, $actualPort] = $listener;
        $redirectUri = "http://localhost:$actualPort/callback";

        $params = [
            "redirect_uri" => $redirectUri
        ];
        if (!empty($input["module"])) $params["module"] = $input["module"];
        if (!empty($input["vendor"])) $params["vendor"] = $input["vendor"];
        if (!empty($input["upgrade"])) $params["upgrade"] = "1";

        $authUrl = $authBaseUrl . (str_contains($authBaseUrl, '?') ? '&' : '?') . http_build_query($params);

        $noBrowser = (bool)($input["noBrowser"] ?? (getenv('ISH_MCP_NO_BROWSER') === '1'));

        // Try to open browser early
        if (!$noBrowser && PHP_OS_FAMILY === "Windows") {
            @shell_exec('powershell -WindowStyle Hidden -Command Start-Process ' . escapeshellarg($authUrl));
        }

        $start = time();
        $timeout = 120; 
        $resultData = null;

        while (time() - $start < $timeout) {
            $client = @stream_socket_accept($server, 1);
            if ($client) {
                $request = fread($client, 2048);
                if ($request && preg_match("/GET \/callback\?(.*?) HTTP/i", $request, $matches)) {
                    parse_str($matches[1], $resultData);

                    $tier = $resultData['tier'] ?? 'B';
                    $tierName = ($tier === 'A') ? 'Tier A (Hardware)' : 'Tier B (Community)';

                    $responseBody = "<html><head><title>Ishmael Registry</title><style>body { font-family: sans-serif; display: flex; align-items: center; justify-content: center; height: 100vh; margin: 0; background: #f4f4f4; } .card { background: white; padding: 2rem; border-radius: 8px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); font-size: 1.1rem; } h1 { color: #2c3e50; }</style></head><body><div class='card'><h1>Authentication Successful</h1><p>Token captured successfully.</p><p>Trust Level: <strong>$tierName</strong></p><p>You can close this window now.</p></div></body></html>";
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
            usleep(100000);
        }
        fclose($server);

        if ($resultData && isset($resultData['token'])) {
            return [
                "success" => true,
                "message" => "Authentication successful.",
                "token" => $resultData['token'],
                "tier" => $resultData['tier'] ?? 'B',
                "authUrl" => $authUrl
            ];
        }

        return [
            "success" => false,
            "message" => "Authentication timed out or failed. Ensure you completed the handshake in your browser.",
            "authUrl" => $authUrl
        ];
    }

}
