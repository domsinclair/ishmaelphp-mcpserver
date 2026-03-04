<?php
declare(strict_types=1);

namespace Ishmael\McpServer\Tools;

use Ishmael\McpServer\Contracts\Tool;
use Ishmael\McpServer\Project\ProjectContext;
use Ishmael\McpServer\Support\IshCliBridge;
use Ishmael\McpServer\Support\RegistryToolHelper;
use Exception;

/**
 * Publishes a feature pack to the Ishmael Registry with a high-trust "Pack and Submit" workflow.
 */
class FeaturePackPublishTool implements Tool
{
    protected ProjectContext $context;
    protected IshCliBridge $cli;

    private const AMENDED_CLI_PATH = "D:\\JetBrainsProjects\\PhpStorm\\ish\\IshmaelPHP-Core\\bin\\ish";
    private const DEFAULT_REGISTRY_URL = "https://vtl-ishmael-registry.test";

    public function __construct(ProjectContext $context)
    {
        $this->context = $context;
        $this->cli = new IshCliBridge($context);
    }

    public function getName(): string
    {
        return "ish:featurePack:publish";
    }

    public function getDescription(): string
    {
        return "Orchestrates the entire lifecycle of a feature pack from the local environment to the registry, including zipping, metadata verification, and secure authentication.";
    }

