<?php

declare(strict_types=1);

namespace Ishmael\McpServer\Support;

use Ishmael\McpServer\Project\ProjectContext;

/**
 * Bridge to the native Ishmael CLI (bin/ish).
 */
final class IshCliBridge
{
    private ProjectContext $context;

    public function __construct(ProjectContext $context)
    {
        $this->context = $context;
    }

    /**
     * Execute an 'ish' command.
     *
     * @param string $command The command name (e.g., 'make:module')
     * @param array<string, mixed> $options Key-value options (e.g., ['name' => 'Blog', 'api' => true])
     * @param array<int, string> $arguments Positional arguments
     * @return array{success: bool, output: string, error: ?string, files: string[], preview?: array<int, array{path: string, content: string}>}
     */
    public function execute(string $command, array $options = [], array $arguments = []): array
    {
        $ish = $this->context->getIshBinary();
        if ($ish === null) {
            return [
                'success' => false,
                'output' => '',
                'error' => 'ish binary not found. Ensure you are in an Ishmael project root.',
                'files' => [],
            ];
        }

        $root = $this->context->getRoot();
        $php = PHP_BINARY ?: 'php';

        $cmdParts = [escapeshellarg($php), escapeshellarg($ish), escapeshellarg($command)];

        foreach ($arguments as $arg) {
            $cmdParts[] = escapeshellarg((string)$arg);
        }

        foreach ($options as $key => $value) {
            if ($value === true) {
                $cmdParts[] = '--' . $key;
            } elseif ($value !== false && $value !== null) {
                $cmdParts[] = '--' . $key . '=' . escapeshellarg((string)$value);
            }
        }

        $cmd = implode(' ', $cmdParts);

        $descriptorspec = [
            0 => ["pipe", "r"], // stdin
            1 => ["pipe", "w"], // stdout
            2 => ["pipe", "w"]  // stderr
        ];

        // Ensure ISH_APP_BASE is set to project root
        // Filter out non-scalar values from environment to avoid proc_open warnings
        $baseEnv = array_merge($_ENV, $_SERVER);
        $env = [
            'ISH_APP_BASE' => $root,
            'ISH_BOOTSTRAP_ONLY' => '1',
        ];

        foreach ($baseEnv as $key => $value) {
            if (is_scalar($value)) {
                $env[(string)$key] = (string)$value;
            }
        }

        $process = proc_open($cmd, $descriptorspec, $pipes, $root, $env);

        if (!is_resource($process)) {
            return [
                'success' => false,
                'output' => '',
                'error' => 'Failed to execute ish process.',
                'files' => [],
            ];
        }

        fclose($pipes[0]);

        $stdout = stream_get_contents($pipes[1]);
        fclose($pipes[1]);

        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[2]);

        $exitCode = proc_close($process);

        $success = ($exitCode === 0);
        $files = $this->parseCreatedFiles($stdout);

        $result = [
            'success' => $success,
            'output' => trim($stdout),
            'error' => $success ? null : trim($stderr ?: $stdout),
            'files' => $files,
        ];

        if (!empty($options['preview'])) {
            $result['preview'] = $this->parsePreviewOutput($stdout);
        }

        return $result;
    }

    /**
     * Parse the preview output from ish binary.
     *
     * @return array<int, array{path: string, content: string}>
     */
    private function parsePreviewOutput(string $output): array
    {
        $previews = [];
        $pattern = '/---PREVIEW-START---\s+Path:\s+(.*?)\s+Content:\s+(.*?)\s+---PREVIEW-END---/s';
        if (preg_match_all($pattern, $output, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $previews[] = [
                    'path' => trim($match[1]),
                    'content' => trim($match[2]),
                ];
            }
        }
        return $previews;
    }

    /**
     * Parse the output for "Created: <path>" lines.
     *
     * @return string[] Absolute paths
     */
    private function parseCreatedFiles(string $output): array
    {
        $files = [];
        $lines = explode("\n", $output);
        foreach ($lines as $line) {
            $line = trim($line);
            // Patterns from bin/ish:
            // "Created: <path> (discoverable)"
            // "Module scaffolded at: <path>"
            // "Controller created at: <path>"
            // "Service created at: <path>"
            // "View created at: <path>"
            // "Seeder created at: <path>"
            // "Resource scaffolded: <Module>/<Resource>" -> This one doesn't give a path directly, but we can infer it
            // "Views created for resource: <Module>/<Resource>" -> Same as above

            if (preg_match('/^(?:Created|Module scaffolded at|Controller created at|Service created at|View created at|Seeder created at): (.*)$/i', $line, $matches)) {
                $path = trim($matches[1]);
                // Remove "(discoverable)" or other suffixes if present
                $path = preg_replace('/ \(.*\)$/', '', $path);

                if (file_exists($path) || is_dir($path)) {
                    $files[] = realpath($path) ?: $path;
                }
            } elseif (preg_match('/^Resource scaffolded: (.*)$/i', $line, $matches)) {
                // Infer path for Resource scaffolded: Module/Resource
                $parts = explode('/', $matches[1]);
                if (count($parts) === 2) {
                    $root = $this->context->getRoot();
                    $resourcePath = $root . DIRECTORY_SEPARATOR . 'Modules' . DIRECTORY_SEPARATOR . $parts[0];
                    if (is_dir($resourcePath)) {
                        $files[] = realpath($resourcePath) ?: $resourcePath;
                    }
                }
            }
        }
        return array_values(array_unique($files));
    }
}
