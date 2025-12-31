<?php

declare(strict_types=1);

namespace Ishmael\McpServer\Tools;

use Ishmael\McpServer\Contracts\Tool;
use Ishmael\McpServer\Project\ProjectContext;

/**
 * Synchronize documentation from GitHub or a local path to the project's Docs folder.
 */
final class DocsSyncTool implements Tool
{
    private ProjectContext $context;

    public function __construct(ProjectContext $context)
    {
        $this->context = $context;
    }

    public function getName(): string
    {
        return 'docs/sync';
    }

    public function getDescription(): string
    {
        return 'Synchronize documentation from the official GitHub repository or a local framework path.';
    }

    public function getInputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'source' => [
                    'type' => 'string',
                    'description' => 'Source to sync from: "github" (default) or an absolute local path to IshmaelPHP-Core.',
                    'default' => 'github',
                ],
            ],
        ];
    }

    public function getOutputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'content' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'type' => ['type' => 'string'],
                            'text' => ['type' => 'string'],
                        ],
                    ],
                ],
                'isError' => ['type' => 'boolean'],
            ],
        ];
    }

    public function execute(array $input): array
    {
        $root = $this->context->getRoot();
        if ($root === null) {
            return [
                'content' => [['type' => 'text', 'text' => 'Error: Project root not detected. Cannot sync docs.']],
                'isError' => true,
            ];
        }

        $source = $input['source'] ?? 'github';
        $targetDir = $root . DIRECTORY_SEPARATOR . 'Docs';

        if (!is_dir($targetDir)) {
            if (!mkdir($targetDir, 0755, true) && !is_dir($targetDir)) {
                return [
                    'content' => [['type' => 'text', 'text' => "Error: Failed to create target directory: $targetDir"]],
                    'isError' => true,
                ];
            }
        }

        try {
            if ($source === 'github') {
                return $this->syncFromGithub($targetDir);
            } else {
                return $this->syncFromLocal($source, $targetDir);
            }
        } catch (\Exception $e) {
            return [
                'content' => [['type' => 'text', 'text' => 'Error during sync: ' . $e->getMessage()]],
                'isError' => true,
            ];
        }
    }

    private function syncFromGithub(string $targetDir): array
    {
        // GitHub repository details
        $repo = 'domsinclair/IshmaelPHP-Core';
        $branch = 'main';
        $remoteDocsDir = 'Documentation';

        // We'll use GitHub's Zip download to avoid complex API calls for recursive listing
        $zipUrl = "https://github.com/$repo/archive/refs/heads/$branch.zip";
        
        $tempZip = tempnam(sys_get_temp_dir(), 'ish_docs_');
        if (!copy($zipUrl, $tempZip)) {
            return [
                'content' => [['type' => 'text', 'text' => 'Error: Failed to download documentation from GitHub.']],
                'isError' => true,
            ];
        }

        $zip = new \ZipArchive();
        if ($zip->open($tempZip) === true) {
            $extractPath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'ish_docs_extract_' . uniqid();
            $zip->extractTo($extractPath);
            $zip->close();
            unlink($tempZip);

            $sourcePath = $extractPath . DIRECTORY_SEPARATOR . "IshmaelPHP-Core-$branch" . DIRECTORY_SEPARATOR . $remoteDocsDir;
            
            if (is_dir($sourcePath)) {
                $count = $this->recursiveCopy($sourcePath, $targetDir);
                $this->recursiveDelete($extractPath);
                return [
                    'content' => [['type' => 'text', 'text' => "Successfully synchronized $count files from GitHub to $targetDir"]],
                ];
            } else {
                $this->recursiveDelete($extractPath);
                return [
                    'content' => [['type' => 'text', 'text' => "Error: Documentation folder not found in the downloaded repository."]],
                    'isError' => true,
                ];
            }
        }

        return [
            'content' => [['type' => 'text', 'text' => 'Error: Failed to open downloaded ZIP archive.']],
            'isError' => true,
        ];
    }

    private function syncFromLocal(string $sourcePath, string $targetDir): array
    {
        $sourcePath = rtrim($sourcePath, DIRECTORY_SEPARATOR);
        
        // Check if the source path is the root of IshmaelPHP-Core or the Documentation folder itself
        $docPath = $sourcePath;
        if (basename($sourcePath) !== 'Documentation' && is_dir($sourcePath . DIRECTORY_SEPARATOR . 'Documentation')) {
            $docPath = $sourcePath . DIRECTORY_SEPARATOR . 'Documentation';
        }

        if (!is_dir($docPath)) {
            return [
                'content' => [['type' => 'text', 'text' => "Error: Local documentation path not found: $docPath"]],
                'isError' => true,
            ];
        }

        $count = $this->recursiveCopy($docPath, $targetDir);
        return [
            'content' => [['type' => 'text', 'text' => "Successfully synchronized $count files from $docPath to $targetDir"]],
        ];
    }

    private function recursiveCopy(string $source, string $target): int
    {
        $count = 0;
        $dir = opendir($source);
        @mkdir($target);
        while (false !== ($file = readdir($dir))) {
            if (($file != '.') && ($file != '..')) {
                if (is_dir($source . '/' . $file)) {
                    $count += $this->recursiveCopy($source . '/' . $file, $target . '/' . $file);
                } else {
                    // Only copy markdown files or other relevant doc files
                    $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
                    if (in_array($ext, ['md', 'txt', 'png', 'jpg', 'jpeg', 'gif'])) {
                        if (copy($source . '/' . $file, $target . '/' . $file)) {
                            $count++;
                        }
                    }
                }
            }
        }
        closedir($dir);
        return $count;
    }

    private function recursiveDelete(string $dir): void
    {
        if (!is_dir($dir)) return;
        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            (is_dir("$dir/$file")) ? $this->recursiveDelete("$dir/$file") : unlink("$dir/$file");
        }
        rmdir($dir);
    }
}
