<?php
    declare(strict_types=1);

    namespace Ishmael\McpServer\Tools;

    use Ishmael\McpServer\Contracts\Tool;
    use Ishmael\McpServer\Project\ProjectContext;
    use Ishmael\McpServer\Support\IshCliBridge;
    use Ishmael\McpServer\Support\RegistryToolHelper;
    use Exception;

    /**
     * Publishes a feature pack to the Ishmael Registry.
     * 
     * This tool requires a pre-obtained upload token. The token must be acquired
     * separately via the vendor:authenticate tool or by visiting the registry
     * authentication page directly.
     * 
     * Flow:
     * 1. User obtains token (via browser authentication)
     * 2. User calls this tool with the token
     * 3. Tool packs the module and uploads to registry
     */
    class FeaturePackPublishTool implements Tool
    {
        private const DEFAULT_REGISTRY_URL = 'https://vtl-ishmael-registry.test';
        protected ProjectContext $context;
        protected IshCliBridge $cli;

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
            return "Packs and publishes a feature pack to the registry. Requires a valid upload token obtained via vendor:authenticate.";
        }

        public function getInputSchema(): array
        {
            return [
                "type" => "object",
                "required" => ["module_name", "token"],
                "properties" => [
                    "module_name" => [
                        "type" => "string",
                        "description" => "The name of the module to pack and publish."
                    ],
                    "token" => [
                        "type" => "string",
                        "description" => "Upload token obtained from vendor:authenticate or the registry web UI."
                    ],
                    "registry_url" => [
                        "type" => "string",
                        "description" => "The registry URL. Defaults to https://vtl-ishmael-registry.test.",
                        "default" => self::DEFAULT_REGISTRY_URL
                    ]
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
                    "error" => ["type" => "object"],
                ],
            ];
        }

        public function execute(array $input): array
        {
            $moduleName = (string)$input["module_name"];
            $token = (string)($input["token"] ?? "");
            $registryUrl = (string)($input["registry_url"] ?? self::DEFAULT_REGISTRY_URL);

            // Fail fast if no token provided
            if (empty($token)) {
                return [
                    "success" => false,
                    "message" => "Upload token is required. Please obtain a token first using 'Obtain Token' or vendor:authenticate.",
                    "error" => [
                        "code" => 401,
                        "message" => "Missing upload token"
                    ]
                ];
            }

            $root = $this->context->getRoot();
            if (!$root) {
                return [
                    "success" => false,
                    "message" => "Project root not found.",
                    "error" => [
                        "code" => -32003,
                        "message" => "Ensure you are in an Ishmael project root."
                    ]
                ];
            }

            // Pre-flight Validation
            $moduleDir = $root . DIRECTORY_SEPARATOR . "Modules" . DIRECTORY_SEPARATOR . $moduleName;
            $contextFile = $moduleDir . DIRECTORY_SEPARATOR . ".ish-context.md";
            $warnings = [];

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
            ], [$moduleName]);

            if (!$packResult["success"]) {
                return [
                    "success" => false,
                    "message" => "Failed to generate feature pack artifacts.",
                    "error" => [
                        "code" => -32001,
                        "message" => $packResult["error"] ?? "Unknown CLI error"
                    ]
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

            // Step B: Secure Upload
            $uploadUrl = rtrim($registryUrl, '/') . "/api/publish/upload";
            $uploadResult = $this->uploadToRegistry($uploadUrl, $zipPath, $metadataContent, $token);

            // Cleanup
            $this->cleanup($distDir, $moduleName);

            if (!$uploadResult["success"]) {
                return [
                    "success" => false,
                    "message" => $uploadResult["message"] ?? "Upload failed",
                    "error" => [
                        "code" => $uploadResult["httpCode"] ?? -32002,
                        "message" => $uploadResult["error"] ?? "Unknown upload error"
                    ]
                ];
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

        protected function uploadToRegistry(string $uploadUrl, string $zipPath, string $metadataJson, string $token): array
        {
            if (!function_exists("curl_init")) {
                return [
                    "success" => false,
                    "message" => "CURL extension is missing.",
                ];
            }

            if (!is_file($zipPath)) {
                return [
                    "success" => false,
                    "message" => "Feature pack ZIP file not found: $zipPath",
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
            // Reasonable timeouts for file upload
            curl_setopt($ch, CURLOPT_TIMEOUT, 120);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 30);
            // SSL settings for local .test domains
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

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

            // Provide specific error messages for common HTTP codes
            $errorMessage = match ($httpCode) {
                401 => "Token expired or invalid. Please obtain a new token.",
                403 => "Access forbidden. You may need a Hardware Key (Tier A) for this vendor.",
                409 => "A feature pack with this version already exists.",
                422 => "Validation failed: " . ($data['message'] ?? 'Invalid data'),
                500 => "Registry server error. Please try again later.",
                default => "Upload failed (HTTP $httpCode)"
            };

            return [
                "success" => false,
                "message" => $errorMessage,
                "httpCode" => $httpCode,
                "error" => $data['error'] ?? $data['message'] ?? (string)$response
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
