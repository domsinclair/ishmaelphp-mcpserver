<?php

declare(strict_types=1);

namespace Ishmael\McpServer\Support;

use Ishmael\McpServer\Project\ProjectContext;

/**
 * Bridge to the Composer binary.
 *
 * Locates composer on the host and executes commands (require, update, etc.)
 * inside the project root via proc_open, returning the same result shape
 * used by IshCliBridge.
 */
final class ComposerBridge
{
    private ProjectContext $context;

    public function __construct(ProjectContext $context)
    {
        $this->context = $context;
    }

    /**
     * Execute a composer command.
     *
     * @param string               $command   e.g. 'require', 'update', 'dump-autoload'
     * @param array<int, string>   $packages  Positional package arguments (e.g. ['vendor/pack:^1.0'])
     * @param array<string, mixed> $flags     Flag options (e.g. ['no-interaction' => true, 'no-scripts' => true])
     * @return array{success: bool, output: string, error: ?string}
     */
    public function execute(string $command, array $packages = [], array $flags = []): array
    {
        $root = $this->context->getRoot();
        if ($root === null) {
            return [
                'success' => false,
                'output'  => '',
                'error'   => 'Project root not detected. Cannot run composer.',
            ];
        }

        $composer = $this->findComposer($root);
        if ($composer === null) {
            return [
                'success' => false,
                'output'  => '',
                'error'   => 'composer binary not found. Ensure composer is installed and on PATH.',
            ];
        }

        $php = PHP_BINARY ?: 'php';

        $cmdParts = [];

        if (str_ends_with($composer, '.phar')) {
            $cmdParts[] = escapeshellarg($php);
            $cmdParts[] = escapeshellarg($composer);
        } else {
            $cmdParts[] = escapeshellarg($composer);
        }

        $cmdParts[] = escapeshellarg($command);
        $cmdParts[] = '--no-interaction';
        $cmdParts[] = '--ansi';

        foreach ($flags as $key => $value) {
            if ($value === true) {
                $cmdParts[] = '--' . $key;
            } elseif ($value !== false && $value !== null) {
                $cmdParts[] = '--' . $key . '=' . escapeshellarg((string)$value);
            }
        }

        foreach ($packages as $pkg) {
            $cmdParts[] = escapeshellarg($pkg);
        }

        $cmd = implode(' ', $cmdParts);

        $descriptorspec = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $baseEnv = array_merge($_ENV, $_SERVER);
        $env = ['COMPOSER_HOME' => $root . DIRECTORY_SEPARATOR . '.composer'];
        foreach ($baseEnv as $key => $value) {
            if (is_scalar($value)) {
                $env[(string)$key] = (string)$value;
            }
        }

        $process = proc_open($cmd, $descriptorspec, $pipes, $root, $env);

        if (!is_resource($process)) {
            return [
                'success' => false,
                'output'  => '',
                'error'   => 'Failed to start composer process.',
            ];
        }

        fclose($pipes[0]);

        $stdout   = (string)stream_get_contents($pipes[1]);
        fclose($pipes[1]);

        $stderr   = (string)stream_get_contents($pipes[2]);
        fclose($pipes[2]);

        $exitCode = proc_close($process);
        $success  = ($exitCode === 0);

        return [
            'success' => $success,
            'output'  => trim($stdout),
            'error'   => $success ? null : trim($stderr ?: $stdout),
        ];
    }

    /**
     * Locate the composer binary in order of preference:
     *  1. composer / composer.bat on PATH
     *  2. composer.phar in project root
     *  3. vendor/bin/composer in project root
     */
    private function findComposer(string $root): ?string
    {
        $candidates = PHP_OS_FAMILY === 'Windows'
            ? ['composer.bat', 'composer.cmd', 'composer']
            : ['composer'];

        foreach ($candidates as $name) {
            $found = $this->which($name);
            if ($found !== null) {
                return $found;
            }
        }

        $local = [
            $root . DIRECTORY_SEPARATOR . 'composer.phar',
            $root . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'bin' . DIRECTORY_SEPARATOR . 'composer',
        ];

        foreach ($local as $path) {
            if (is_file($path)) {
                return $path;
            }
        }

        return null;
    }

    private function which(string $name): ?string
    {
        $cmd = PHP_OS_FAMILY === 'Windows'
            ? 'where ' . escapeshellarg($name) . ' 2>NUL'
            : 'which ' . escapeshellarg($name) . ' 2>/dev/null';

        $out = trim((string)shell_exec($cmd));
        if ($out === '') {
            return null;
        }

        $first = explode("\n", $out)[0];
        $first = trim($first);

        return ($first !== '' && is_file($first)) ? $first : null;
    }
}
