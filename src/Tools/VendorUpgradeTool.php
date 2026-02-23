<?php
declare(strict_types=1);

namespace Ishmael\McpServer\Tools;

use Ishmael\McpServer\Contracts\Tool;
use Ishmael\McpServer\Project\ProjectContext;
use Ishmael\McpServer\Support\RegistryToolHelper;

/**
 * Tool to promote a vendor to Tier A (Hardware) by registering security keys.
 */
class VendorUpgradeTool implements Tool
{
    private ProjectContext $context;

    public function __construct(ProjectContext $context)
    {
        $this->context = $context;
    }

    public function getName(): string
    {
        return "vendor:upgrade";
    }

    public function getDescription(): string
    {
        return "Promote a vendor to Tier A (Hardware) by registering security keys in the Ishmael Registry.";
    }

    public function getInputSchema(): array
    {
        return [
            "type" => "object",
            "required" => ["vendor"],
            "properties" => [
                "vendor" => ["type" => "string", "description" => "The vendor name (slug) to upgrade"],
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
                "tier" => ["type" => "string", "description" => "The new trust tier (A or B)"],
                "authUrl" => ["type" => "string"]
            ]
        ];
    }

    public function execute(array $input): array
    {
        $vendor = (string)$input["vendor"];
        $registryUrl = isset($input["registryUrl"]) ? (string)$input["registryUrl"] : RegistryToolHelper::getRegistryBaseUrl($this->context);
        $port = $input["port"] ?? RegistryToolHelper::getListenerPort($this->context, 8080);
        
        $config = RegistryToolHelper::getConfig($this->context);
        $upgradeBaseUrl = isset($config['registry_upgrade_url']) ? (string)$config['registry_upgrade_url'] : rtrim($registryUrl, '/') . "/auth/keys/setup";

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
            "upgrade" => "1",
            "vendor" => $vendor,
            "redirect_uri" => $redirectUri
        ];

        $authUrl = $upgradeBaseUrl . (str_contains($upgradeBaseUrl, '?') ? '&' : '?') . http_build_query($params);

        if (PHP_OS_FAMILY === "Windows") {
            @shell_exec("start " . escapeshellarg($authUrl));
        }

        $start = time();
        $timeout = 180; // Longer timeout for key registration
        $resultData = null;

        while (time() - $start < $timeout) {
            $client = @stream_socket_accept($server, 1);
            if ($client) {
                $request = fread($client, 2048);
                if ($request && preg_match("/GET \/callback\?(.*?) HTTP/i", $request, $matches)) {
                    parse_str($matches[1], $resultData);

                    $tier = $resultData['tier'] ?? 'B';
                    $tierName = ($tier === 'A') ? 'Tier A (Hardware)' : 'Tier B (Community)';

                    $responseBody = "<html><head><title>Ishmael Registry</title><style>body { font-family: sans-serif; display: flex; align-items: center; justify-content: center; height: 100vh; margin: 0; background: #f4f4f4; } .card { background: white; padding: 2rem; border-radius: 8px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); font-size: 1.1rem; } h1 { color: #2c3e50; }</style></head><body><div class='card'><h1>Security Setup Complete</h1><p>Trust Level: <strong>$tierName</strong></p><p>Your vendor account has been updated. You can close this window.</p></div></body></html>";
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
            $tier = $resultData['tier'] ?? 'B';
            $success = ($tier === 'A');
            return [
                "success" => true,
                "message" => $success ? "Vendor promoted to Tier A (Hardware)." : "Security setup completed, but remains Tier B.",
                "tier" => $tier
            ];
        }

        return [
            "success" => false,
            "message" => "Upgrade timed out or failed.",
            "authUrl" => $authUrl
        ];
    }
}
