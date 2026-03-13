<?php
    declare(strict_types=1);

    namespace Ishmael\McpServer\Tools;

    use Ishmael\McpServer\Contracts\Tool;
    use Ishmael\McpServer\Project\ProjectContext;
    use Ishmael\McpServer\Support\RegistryToolHelper;

    /**
     * Tool to promote a vendor to Tier A (Hardware) by registering security keys.
     * 
     * Opens the browser to the security key setup page and returns immediately.
     * User completes the WebAuthn registration in browser.
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
            return "Opens browser to promote a vendor to Tier A (Hardware) by registering security keys. Returns immediately - user completes setup in browser.";
        }

        public function getInputSchema(): array
        {
            return [
                "type" => "object",
                "required" => ["vendor"],
                "properties" => [
                    "vendor" => ["type" => "string", "description" => "The vendor name (slug) to upgrade"],
                    "registryUrl" => ["type" => "string", "description" => "Registry base URL override"],
                    "noBrowser" => ["type" => "boolean", "description" => "If true, skips browser launch and only returns the upgrade URL.", "default" => false]
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
                    "authUrl" => ["type" => "string", "description" => "The upgrade URL to visit"],
                    "instructions" => ["type" => "string", "description" => "Instructions for the user"]
                ]
            ];
        }

        public function execute(array $input): array
        {
            $vendor = (string)$input["vendor"];
            $registryUrl = isset($input["registryUrl"]) ? (string)$input["registryUrl"] : RegistryToolHelper::getRegistryBaseUrl($this->context);

            $config = RegistryToolHelper::getConfig($this->context);
            $upgradeBaseUrl = isset($config['registry_upgrade_url']) ? (string)$config['registry_upgrade_url'] : rtrim($registryUrl, '/') . "/auth/keys/setup";

            $noBrowser = (bool)($input["noBrowser"] ?? (getenv('ISH_MCP_NO_BROWSER') === '1'));

            $params = [
                "upgrade" => "1",
                "vendor" => $vendor
            ];

            $authUrl = $upgradeBaseUrl . (str_contains($upgradeBaseUrl, '?') ? '&' : '?') . http_build_query($params);

            // Open browser unless explicitly disabled
            if (!$noBrowser) {
                RegistryToolHelper::openBrowser($authUrl);
            }

            $instructions = "1. Insert your hardware security key (YubiKey, etc.).\n" .
                           "2. Complete the WebAuthn registration in your browser.\n" .
                           "3. After successful registration, your vendor will be promoted to Tier A.\n" .
                           "4. Use 'Obtain Token' to get a new token with Hardware verification.";

            $message = $noBrowser
                ? "Upgrade URL generated. Please open it in your browser."
                : "Browser opened for security key setup.";

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
