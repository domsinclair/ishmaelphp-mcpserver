<?php

declare(strict_types=1);

namespace Ishmael\McpServer\Prompts;

use Ishmael\McpServer\Contracts\Prompt;

final class BestPracticesPrompt implements Prompt
{
    public function getName(): string
    {
        return 'ishmael:best-practices';
    }

    public function getDescription(): string
    {
        return 'Instructions on Ishmael best practices, emphasizing CLI usage over manual code generation.';
    }

    public function getMessages(): array
    {
        return [
            [
                'role' => 'assistant',
                'content' => [
                    'type' => 'text',
                    'text' => "When working with the IshmaelPHP framework, please follow these best practices:\n\n" .
                        "1. **Use the CLI for Scaffolding**: Always prefer using the `ish` CLI tools (e.g., `ish:make:module`, `ish:make:controller`) instead of writing PHP boilerplate manually. This ensures that namespaces, directory structures, and framework conventions are strictly followed.\n" .
                        "2. **Module-First Architecture**: Keep business logic organized within the `Modules` directory. Each module should be self-contained with its own controllers, routes, and views.\n" .
                        "3. **Environment Validation**: Before starting, run `ish:env:validate` to ensure the local environment meets the application's requirements.\n" .
                        "4. **Database Migrations**: Use `ish:migrate` and `ish:make:migration` to manage database schema changes rather than manual SQL execution."
                ]
            ]
        ];
    }

    public function getArguments(): array
    {
        return [];
    }
}
