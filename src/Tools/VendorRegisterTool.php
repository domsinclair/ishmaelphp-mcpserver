<?php
    declare(strict_types=1);

    namespace Ishmael\McpServer\Tools;

    use Ishmael\McpServer\Contracts\Tool;
    use Ishmael\McpServer\Project\ProjectContext;
    use Ishmael\McpServer\Support\RegistryToolHelper;

    /**
     * Tool to register a new vendor on the Ishmael Registry.
     * 
     * Opens the browser to the registration page and returns immediately.
     * User completes registration in browser and copies the token.
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
            return "Opens browser to register a new vendor on the Ishmael Registry. Returns immediately - user completes registration in browser.";
        }

        public function getInputSchema(): array
        {
            return [
                "type" => "object",
                "properties" => [
                    "name" => ["type" => "string", "description" => "Vendor name (slug-style) to prefill"],
                    "email" => ["type" => "string", "description" => "Developer email to prefill"],
                    "url" => ["type" => "string", "description" => "Developer website to prefill"],
                    "registryUrl" => ["type" => "string", "description" => "Registry base URL override"],
                    "noBrowser" => ["type" => "boolean", "description" => "If true, skips browser launch and only returns the registration URL.", "default" => false]
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
                    "authUrl" => ["type" => "string", "description" => "The registration URL to visit"],
                    "instructions" => ["type" => "string", "description" => "Instructions for the user"]
                ]
            ];
        }

        public function execute(array $input): array
        {
            $registryUrl = isset($input["registryUrl"]) ? (string)$input["registryUrl"] : RegistryToolHelper::getRegistryBaseUrl($this->context);
            $config = RegistryToolHelper::getConfig($this->context);
            $registerBaseUrl = isset($config['registry_register_url']) ? (string)$config['registry_register_url'] : rtrim($registryUrl, '/') . "/auth/register";

            $noBrowser = (bool)($input["noBrowser"] ?? (getenv('ISH_MCP_NO_BROWSER') === '1'));

            // Build URL params for prefilling the form
            $params = [];
            if (!empty($input["name"])) $params["name"] = $input["name"];
            if (!empty($input["email"])) $params["email"] = $input["email"];
            if (!empty($input["url"])) $params["url"] = $input["url"];

            $authUrl = $registerBaseUrl;
            if (!empty($params)) {
                $authUrl .= (str_contains($registerBaseUrl, '?') ? '&' : '?') . http_build_query($params);
            }

            // Open browser unless explicitly disabled
            if (!$noBrowser) {
                RegistryToolHelper::openBrowser($authUrl);
            }

            $instructions = "1. Complete the registration form in your browser.\n" .
                           "2. Choose your trust tier (Hardware Key or Community).\n" .
                           "3. Copy the token displayed after successful registration.\n" .
                           "4. Use 'Obtain Token' or paste the token when publishing.";

            $message = $noBrowser
                ? "Registration URL generated. Please open it in your browser."
                : "Browser opened for vendor registration.";

            return [
                "success" => true,
                "message" => $message,
                "authUrl" => $authUrl,
                "url" => $authUrl, // Backwards compatibility
                "instructions" => $instructions,
                "manual" => true
            ];
        }
    }
