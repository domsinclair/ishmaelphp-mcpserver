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
        return 'Generate IDE Run Configurations for common Ishmael commands (supports PhpStorm and VSCode).';
    }

    public function getInputSchema(): array
    {
        return [
            'type' => 'object',
            'additionalProperties' => false,
            'properties' => [
                'ide' => ['type' => 'string', 'enum' => ['phpstorm', 'vscode'], 'default' => 'phpstorm'],
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
            $this->setupMcpConfig($root, $overwrite);
            return $this->setupPhpStorm($root, $overwrite);
        }

        if ($ide === 'vscode') {
            $this->setupMcpConfig($root, $overwrite);
            return $this->setupVSCode($root, $overwrite);
        }

        return [
            'success' => false,
            'message' => "IDE '{$ide}' is not supported yet.",
        ];
    }

    private function setupVSCode(string $root, bool $overwrite): array
    {
        $vscodePath = $root . DIRECTORY_SEPARATOR . '.vscode';
        if (!is_dir($vscodePath)) {
            if (!mkdir($vscodePath, 0777, true) && !is_dir($vscodePath)) {
                return [
                    'success' => false,
                    'message' => 'Failed to create .vscode directory.',
                ];
            }
        }

        $launchJsonPath = $vscodePath . DIRECTORY_SEPARATOR . 'launch.json';
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
            [
                'name' => 'Ish: Help',
                'type' => 'php',
                'request' => 'launch',
                'program' => '${workspaceFolder}/' . $relativeIsh,
                'args' => ['help']
            ],
            [
                'name' => 'Ish: Migrate',
                'type' => 'php',
                'request' => 'launch',
                'program' => '${workspaceFolder}/' . $relativeIsh,
                'args' => ['migrate']
            ],
            [
                'name' => 'Ish: Routes List',
                'type' => 'php',
                'request' => 'launch',
                'program' => '${workspaceFolder}/' . $relativeIsh,
                'args' => ['routes:list']
            ]
        ];

        $existing = [];
        if (file_exists($launchJsonPath)) {
            $content = file_get_contents($launchJsonPath);
            $existing = json_decode((string)$content, true) ?: [];
        }

        if (empty($existing)) {
            $existing = [
                'version' => '0.2.0',
                'configurations' => []
            ];
        }

        $created = [];
        $filesCreated = [];

        // 1. Handle launch.json
        $launchResult = $this->updateLaunchJson($launchJsonPath, $relativeIsh, $overwrite);
        if ($launchResult['updated']) {
            $filesCreated[] = '.vscode/launch.json';
        }

        // 2. Handle tasks.json
        $tasksJsonPath = $vscodePath . DIRECTORY_SEPARATOR . 'tasks.json';
        $tasksResult = $this->updateTasksJson($tasksJsonPath, $relativeIsh, $overwrite);
        if ($tasksResult['updated']) {
            $filesCreated[] = '.vscode/tasks.json';
        }

        // 3. Handle extensions.json
        $extensionsJsonPath = $vscodePath . DIRECTORY_SEPARATOR . 'extensions.json';
        $extensionsResult = $this->updateExtensionsJson($extensionsJsonPath, $overwrite);
        if ($extensionsResult['updated']) {
            $filesCreated[] = '.vscode/extensions.json';
        }

        if (empty($filesCreated)) {
            return [
                'success' => true,
                'message' => 'No new configurations were added to VSCode.',
                'files_created' => [],
            ];
        }

        return [
            'success' => true,
            'message' => 'Successfully updated VSCode configurations.',
            'files_created' => $filesCreated,
        ];
    }

    private function updateLaunchJson(string $path, string $relativeIsh, bool $overwrite): array
    {
        $configs = [
            [
                'name' => 'Ish: Help',
                'type' => 'php',
                'request' => 'launch',
                'program' => '${workspaceFolder}/' . $relativeIsh,
                'args' => ['help']
            ],
            [
                'name' => 'Ish: Migrate',
                'type' => 'php',
                'request' => 'launch',
                'program' => '${workspaceFolder}/' . $relativeIsh,
                'args' => ['migrate']
            ],
            [
                'name' => 'Ish: Routes List',
                'type' => 'php',
                'request' => 'launch',
                'program' => '${workspaceFolder}/' . $relativeIsh,
                'args' => ['routes:list']
            ]
        ];

        $existing = [];
        if (file_exists($path)) {
            $content = file_get_contents($path);
            $existing = json_decode((string)$content, true) ?: [];
        }

        if (empty($existing)) {
            $existing = [
                'version' => '0.2.0',
                'configurations' => []
            ];
        }

        $updated = false;
        foreach ($configs as $config) {
            $found = false;
            foreach ($existing['configurations'] as &$existingConfig) {
                if (isset($existingConfig['name']) && $existingConfig['name'] === $config['name']) {
                    if ($overwrite) {
                        $existingConfig = $config;
                        $updated = true;
                    }
                    $found = true;
                    break;
                }
            }
            if (!$found) {
                $existing['configurations'][] = $config;
                $updated = true;
            }
        }

        if ($updated) {
            file_put_contents($path, json_encode($existing, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        }

        return ['updated' => $updated];
    }

    private function updateTasksJson(string $path, string $relativeIsh, bool $overwrite): array
    {
        $tasks = [
            [
                'label' => 'Ish: Migrate',
                'type' => 'shell',
                'command' => 'php ' . $relativeIsh . ' migrate',
                'problemMatcher' => [],
                'group' => [
                    'kind' => 'build',
                    'isDefault' => true
                ]
            ],
            [
                'label' => 'Ish: Seed',
                'type' => 'shell',
                'command' => 'php ' . $relativeIsh . ' seed',
                'problemMatcher' => []
            ],
            [
                'label' => 'Ish: Cache Clear',
                'type' => 'shell',
                'command' => 'php ' . $relativeIsh . ' cache:clear',
                'problemMatcher' => []
            ]
        ];

        $existing = [];
        if (file_exists($path)) {
            $content = file_get_contents($path);
            $existing = json_decode((string)$content, true) ?: [];
        }

        if (empty($existing)) {
            $existing = [
                'version' => '2.0.0',
                'tasks' => []
            ];
        }

        $updated = false;
        foreach ($tasks as $task) {
            $found = false;
            foreach ($existing['tasks'] as &$existingTask) {
                if (isset($existingTask['label']) && $existingTask['label'] === $task['label']) {
                    if ($overwrite) {
                        $existingTask = $task;
                        $updated = true;
                    }
                    $found = true;
                    break;
                }
            }
            if (!$found) {
                $existing['tasks'][] = $task;
                $updated = true;
            }
        }

        if ($updated) {
            file_put_contents($path, json_encode($existing, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        }

        return ['updated' => $updated];
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

    private function setupMcpConfig(string $root, bool $overwrite): void
    {
        $path = $root . DIRECTORY_SEPARATOR . 'jetbrains-mcp.json';
        if (file_exists($path) && !$overwrite) {
            return;
        }

        $config = [
            'mcpServers' => [
                'ishmael' => [
                    'command' => 'php',
                    'args' => [
                        'vendor/ishmael/mcp-server/bin/ish-mcp'
                    ],
                    'enabled' => true
                ]
            ]
        ];

        file_put_contents($path, json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }

    private function updateExtensionsJson(string $path, bool $overwrite): array
    {
        $recommendations = [
            'xdebug.php-debug',
            'bmewburn.vscode-intelephense-client',
            'saoudrizwan.claude-dev',
            'rooveterinaryinc.roo-cline'
        ];

        $existing = [];
        if (file_exists($path)) {
            $content = file_get_contents($path);
            $existing = json_decode((string)$content, true) ?: [];
        }

        if (empty($existing)) {
            $existing = [
                'recommendations' => []
            ];
        }

        $updated = false;
        foreach ($recommendations as $rec) {
            if (!in_array($rec, $existing['recommendations'])) {
                $existing['recommendations'][] = $rec;
                $updated = true;
            }
        }

        if ($updated) {
            file_put_contents($path, json_encode($existing, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        }

        return ['updated' => $updated];
    }
}
