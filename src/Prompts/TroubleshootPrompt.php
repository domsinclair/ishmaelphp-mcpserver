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
                        "To provide a comprehensive diagnostic report, please follow these steps and share the output of each:\n\n" .
                        "1. **Project Health**: Run `ish://project/health` to check the general status of the application and its environment.\n" .
                        "2. **Log Analysis**: Run `ish:log:tail` (with `lines=50`) to see the most recent errors and stack traces in the application logs.\n" .
                        "3. **Environment Validation**: Run `ish:env:validate` to ensure all required extensions and environment variables are correctly configured.\n" .
                        "4. **Route Integrity**: If the error is related to a specific URL, run `ish:listRoutes` with a `filter` for that path to verify the handler exists.\n" .
                        "5. **Container Status**: If you suspect a dependency injection issue, run `ish:container:describe` to see registered services.\n\n" .
                        "Once you have gathered this information, I can analyze it to identify the root cause and suggest a fix."
                ]
            ]
        ];
    }
}