    public function getInputSchema(): array
    {
        return [
            "type" => "object",
            "required" => ["module_name"],
            "properties" => [
                "module_name" => [
                    "type" => "string",
                    "description" => "The name of the module to pack and publish."
                ],
                "registry_url" => [
                    "type" => "string",
                    "description" => "The registry URL. Defaults to https://vtl-ishmael-registry.test.",
                    "default" => self::DEFAULT_REGISTRY_URL
                ],
                "force_upgrade" => [
                    "type" => "boolean",
                    "description" => "If true, force the hardware key (Tier A) setup.",
                    "default" => false
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
                "status" => ["type" => "string"],
                "error" => ["type" => "string"],
            ],
        ];
    }

    public function execute(array $input): array
    {
        $moduleName = (string)$input["module_name"];
        $registryUrl = (string)($input["registry_url"] ?? self::DEFAULT_REGISTRY_URL);
        $forceUpgrade = (bool)($input["force_upgrade"] ?? false);

        $root = $this->context->getRoot();
        if (!$root) {
            return [
                "success" => false,
                "message" => "Project root not found.",
                "error" => "Ensure you are in an Ishmael project root."
            ];
        }

        // Pre-flight Validation
        $moduleDir = $root . DIRECTORY_SEPARATOR . "Modules" . DIRECTORY_SEPARATOR . $moduleName;
        $contextFile = $moduleDir . DIRECTORY_SEPARATOR . ".ish-context.md";
        $warnings = [];
        
        // Note: we don't return early if moduleDir doesn't exist yet, 
        // as the 'ish' command might handle it or it might be in a different structure.
        // However, usually it's in Modules/.
        if (is_dir($moduleDir) && !is_file($contextFile)) {
            $warnings[] = "Warning: .ish-context.md is missing in the module directory. This may affect the AI-readiness score in the registry.";
        }

        // Step A: Metadata & ZIP Generation
        $distDir = $root . DIRECTORY_SEPARATOR . "dist";
        if (!is_dir($distDir)) {
            @mkdir($distDir, 0755, true);
        }

        $registryJsonPath = $distDir . DIRECTORY_SEPARATOR . "registry.json";
        $zipPath = $distDir . DIRECTORY_SEPARATOR . strtolower($moduleName) . ".zip";

        // Clean up previous artifacts
        if (is_file($registryJsonPath)) @unlink($registryJsonPath);
        if (is_file($zipPath)) @unlink($zipPath);

        $packResult = $this->cli->execute("feature:pack", [
            "out" => "./dist",
            "registry-out" => "./dist/registry.json"
        ], [$moduleName], self::AMENDED_CLI_PATH);

        if (!$packResult["success"]) {
            return [
                "success" => false,
                "message" => "Failed to generate feature pack artifacts.",
                "error" => $packResult["error"]
            ];
        }

        // Parse registry.json to verify mandatory fields
        if (!is_file($registryJsonPath)) {
            return [
                "success" => false,
                "message" => "registry.json was not generated.",
            ];
        }

        $metadataContent = file_get_contents($registryJsonPath);
        $metadata = json_decode($metadataContent, true);
        $mandatoryFields = ["title", "category", "capabilities"];
        foreach ($mandatoryFields as $field) {
            if (empty($metadata[$field])) {
                return [
                    "success" => false,
                    "message" => "Metadata verification failed: mandatory field '$field' is missing in registry.json.",
                ];
            }
        }

        // Step B: Local Authentication Listener
        $port = 8080;
        $callbackUrl = "http://localhost:$port/callback";
        
        // Open browser for Step C
        $authUrl = rtrim($registryUrl, '/') . "/auth/publish?module=" . urlencode($moduleName) . "&redirect_uri=" . urlencode($callbackUrl);
        if ($forceUpgrade) {
            $authUrl .= "&force_upgrade=1";
        }

        // Inform user about browser handshake
        $this->openBrowser($authUrl);

        // Start listener to capture token
        $token = $this->listenForToken($port);
        
        if (!$token) {
            $this->cleanup($distDir, $moduleName);
            return [
                "success" => false,
                "message" => "Authentication failed or timed out. Could not capture Upload Token.",
            ];
        }

        // Step D: Secure Upload
        $uploadUrl = rtrim($registryUrl, '/') . "/api/publish/upload";
        $uploadResult = $this->uploadToRegistry($uploadUrl, $zipPath, $metadataContent, $token);

        // Cleanup
        $this->cleanup($distDir, $moduleName);

        if (!$uploadResult["success"]) {
            return $uploadResult;
        }

        $message = "Successfully published '$moduleName' to the registry.";
        if (isset($uploadResult['tier']) && $uploadResult['tier'] === 'A') {
            $message .= " [Hardware Verified]";
        }
        
        if (!empty($warnings)) {
            $message .= "\n" . implode("\n", $warnings);
        }

        return [
            "success" => true,
            "message" => $message,
            "status" => isset($uploadResult['tier']) && $uploadResult['tier'] === 'A' ? "Hardware Verified" : "Standard Verified"
        ];
    }

    protected function listenForToken(int $port): ?string
    {
        $server = @stream_socket_server("tcp://127.0.0.1:$port", $errno, $errstr);
        if (!$server) {
            return null;
        }

        $timeout = 120; // 2 minutes timeout
        $start = time();
        $token = null;

        while (time() - $start < $timeout) {
            $read = [$server];
            $write = null;
            $except = null;
            if (stream_select($read, $write, $except, 1) > 0) {
                $conn = stream_socket_accept($server);
                if ($conn) {
                    $request = fread($conn, 4096);
                    if (preg_match('/GET \/callback\?token=([^&\s]+)/', $request, $matches)) {
                        $token = $matches[1];
                        
                        $response = "HTTP/1.1 200 OK\r\nContent-Type: text/html\r\nConnection: close\r\n\r\n";
                        $response .= "<html><head><title>Ishmael Auth</title></head><body><h1>Authentication Successful</h1><p>The token has been captured by the MCP server. You can close this window now.</p></body></html>";
                        fwrite($conn, $response);
                        fclose($conn);
                        break;
                    }
                    fclose($conn);
                }
            }
        }
        
        fclose($server);
        return $token;
    }

    protected function openBrowser(string $url): void
    {
        if (PHP_OS_FAMILY === "Windows") {
            @shell_exec("start " . escapeshellarg($url));
        }
    }

    protected function uploadToRegistry(string $uploadUrl, string $zipPath, string $metadataJson, string $token): array
    {
        if (!function_exists("curl_init")) {
            return [
                "success" => false,
                "message" => "CURL extension is missing.",
            ];
        }

        $ch = curl_init();
        
        $postFields = [
            'pack' => new \CURLFile($zipPath, 'application/zip', basename($zipPath)),
            'metadata' => $metadataJson,
        ];

        curl_setopt($ch, CURLOPT_URL, $uploadUrl);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postFields);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Authorization: Bearer $token",
            "Accept: application/json"
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            return [
                "success" => false,
                "message" => "CURL Error: $error",
            ];
        }

        $data = json_decode((string)$response, true);
        
        if ($httpCode >= 200 && $httpCode < 300) {
            return [
                "success" => true,
                "tier" => $data['tier'] ?? 'B',
                "data" => $data
            ];
        }

        return [
            "success" => false,
            "message" => "Upload failed (HTTP $httpCode)",
            "error" => $data['error'] ?? (string)$response
        ];
    }

    private function cleanup(string $distDir, string $moduleName): void
    {
        $registryJsonPath = $distDir . DIRECTORY_SEPARATOR . "registry.json";
        $zipPath = $distDir . DIRECTORY_SEPARATOR . strtolower($moduleName) . ".zip";

        if (is_file($registryJsonPath)) @unlink($registryJsonPath);
        if (is_file($zipPath)) @unlink($zipPath);
        
        // Optionally remove dist dir if empty
        if (is_dir($distDir)) {
            $files = array_diff(scandir($distDir), array('.', '..'));
            if (empty($files)) {
                @rmdir($distDir);
            }
        }
    }
}
