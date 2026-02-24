<?php

namespace Ishmael\McpServer\Tools;

use Ishmael\McpServer\Contracts\Tool;
use Ishmael\McpServer\Project\ProjectContext;
use Ishmael\McpServer\Support\RegistryToolHelper;

/**
 * Tool to register a new vendor on the Ishmael Registry.
 */
class VendorRegisterTool implements Tool
{
    private ProjectContext $context;

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
                "vendor" => ["type" => "string"],
                "registered" => ["type" => "boolean"],
                "tier" => ["type" => "string", "description" => "A (Hardware) or B (Community)"],
                "token" => ["type" => "string", "description" => "Optional 10-minute Upload Token"],
                "authUrl" => ["type" => "string"]
            ]
        ];
    }

    public function execute(array $input): array
    {
        $registryUrl = isset($input["registryUrl"]) ? (string)$input["registryUrl"] : RegistryToolHelper::getRegistryBaseUrl($this->context);
        $port = $input["port"] ?? RegistryToolHelper::getListenerPort($this->context, 8080);

        $config = RegistryToolHelper::getConfig($this->context);
        $registerBaseUrl = isset($config['registry_register_url']) ? (string)$config['registry_register_url'] : rtrim($registryUrl, '/') . "/auth/register";

        // Start listener with port discovery
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
        if (!empty($input["name"])) $params["name"] = $input["name"];
        if (!empty($input["email"])) $params["email"] = $input["email"];
        if (!empty($input["url"])) $params["url"] = $input["url"];

        $authUrl = $registerBaseUrl . (str_contains($registerBaseUrl, '?') ? '&' : '?') . http_build_query($params);

        $noBrowser = (bool)($input["noBrowser"] ?? (getenv('ISH_MCP_NO_BROWSER') === '1'));

        // Try to open browser early
        if (!$noBrowser && PHP_OS_FAMILY === "Windows") {
            @shell_exec('powershell -WindowStyle Hidden -Command Start-Process ' . escapeshellarg($authUrl));
        }

        $start = time();
        $timeout = 300; // Increased timeout for two-step registration
        $resultData = null;

        while (time() - $start < $timeout) {
            $client = @stream_socket_accept($server, 1);
            if ($client) {
                $request = fread($client, 2048);
                if ($request && preg_match("/GET \/callback\?(.*?) HTTP/i", $request, $matches)) {
                    parse_str($matches[1], $resultData);

                    $vendorName = htmlspecialchars($resultData['vendor'] ?? 'Unknown');
                    $tier = $resultData['tier'] ?? 'B';
                    $tierName = ($tier === 'A') ? 'Tier A (Hardware)' : 'Tier B (Community)';

                    $responseBody = "<html><head><title>Ishmael Registry</title><style>body { font-family: sans-serif; display: flex; align-items: center; justify-content: center; height: 100vh; margin: 0; background: #f4f4f4; } .card { background: white; padding: 2rem; border-radius: 8px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); font-size: 1.1rem; } h1 { color: #2c3e50; }</style></head><body><div class='card'><h1>Registration Successful</h1><p>Vendor: <strong>$vendorName</strong></p><p>Trust Level: <strong>$tierName</strong></p><p>You can close this window and return to your IDE.</p></div></body></html>";
                    error_log("[VendorRegisterTool] Callback received for vendor: $vendorName");
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

        if ($resultData) {
            $tier = $resultData["tier"] ?? "B";
            $tierLabel = ($tier === "A") ? "Tier A (Hardware)" : "Tier B (Community)";
            
            return [
                "success" => true,
                "message" => "Vendor registration successful as $tierLabel.",
                "vendor" => $resultData["vendor"] ?? null,
                "registered" => ($resultData["registered"] ?? "0") === "1",
                "tier" => $tier,
                "token" => $resultData["token"] ?? null,
                "authUrl" => $authUrl
            ];
        }

        return [
            "success" => false,
            "message" => "Registration timed out. Ensure you completed the form in your browser.",
            "authUrl" => $authUrl
        ];
    }

}
