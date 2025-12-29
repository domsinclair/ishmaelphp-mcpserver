<?php

declare(strict_types=1);

namespace IshmaelPHP\McpServer\Tools;

use IshmaelPHP\McpServer\Contracts\Tool;
use IshmaelPHP\McpServer\Project\ProjectContext;
use IshmaelPHP\McpServer\Project\PathSandbox;

final class FeaturePackCreateTool implements Tool
{
    private ProjectContext $context;

    // stash last input to use during applyPlan tokenization

    private string $lastVendor = '';

    private string $lastName = '';

    private string $lastNamespace = '';

    private string $lastDescription = '';

    private string $lastLicense = '';



    public function __construct(ProjectContext $context)
    {

        $this->context = $context;
    }



    public function getName(): string
    {

        return 'ish:featurePack:create';
    }



    public function getDescription(): string
    {

        return 'Create a new Feature Pack from templates (dry-run by default; confirm=true to write).';
    }



    public function getInputSchema(): array
    {

        return [

            'type' => 'object',

            'additionalProperties' => false,

            'required' => ['name', 'vendor', 'namespace'],

            'properties' => [

                'name' => ['type' => 'string'],

                'vendor' => ['type' => 'string'],

                'namespace' => ['type' => 'string'],

                'description' => ['type' => 'string'],

                'license' => ['type' => 'string'],

                'repoInit' => ['type' => 'boolean'],

                'targetPath' => ['type' => 'string'],

                'confirm' => ['type' => 'boolean'],

            ],

        ];
    }



    public function getOutputSchema(): array
    {

        return [

            'type' => 'object',

            'required' => ['dryRun', 'targetPath', 'files'],

            'properties' => [

                'dryRun' => ['type' => 'boolean'],

                'targetPath' => ['type' => 'string'],

                'conflicts' => ['type' => 'array', 'items' => ['type' => 'string']],

                'files' => [

                    'type' => 'array',

                    'items' => [

                        'type' => 'object',

                        'required' => ['path', 'action'],

                        'properties' => [

                            'path' => ['type' => 'string'],

                            'action' => ['type' => 'string'],

                            'fromTemplate' => ['type' => ['string','null']],

                            'contentPreview' => ['type' => 'string'],

                            'exists' => ['type' => 'boolean'],

                        ],

                    ],

                ],

            ],

        ];
    }



    public function execute(array $input): array
    {

        $sandbox = $this->context->getSandbox();

        $root = $this->context->getRoot();

        if ($sandbox === null || $root === null) {
            return [ 'dryRun' => true, 'targetPath' => '', 'files' => [], 'conflicts' => [], 'error' => 'Project root not detected.' ];
        }



        $name = $this->sanitize((string)($input['name'] ?? ''));

        $vendor = $this->sanitize((string)($input['vendor'] ?? ''));

        $namespace = trim((string)($input['namespace'] ?? ''));

        $description = isset($input['description']) && is_string($input['description']) ? $input['description'] : '';

        $license = isset($input['license']) && is_string($input['license']) ? $input['license'] : 'MIT';

        $confirm = (bool)($input['confirm'] ?? false);

        $repoInit = (bool)($input['repoInit'] ?? false); // currently ignored (no VCS ops)



        if ($name === '' || $vendor === '' || $namespace === '') {
            return [ 'dryRun' => true, 'targetPath' => '', 'files' => [], 'conflicts' => [], 'error' => 'Missing required fields: name, vendor, namespace' ];
        }



        // Save for applyPlan

        $this->lastVendor = $vendor;

        $this->lastName = $name;

        $this->lastNamespace = $namespace;

        $this->lastDescription = $description;

        $this->lastLicense = $license;



        $defaultTarget = $root . DIRECTORY_SEPARATOR . 'FeaturePacks' . DIRECTORY_SEPARATOR . $vendor . '.' . $name;

        $targetPath = isset($input['targetPath']) && is_string($input['targetPath']) && $input['targetPath'] !== ''

            ? $input['targetPath']

            : $defaultTarget;

        // Resolve absolute path if relative

        if (!preg_match('~^[A-Za-z]:\\\\|^/~', $targetPath)) {
            $targetPath = $root . DIRECTORY_SEPARATOR . $targetPath;
        }

        $targetPath = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $targetPath);

        $sandbox->assertWithinRoot($targetPath, 'targetPath');



        // Identify a source template folder if present

        $templateRoot = $root . DIRECTORY_SEPARATOR . 'Templates' . DIRECTORY_SEPARATOR . 'FeaturePacks';

        $preferred = $templateRoot . DIRECTORY_SEPARATOR . $name;

        $fallback = $templateRoot . DIRECTORY_SEPARATOR . 'Upload';

        $sourceTemplate = is_dir($preferred) ? $preferred : (is_dir($fallback) ? $fallback : null);



        // Build plan

