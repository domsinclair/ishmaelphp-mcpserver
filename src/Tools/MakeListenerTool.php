<?php

declare(strict_types=1);

namespace Ishmael\McpServer\Tools;

use Ishmael\McpServer\Contracts\Tool;
use Ishmael\McpServer\Project\ProjectContext;

/**
 * ish:make:listener — Scaffold a Listener Class and register it in the module manifest.
 */
final class MakeListenerTool implements Tool
{
    private ProjectContext $context;

    public function __construct(ProjectContext $context)
    {
        $this->context = $context;
    }

    public function getName(): string
    {
        return 'ish:make:listener';
    }

    public function getDescription(): string
    {
        return 'Create a Listener Class inside a module and automatically register it under an event in the module.php manifest.';
    }

    public function getInputSchema(): array
    {
        return [
            'type' => 'object',
            'additionalProperties' => false,
            'required' => ['module', 'name', 'event'],
            'properties' => [
                'module' => ['type' => 'string', 'description' => 'Target module name.'],
                'name' => ['type' => 'string', 'description' => 'Listener class name (e.g. SyncInventory).'],
                'event' => ['type' => 'string', 'description' => 'The FQCN or string name of the event to listen to.'],
                'preview' => ['type' => 'boolean', 'description' => 'Preview the changes without writing to disk.'],
            ],
        ];
    }

    public function getOutputSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['success', 'output', 'files'],
            'properties' => [
                'success' => ['type' => 'boolean'],
                'output' => ['type' => 'string'],
                'error' => ['type' => ['string', 'null']],
                'files' => [
                    'type' => 'array',
                    'items' => ['type' => 'string'],
                    'description' => 'Absolute paths of created/modified files.'
                ],
                'preview' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'path' => ['type' => 'string'],
                            'content' => ['type' => 'string'],
                        ]
                    ],
                ],
            ],
        ];
    }

    public function execute(array $input): array
    {
        $module = $input['module'];
        $name = $input['name'];
        $event = $input['event'];
        $preview = !empty($input['preview']);

        $root = $this->context->getRoot();
        if (!$root) {
            return ['success' => false, 'output' => '', 'error' => 'Project root not found.', 'files' => []];
        }

        $modulePath = $root . DIRECTORY_SEPARATOR . 'Modules' . DIRECTORY_SEPARATOR . $module;
        if (!is_dir($modulePath)) {
            return ['success' => false, 'output' => '', 'error' => "Module $module not found at $modulePath.", 'files' => []];
        }

        $listenersDir = $modulePath . DIRECTORY_SEPARATOR . 'Listeners';
        $filePath = $listenersDir . DIRECTORY_SEPARATOR . $name . '.php';
        $namespace = "Modules\\$module\\Listeners";
        $fqcn = $namespace . "\\" . $name;

        // Generate Class Content
        $classContent = $this->generateListenerClass($namespace, $name, $event);
        
        // Update manifest
        $manifestPath = $modulePath . DIRECTORY_SEPARATOR . 'module.php';
        $manifestContent = null;
        if (file_exists($manifestPath)) {
            $manifestContent = $this->updateManifest($manifestPath, $event, $fqcn);
        }

        if ($preview) {
            $previews = [
                ['path' => "Modules/$module/Listeners/$name.php", 'content' => $classContent]
            ];
            if ($manifestContent) {
                $previews[] = ['path' => "Modules/$module/module.php", 'content' => $manifestContent];
            }
            return [
                'success' => true,
                'output' => "Previewing changes for Listener $name.",
                'files' => [],
                'preview' => $previews
            ];
        }

        // Write files
        if (!is_dir($listenersDir)) {
            mkdir($listenersDir, 0755, true);
        }
        file_put_contents($filePath, $classContent);
        
        $files = [realpath($filePath)];
        if ($manifestContent) {
            file_put_contents($manifestPath, $manifestContent);
            $files[] = realpath($manifestPath);
        }

        return [
            'success' => true,
            'output' => "Listener $name created and registered for event $event in $module manifest.",
            'files' => $files
        ];
    }

    private function generateListenerClass(string $namespace, string $name, string $event): string
    {
        $eventShortName = str_contains($event, '\\') ? substr($event, strrpos($event, '\\') + 1) : $event;
        $useStatement = str_contains($event, '\\') ? "use $event;\n\n" : "";

        $content = "<?php\n\ndeclare(strict_types=1);\n\nnamespace $namespace;\n\n$useStatement";
        $content .= "/**\n * Listener for $eventShortName\n */\nfinal class $name\n{\n";
        $content .= "    public function handle($eventShortName \$event): void\n";
        $content .= "    {\n";
        $content .= "        // Handle the event\n";
        $content .= "    }\n";
        $content .= "}\n";
        return $content;
    }

    private function updateManifest(string $path, string $event, string $listenerFqcn): string
    {
        $content = file_get_contents($path);

        if (str_contains($content, "'listeners' => [")) {
            // Check if this specific event already has listeners
            if (str_contains($content, "'$event' => [")) {
                 return preg_replace("/'$event' => \[/", "'$event' => [\n            '$listenerFqcn',", $content, 1);
            } else {
                 return preg_replace("/'listeners' => \[/", "'listeners' => [\n        '$event' => [\n            '$listenerFqcn'\n        ],", $content, 1);
            }
        } else {
            // Add new listeners block before last return array closing
            $block = "\n    'listeners' => [\n        '$event' => [\n            '$listenerFqcn'\n        ],\n    ],";
            return preg_replace('/\];\s*$/', $block . "\n];", $content);
        }
    }
}
