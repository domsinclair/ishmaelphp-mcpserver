<?php

declare(strict_types=1);

namespace Ishmael\McpServer\Prompts;

use Ishmael\McpServer\Contracts\Prompt;

final class TroubleshootPrompt implements Prompt
{
    /** @var array<string, mixed> */
    private array $arguments = [];

    public function getName(): string
    {
        return 'ishmael:troubleshoot';
    }

    public function getDescription(): string
    {
        return 'Automated diagnostic gathering for troubleshooting Ishmael project errors.';
    }

    public function withArguments(array $arguments): self
    {
        $clone = clone $this;
        $clone->arguments = $arguments;
        return $clone;
    }

    public function getArguments(): array
    {
        return [
            [
                'name' => 'error_message',
                'description' => 'The error message or symptoms you are encountering.',
                'required' => false,
            ]
        ];
    }

    public function getMessages(): array
    {
        $errorMessage = $this->arguments['error_message'] ?? 'No specific error message provided.';

        return [
            [
                'role' => 'assistant',
                'content' => [
                    'type' => 'text',
                    'text' => "I will help you troubleshoot the issue: \"{$errorMessage}\"\n\n" .
                        "To provide a comprehensive diagnostic report, please follow these steps:\n\n" .
                        "1. **Generate Diagnostic Snapshot**: Run `ish:project:snapshot`. This tool bundles project info, environment validation, and the most recent errors with mapped stack traces into a single report.\n" .
                        "2. **Log Analysis**: If the snapshot doesn't contain enough detail, run `ish:log:tail` (with `maxItems=50`) to see more history. Note that stack traces are automatically mapped to project-relative paths.\n" .
                        "3. **Route Integrity**: If the error is related to a specific URL, run `ish:listRoutes` with a `filter` for that path to verify the handler exists.\n" .
                        "4. **Container Status**: If you suspect a dependency injection issue, run `ish:container:describe` to see registered services.\n\n" .
                        "Once you have shared the output of these tools, I can analyze the root cause (e.g., identifying the exact file and line number from the mapped stack trace) and suggest a fix."
                ]
            ]
        ];
    }
}