        $planFiles = [];

        $conflicts = [];



        if ($sourceTemplate !== null) {
            // Copy all files from template (shallow recursive)

            $rii = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($sourceTemplate, \FilesystemIterator::SKIP_DOTS));

            foreach ($rii as $item) {
                $rel = substr($item->getPathname(), strlen($sourceTemplate) + 1);

                $dest = $targetPath . DIRECTORY_SEPARATOR . $rel;

                $exists = file_exists($dest);

                if ($exists) {
                    $conflicts[] = $dest;
                }

                $preview = '';

                if ($item->isFile()) {
                    $content = (string)@file_get_contents($item->getPathname());

                    // Basic token replacement

                    $content = $this->applyTokens($content, $vendor, $name, $namespace, $description, $license);

                    $preview = $this->preview($content);
                }

                $planFiles[] = [

                    'path' => $dest,

                    'action' => $exists ? 'skip' : ($item->isDir() ? 'mkdir' : 'write'),

                    'fromTemplate' => $item->isDir() ? null : $item->getPathname(),

                    'contentPreview' => $preview,

                    'exists' => $exists,

                ];
            }
        } else {
            // Minimal generated skeleton

            $composerJson = json_encode([

                'name' => strtolower($vendor) . '/' . strtolower($name),

                'description' => $description !== '' ? $description : ($name . ' Feature Pack for Ishmael'),

                'type' => 'library',

                'license' => $license,

                'autoload' => [ 'psr-4' => [ $namespace . '\\' => 'src/' ] ],

                'require' => new \stdClass(),

            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";

            $files = [

                'composer.json' => $composerJson,

                'README.md' => "# $name\n\n$description\n",

                'src/.gitkeep' => '',

            ];

            foreach ($files as $rel => $content) {
                $dest = $targetPath . DIRECTORY_SEPARATOR . $rel;

                $exists = file_exists($dest);

                if ($exists) {
                    $conflicts[] = $dest;
                }

                $planFiles[] = [

                    'path' => $dest,

                    'action' => $exists ? 'skip' : 'write',

                    'fromTemplate' => null,

                    'contentPreview' => $this->preview($content),

                    'exists' => $exists,

                ];
            }
        }



        $dryRun = !$confirm;



        if ($confirm) {
            // Do not write if conflicts detected

            if (!empty($conflicts)) {
                $dryRun = true; // nothing written
            } else {
                $this->applyPlan($sandbox, $targetPath, $planFiles);

                $dryRun = false;
            }
        }



        return [

            'dryRun' => $dryRun,

            'targetPath' => $targetPath,

            'files' => $planFiles,

            'conflicts' => $conflicts,

        ];
    }



    /**

     * @param array<int, array{path:string,action:string,fromTemplate: ?string, contentPreview:string, exists:bool}> $plan

     */

    private function applyPlan(PathSandbox $sandbox, string $targetPath, array $plan): void
    {

        // Ensure directories before writing files

        foreach ($plan as $step) {
            $path = $step['path'];

            $sandbox->assertWithinRoot($path);

            if ($step['action'] === 'mkdir') {
                if (!is_dir($path)) {
                    @mkdir($path, 0777, true);
                }
            }
        }

        foreach ($plan as $step) {
            $path = $step['path'];

            if ($step['action'] !== 'write') {
                continue;
            }

            $dir = dirname($path);

            if (!is_dir($dir)) {
                @mkdir($dir, 0777, true);
            }

            $content = '';

            if ($step['fromTemplate'] !== null && is_file($step['fromTemplate'])) {
                $content = (string)@file_get_contents($step['fromTemplate']);

                // Apply tokenization on write too

                $content = $this->applyTokens($content, $this->lastVendor, $this->lastName, $this->lastNamespace, $this->lastDescription, $this->lastLicense);
            } else {
                // For generated content, rely on the preview

                $content = $step['contentPreview'];
            }

            @file_put_contents($path, $content);
        }
    }



    private function sanitize(string $s): string
    {

        // Allow letters, numbers, hyphen and underscore

        $s = preg_replace('~[^A-Za-z0-9_-]+~', '', $s) ?? '';

        return trim($s, '-_');
    }



    private function applyTokens(string $content, string $vendor, string $name, string $namespace, string $description, string $license): string
    {

        $repl = [

            '{{vendor}}' => $vendor,

            '{{name}}' => $name,

            '{{package}}' => strtolower($vendor) . '/' . strtolower($name),

            '{{namespace}}' => $namespace,

            '{{description}}' => $description,

            '{{license}}' => $license,

        ];

        return strtr($content, $repl);
    }



    private function preview(string $content): string
    {

        $max = 100000; // large to avoid truncation; ensures writes match previews for generated content

        if (strlen($content) <= $max) {
            return $content;
        }

        return substr($content, 0, $max) . "\n...";
    }
}
