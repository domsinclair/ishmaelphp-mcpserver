<?php

declare(strict_types=1);

namespace Ishmael\McpServer\Tools;

use Ishmael\McpServer\Contracts\Tool;
use Ishmael\McpServer\Project\ProjectContext;

/**
 * ish:make:event — Scaffold a Typed Event Class and register it in the module manifest.
 */
final class MakeEventTool implements Tool
{
    private ProjectContext $context;

    public function __construct(ProjectContext $context)
    {
        $this->context = $context;
    }

    public function getName(): string
    {
        return 'ish:make:event';
    }

    public function getDescription(): string
    {
        return 'Create a Typed Event Class inside a module and automatically register it in the module.php manifest.';
    }

    public function getInputSchema(): array
    {
        return [
            'type' => 'object',
            'additionalProperties' => false,
            'required' => ['module', 'name'],
            'properties' => [
                'module' => ['type' => 'string', 'description' => 'Target module name.'],
                'name' => ['type' => 'string', 'description' => 'Event class name (e.g. OrderPlaced).'],
                'description' => ['type' => 'string', 'description' => 'Short description of when the event is dispatched.'],
                'payload' => [
                    'type' => 'object',
                    'additionalProperties' => ['type' => 'string'],
                    'description' => 'Key-type pairs for the event payload (e.g. {"orderId": "int"}).',
                ],
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
        $description = $input['description'] ?? "Dispatched when $name occurs.";
        $payload = $input['payload'] ?? [];
        $preview = !empty($input['preview']);

        $root = $this->context->getRoot();
        if (!$root) {
            return ['success' => false, 'output' => '', 'error' => 'Project root not found.', 'files' => []];
        }

        $modulePath = $root . DIRECTORY_SEPARATOR . 'Modules' . DIRECTORY_SEPARATOR . $module;
        if (!is_dir($modulePath)) {
            return ['success' => false, 'output' => '', 'error' => "Module $module not found at $modulePath.", 'files' => []];
        }

        $eventsDir = $modulePath . DIRECTORY_SEPARATOR . 'Events';
        $filePath = $eventsDir . DIRECTORY_SEPARATOR . $name . '.php';
        $namespace = "Modules\\$module\\Events";
        $fqcn = $namespace . "\\" . $name;

        // Generate Class Content
        $classContent = $this->generateEventClass($namespace, $name, $description, $payload);
        
        // Update manifest
        $manifestPath = $modulePath . DIRECTORY_SEPARATOR . 'module.php';
        $manifestContent = null;
        if (file_exists($manifestPath)) {
            $manifestContent = $this->updateManifest($manifestPath, $fqcn, $description, $payload);
        }

        if ($preview) {
            $previews = [
                ['path' => "Modules/$module/Events/$name.php", 'content' => $classContent]
            ];
            if ($manifestContent) {
                $previews[] = ['path' => "Modules/$module/module.php", 'content' => $manifestContent];
            }
            return [
                'success' => true,
                'output' => "Previewing changes for Event $name.",
                'files' => [],
                'preview' => $previews
            ];
        }

        // Write files
        if (!is_dir($eventsDir)) {
            mkdir($eventsDir, 0755, true);
        }
        file_put_contents($filePath, $classContent);
        
        $files = [realpath($filePath)];
        if ($manifestContent) {
            file_put_contents($manifestPath, $manifestContent);
            $files[] = realpath($manifestPath);
        }

        return [
            'success' => true,
            'output' => "Event $name created and registered in $module manifest.",
            'files' => $files
        ];
    }

    private function generateEventClass(string $namespace, string $name, string $description, array $payload): string
    {
        $content = "<?php\n\ndeclare(strict_types=1);\n\nnamespace $namespace;\n\n/**\n * $description\n */\nfinal class $name\n{\n";
        
        foreach ($payload as $field => $type) {
            $content .= "    public $type \$$field;\n";
        }

        if (!empty($payload)) {
            $content .= "\n    public function __construct(";
            $params = [];
            foreach ($payload as $field => $type) {
                $params[] = "$type \$$field";
            }
            $content .= implode(', ', $params);
            $content .= ")\n    {\n";
            foreach ($payload as $field => $type) {
                $content .= "        \$this->$field = \$$field;\n";
            }
            $content .= "    }\n";
        }

        $content .= "}\n";
        return $content;
    }

    private function updateManifest(string $path, string $fqcn, string $description, array $payload): string
    {
        $content = file_get_contents($path);
        
        $payloadStr = "['" . implode("', '", array_keys($payload)) . "']"; // Simple representation
        if (!empty($payload)) {
             $items = [];
             foreach($payload as $k => $v) $items[] = "'$k' => '$v'";
             $payloadStr = "[" . implode(", ", $items) . "]";
        } else {
             $payloadStr = "[]";
        }

        $entry = "        '$fqcn' => [\n            'description' => '$description',\n            'payload' => $payloadStr\n        ],";

        if (str_contains($content, "'emits' => [")) {
            // Append to existing emits
            return preg_replace("/'emits' => \[/", "'emits' => [\n$entry", $content, 1);
        } else {
            // Add new emits block before last return array closing
            $block = "\n    'emits' => [\n$entry\n    ],";
            return preg_replace('/\];\s*$/', $block . "\n];", $content);
        }
    }
}
