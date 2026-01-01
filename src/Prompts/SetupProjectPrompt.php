<?php

declare(strict_types=1);

namespace Ishmael\McpServer\Prompts;

use Ishmael\McpServer\Contracts\Prompt;

final class SetupProjectPrompt implements Prompt
{
    public function getName(): string
    {
        return 'ishmael:setup-project';
    }

    public function getDescription(): string
    {
        return 'Guided walkthrough for setting up a new Ishmael project.';
    }

    public function getMessages(): array
    {
        return [
            [
                'role' => 'user',
                'content' => [
                    'type' => 'text',
                    'text' => "I want to set up my Ishmael project. What should I do first?"
                ]
            ],
            [
                'role' => 'assistant',
                'content' => [
                    'type' => 'text',
                    'text' => "Great! To get started with your Ishmael project, follow these steps:\n\n" .
                        "1. **Validate Environment**: Run the `ish:env:validate` tool to check for missing dependencies or configuration issues.\n" .
                        "2. **Initialize Database**: If you haven't already, run `ish:migrate` to set up your database schema.\n" .
                        "3. **Create your first module**: Use `ish:make:module <Name>` to start building your application logic.\n" .
                        "4. **Set up IDE**: If you are using PhpStorm, use `ide:setup-run-configs` to add Ishmael commands to your IDE Run menu."
                ]
            ]
        ];
    }

    public function getArguments(): array
    {
        return [];
    }
}
