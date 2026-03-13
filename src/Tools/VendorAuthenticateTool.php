<?php
    declare(strict_types=1);

    namespace Ishmael\McpServer\Tools;

    use Ishmael\McpServer\Contracts\Tool;
    use Ishmael\McpServer\Project\ProjectContext;
    use Ishmael\McpServer\Support\RegistryToolHelper;

    /**
     * Tool to initiate authentication with Ishmael Registry.
     * 
     * This tool opens the browser to the registry authentication page and returns immediately.
     * The user must copy the token from the browser and paste it into the plugin.
     * This approach eliminates fragile TCP listener-based token capture.
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
            return "Opens browser to obtain an upload token from the Ishmael Registry. Returns immediately - user must copy token from browser.";
        }

        public function getInputSchema(): array
        {
            return [
                "type" => "object",
                "properties" => [
                    "module" => ["type" => "string", "description" => "Module name for which to get a token"],
                    "vendor" => ["type" => "string", "description" => "Optional vendor name to prefill"],
                    "upgrade" => ["type" => "boolean", "description" => "If true, forces hardware key registration flow", "default" => false],
                    "registryUrl" => ["type" => "string", "description" => "Registry base URL override"],
                    "noBrowser" => ["type" => "boolean", "description" => "If true, skips browser launch and only returns the auth URL.", "default" => false]
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
                    "authUrl" => ["type" => "string", "description" => "The authentication URL to visit"],
                    "instructions" => ["type" => "string", "description" => "Instructions for the user"]
                ]
            ];
        }

        public function execute(array $input): array
        {
            $module = isset($input["module"]) ? (string)$input["module"] : null;
            $vendor = isset($input["vendor"]) ? (string)$input["vendor"] : null;
            $upgrade = (bool)($input["upgrade"] ?? false);
            $registryUrl = isset($input["registryUrl"]) ? (string)$input["registryUrl"] : null;
            $noBrowser = (bool)($input["noBrowser"] ?? (getenv('ISH_MCP_NO_BROWSER') === '1'));

            // Build the authentication URL
            $authUrl = RegistryToolHelper::buildAuthUrl(
                $this->context,
                $module,
                $vendor,
                $upgrade,
                $registryUrl
            );

            // Open browser unless explicitly disabled
            if (!$noBrowser) {
                RegistryToolHelper::openBrowser($authUrl);
            }

            $instructions = "1. Complete authentication in your browser.\n" .
                           "2. Copy the token displayed after successful login.\n" .
                           "3. Paste the token into the plugin dialog.";

            $message = $noBrowser
                ? "Authentication URL generated. Please open it in your browser."
                : "Browser opened for authentication.";

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
