<?php

declare(strict_types=1);

namespace Ishmael\McpServer\Tools;

use Ishmael\McpServer\Contracts\Tool;
use Ishmael\McpServer\Project\ProjectContext;

/**
 * ide:setup-run-configs — Generate IDE Run Configurations (PhpStorm/VSCode).
 */
final class SetupRunConfigsTool implements Tool
{
    private ProjectContext $context;

    public function __construct(ProjectContext $context)
    {
        $this->context = $context;
    }

    public function getName(): string
    {
        return 'ide:setup-run-configs';
    }

    public function getDescription(): string
    {
        return 'Generate IDE Run Configurations for common Ishmael commands (currently supports PhpStorm).';
    }

    public function getInputSchema(): array
    {
        return [
            'type' => 'object',
            'additionalProperties' => false,
            'properties' => [
                'ide' => ['type' => 'string', 'enum' => ['phpstorm'], 'default' => 'phpstorm'],
                'overwrite' => ['type' => 'boolean', 'default' => false, 'description' => 'Overwrite existing configurations if they exist.'],
            ],
        ];
    }

    public function getOutputSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['success', 'message'],
            'properties' => [
                'success' => ['type' => 'boolean'],
                'message' => ['type' => 'string'],
                'files_created' => [
                    'type' => 'array',
                    'items' => ['type' => 'string']
                ],
            ],
        ];
    }

    public function execute(array $input): array
    {
        $root = $this->context->getRoot();
        if ($root === null) {
            return [
                'success' => false,
                'message' => 'Project root not found.',
            ];
        }

        $ide = $input['ide'] ?? 'phpstorm';
        $overwrite = $input['overwrite'] ?? false;

        if ($ide === 'phpstorm') {
            return $this->setupPhpStorm($root, $overwrite);
        }

        return [
            'success' => false,
            'message' => "IDE '{$ide}' is not supported yet.",
        ];
    }

    private function setupPhpStorm(string $root, bool $overwrite): array
    {
        $ideaPath = $root . DIRECTORY_SEPARATOR . '.idea';
        if (!is_dir($ideaPath)) {
            return [
                'success' => false,
                'message' => '.idea directory not found. Is this a PhpStorm project?',
            ];
        }

        $runConfigsPath = $ideaPath . DIRECTORY_SEPARATOR . 'runConfigurations';
        if (!is_dir($runConfigsPath)) {
            if (!mkdir($runConfigsPath, 0777, true) && !is_dir($runConfigsPath)) {
                return [
                    'success' => false,
                    'message' => 'Failed to create .idea/runConfigurations directory.',
                ];
            }
        }

        $ishBinary = $this->context->getIshBinary();
        if ($ishBinary === null) {
            return [
                'success' => false,
                'message' => 'ish binary not found.',
            ];
        }

        // Relative path to ish binary from root
        $relativeIsh = str_replace($root . DIRECTORY_SEPARATOR, '', $ishBinary);
        $relativeIsh = str_replace(DIRECTORY_SEPARATOR, '/', $relativeIsh);

        $configs = [
            'Ish_Help' => 'help',
            'Ish_Migrate' => 'migrate',
            'Ish_Routes_List' => 'routes:list',
            'Ish_Env_Validate' => 'env:validate',
            'Ish_Make_Module' => 'make:module',
        ];

        $created = [];
        foreach ($configs as $name => $arg) {
            $fileName = $name . '.xml';
            $filePath = $runConfigsPath . DIRECTORY_SEPARATOR . $fileName;

            if (file_exists($filePath) && !$overwrite) {
                continue;
            }

            $xml = $this->getPhpStormXml($name, $relativeIsh, $arg);
            if (file_put_contents($filePath, $xml) !== false) {
                $created[] = '.idea/runConfigurations/' . $fileName;
            }
        }

        if (empty($created)) {
            return [
                'success' => true,
                'message' => 'No new configurations were created (they might already exist).',
                'files_created' => [],
            ];
        }

        return [
            'success' => true,
            'message' => 'Successfully created PhpStorm Run Configurations.',
            'files_created' => $created,
        ];
    }

    private function getPhpStormXml(string $name, string $scriptPath, string $arguments): string
    {
        $nameAttr = htmlspecialchars($name, ENT_XML1);
        $scriptAttr = htmlspecialchars($scriptPath, ENT_XML1);
        $argsAttr = htmlspecialchars($arguments, ENT_XML1);

        return <<<XML
<component name="ProjectRunConfigurationManager">
  <configuration default="false" name="$nameAttr" type="PhpLocalRunConfigurationType" factoryName="PHP Console" path="\$PROJECT_DIR\$/$scriptAttr" scriptParameters="$argsAttr">
    <method v="2" />
  </configuration>
</component>
XML;
    }
}
