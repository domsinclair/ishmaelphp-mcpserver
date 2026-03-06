<?php
declare(strict_types=1);

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
                "noBrowser" => ["type" => "boolean", "description" => "If true, skips server-initiated browser launch.", "default" => false],
                "noListener" => ["type" => "boolean", "description" => "If true, skip the local TCP listener and just return the auth URL.", "default" => false]
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

        $noListener = (bool)($input["noListener"] ?? false);
        $noBrowser = (bool)($input["noBrowser"] ?? (getenv('ISH_MCP_NO_BROWSER') === '1'));
        $actualPort = $port;
        $server = null;
        $listenerFailed = false;

        // Try to start listener for automatic token capture
        if (!$noListener) {
            $listener = RegistryToolHelper::startListener($port);
            if ($listener) {
                [$server, $actualPort] = $listener;
            } else {
                $listenerFailed = true;
            }
        }

        // Build URL params - only include redirect_uri if we have a working listener
        $params = [];
        if ($server !== null) {
            $params["redirect_uri"] = "http://localhost:$actualPort/callback";
        }
        
        if (!empty($input["module"])) $params["module"] = $input["module"];
        if (!empty($input["vendor"])) $params["vendor"] = $input["vendor"];
        if (!empty($input["upgrade"])) $params["upgrade"] = "1";

        $authUrl = $authBaseUrl . (str_contains($authBaseUrl, '?') ? '&' : '?') . http_build_query($params);

        // ALWAYS try to open browser (unless explicitly disabled)
        if (!$noBrowser) {
            if (PHP_OS_FAMILY === "Windows") {
                @shell_exec('powershell -WindowStyle Hidden -Command Start-Process ' . escapeshellarg($authUrl));
            } elseif (PHP_OS_FAMILY === "Darwin") {
                @shell_exec('open ' . escapeshellarg($authUrl));
            } else {
                @shell_exec('xdg-open ' . escapeshellarg($authUrl) . ' &');
            }
        }

        // If no listener available, return with manual flow (browser already opened)
        if ($server === null) {
            $message = $noBrowser 
                ? "Authentication URL generated. Please open it in your browser and copy the token."
                : "Browser opened for authentication. Complete the login there and copy the token provided.";
            if ($listenerFailed) {
                $message .= " (Note: Automatic token capture unavailable - ports 8080-8085 in use.)";
            }
            return [
                "success" => true,
                "message" => $message,
                "authUrl" => $authUrl,
                "manual" => true
            ];
        }

        // Wait for automatic token capture via listener
        $resultData = RegistryToolHelper::captureToken($server, 120);
        if ($server) {
            fclose($server);
        }

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
