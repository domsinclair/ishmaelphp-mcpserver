<?php
declare(strict_types=1);

namespace Ishmael\McpServer\Tools;

use Ishmael\McpServer\Contracts\Tool;
use Ishmael\McpServer\Project\ProjectContext;
use Ishmael\McpServer\Support\IshCliBridge;
use Ishmael\McpServer\Support\RegistryToolHelper;
use Exception;

/**
 * Publishes a feature pack to the centralized registry.
 * Implements Tiered Identity & Trust flow.
 */
final class FeaturePackPublishTool implements Tool
{
    private ProjectContext $context;
    private IshCliBridge $cli;

    private const DEFAULT_REGISTRY_URL = "https://vtl-ishmael-registry.test";

    public function __construct(ProjectContext $context)
    {
        $this->context = $context;
        $this->cli = new IshCliBridge($context);
    }

    public function getName(): string
    {
        return "ish-featurepack-publish";
    }

    public function getDescription(): string
    {
        return "Publishes a feature pack to the Ishmael Registry with Tiered Identity (WebAuthn/Standard).";
    }

    public function getInputSchema(): array
    {
        return [
            "type" => "object",
            "required" => ["module"],
            "properties" => [
                "module" => [
                    "type" => "string",
                    "description" => "The name of the module to publish (e.g., \"Blog\")."
                ],
                "token" => [
                    "type" => "string",
                    "description" => "The signed Upload Token obtained from the Registry Auth handshake."
                ],
                "registryUrl" => [
                    "type" => "string",
                    "description" => "Optional registry URL override."
                ],
            ],
        ];
    }

    public function getOutputSchema(): array
    {
        return [
            "type" => "object",
            "required" => ["success"],
            "properties" => [
                "success" => ["type" => "boolean"],
                "message" => ["type" => "string"],
                "authUrl" => ["type" => "string", "description" => "URL to open for authentication if token is missing."],
                "requiresAuth" => ["type" => "boolean"],
                "error" => ["type" => "string"],
            ],
        ];
    }

    public function execute(array $input): array
    {
        $module = (string)$input["module"];
        $token = isset($input["token"]) ? (string)$input["token"] : null;
        $registryUrl = isset($input["registryUrl"]) ? (string)$input["registryUrl"] : RegistryToolHelper::getRegistryBaseUrl($this->context);
        
        $config = RegistryToolHelper::getConfig($this->context);
        $registerUrl = isset($config['registry_register_url']) ? (string)$config['registry_register_url'] : rtrim($registryUrl, '/') . "/auth/register";
        $authUrl = isset($config['registry_auth_url']) ? (string)$config['registry_auth_url'] : rtrim($registryUrl, '/') . "/auth/publish";
        $uploadUrl = isset($config['registry_upload_url']) ? (string)$config['registry_upload_url'] : rtrim($registryUrl, '/') . "/api/publish/upload";

        if (!$token) {
            $authFlowUrl = $authUrl . "?module=" . urlencode($module);
            
            // Try to open the browser automatically on Windows
            if (PHP_OS_FAMILY === "Windows") {
                @shell_exec("start " . escapeshellarg($authFlowUrl));
            }

            return [
                "success" => true,
                "message" => "Authentication required. Please complete the handshake in your browser.",
                "authUrl" => $authFlowUrl,
                "requiresAuth" => true
            ];
        }

        // 1. Ensure the module is packed
        $packResult = $this->cli->execute("feature:pack", [], [$module]);
        if (!$packResult["success"]) {
            return [
                "success" => false,
                "message" => "Failed to pack module for publishing.",
                "error" => $packResult["error"]
            ];
        }

        // 2. Find the ZIP file
        $root = $this->context->getRoot();
        $zipPath = $root . DIRECTORY_SEPARATOR . "storage" . DIRECTORY_SEPARATOR . "feature-packs" . DIRECTORY_SEPARATOR . strtolower($module) . ".zip";
        
        if (!is_file($zipPath)) {
             // Fallback: check if the output mentions the path
             if (preg_match("/Packaged to: (.*\.zip)/", $packResult["output"], $matches)) {
                 $zipPath = trim($matches[1]);
             }
        }

        if (!is_file($zipPath)) {
            return [
                "success" => false,
                "message" => "Could not locate feature pack ZIP file at $zipPath",
            ];
        }

        // 3. Upload to Registry
        return $this->uploadToRegistry($uploadUrl, $zipPath, $token);
    }


    private function uploadToRegistry(string $uploadUrl, string $zipPath, string $token): array
    {
        
        // Use PHP CURL for multipart upload
        if (!function_exists("curl_init")) {
            return [
                "success" => false,
                "message" => "CURL PHP extension is required for publishing.",
            ];
        }

        $ch = curl_init();
        
        $file = new \CURLFile($zipPath, "application/zip", basename($zipPath));
        
        curl_setopt($ch, CURLOPT_URL, $uploadUrl);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, [
            "pack" => $file,
            "token" => $token
        ]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Authorization: Bearer " . $token,
            "Accept: application/json"
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            return [
                "success" => false,
                "message" => "CURL Error during upload: " . $error,
            ];
        }

        $data = json_decode((string)$response, true);
        
        if ($httpCode >= 200 && $httpCode < 300) {
            return [
                "success" => true,
                "message" => $data["message"] ?? "Successfully published to registry!",
                "requiresAuth" => false
            ];
        }

        $message = "Registry rejected upload (HTTP $httpCode)";
        $error = $data["error"] ?? (string)$response;

        if ($httpCode === 403) {
            $message = "Hardware Key required (Tier A).";
            $error = "This vendor account requires a hardware key (YubiKey/TouchID) to publish, but none are registered or verified. Please use 'vendor:upgrade' to register a key.";
        } elseif ($httpCode === 409) {
            $message = "Duplicate version.";
            $error = "This version of the feature pack has already been published to the registry.";
        }

        return [
            "success" => false,
            "message" => $message,
            "error" => $error,
            "requiresAuth" => ($httpCode === 401)
        ];
    }
}
