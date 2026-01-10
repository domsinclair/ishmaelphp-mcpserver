<?php

declare(strict_types=1);

namespace Ishmael\McpServer\Support;

/**
 * Parses log messages for stack traces and maps file paths to the project root.
 */
final class StackTraceMapper
{
    private ?string $root;

    public function __construct(?string $root)
    {
        $this->root = $root;
    }

    /**
     * @param string $message
     * @return array{message: string, stack_trace: array<int, array{file: string, line: int, function?: string}>}
     */
    public function map(string $message): array
    {
        $stackTrace = [];
        $lines = explode("\n", $message);
        $cleanMessage = $lines[0]; // Assume first line is the error message

        // Match frames: #0 /path/to/file.php(123): function() OR #1 [internal function]: ...
        $pattern = '/#\d+\s+(?P<file>.*?)(?:\((?P<line>\d+)\))?(?::\s+(?P<function>.*))?$/';

        foreach ($lines as $line) {
            if (preg_match($pattern, trim($line), $matches)) {
                $file = trim($matches['file']);
                $lineNumber = isset($matches['line']) && $matches['line'] !== '' ? (int)$matches['line'] : 0;
                $function = $matches['function'] ?? null;

                $resolved = $this->resolveRelativePath($file);
                $stackTrace[] = [
                    'file' => $resolved,
                    'line' => $lineNumber,
                    'function' => $function,
                ];
            }
        }

        return [
            'message' => $cleanMessage,
            'stack_trace' => $stackTrace,
        ];
    }

    private function resolveRelativePath(string $filePath): string
    {
        if ($this->root === null || $filePath === "[internal function]") {
            return $filePath;
        }

        $root = str_replace('\\', '/', $this->root);
        $file = str_replace('\\', '/', $filePath);

        $rootPath = rtrim($root, '/');
        $rootLower = strtolower($rootPath);
        $fileLower = strtolower($file);

        if ($rootLower !== '' && str_starts_with($fileLower, $rootLower)) {
            $relative = substr($file, strlen($rootLower));
            $relative = ltrim($relative, '/');
            return $relative === '' ? $filePath : str_replace('/', DIRECTORY_SEPARATOR, $relative);
        }

        return str_replace('/', DIRECTORY_SEPARATOR, $filePath);
    }
}
