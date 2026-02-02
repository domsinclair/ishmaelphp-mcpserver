<?php

declare(strict_types=1);

namespace Ishmael\McpServer\Prompts;

use Ishmael\McpServer\Contracts\Prompt;

final class DatabaseDesignPrompt implements Prompt
{
    /** @var array<string, mixed> */
    private array $arguments = [];

    public function getName(): string
    {
        return 'ishmael:database-design';
    }

    public function getDescription(): string
    {
        return 'Guidance for designing database schemas following Ishmael conventions.';
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
                'name' => 'intent',
                'description' => 'What are you trying to build? (e.g., a blog, a user management system)',
                'required' => true,
            ]
        ];
    }

    public function getMessages(): array
    {
        $intent = $this->arguments['intent'] ?? 'Not specified';

        return [
            [
                'role' => 'user',
                'content' => [
                    'type' => 'text',
                    'text' => "I want to design a database schema for: {$intent}"
                ]
            ],
            [
                'role' => 'assistant',
                'content' => [
                    'type' => 'text',
                    'text' => "Designing a database schema the 'Ishmael Way' involves adhering to specific conventions and using the right tools. Here is how we should proceed:\n\n" .
                        "### 1. Identify Tables and Relationships\n" .
                        "- Based on your intent, what are the primary entities?\n" .
                        "- How do they relate to each other (1:1, 1:N, N:M)?\n\n" .
                        "### 2. Apply Ishmael Conventions\n" .
                        "- **Table Names**: Use `snake_case` and plural (e.g., `blog_posts`).\n" .
                        "- **Primary Key**: Use `{singular_table}_id` (e.g., `blog_post_id`).\n" .
                        "- **Foreign Keys**: Use `{singular_table}_id` (e.g., `user_id`).\n" .
                        "- **Timestamps**: Always include `created_at` and `updated_at`.\n" .
                        "- **Auditing & Soft Deletes**: Ishmael supports `created_by`, `updated_by`, and `deleted_at`.\n" .
                        "    - *Tip*: Ask the user if they need these. Auditing is good for accountability; soft deletes prevent accidental data loss but add query complexity.\n\n" .
                        "### 3. Current Project State\n" .
                        "- Before creating new tables, let's see what already exists: `ish://database/schema`.\n" .
                        "- Check the database configuration: `ish:env:validate`.\n\n" .
                        "### 4. Proposed Workflow\n" .
                        "1. **Create Migration**: `ish:make:migration create_tableName_table`.\n" .
                        "2. **Define Schema**: Edit the generated migration file using the Ishmael Schema Manager patterns.\n" .
                        "3. **Analyze Migration**: Run `ish:migrate:analyze` to check for data loss or performance risks.\n" .
                        "4. **Run Migration**: `ish:migrate`.\n" .
                        "5. **Seed Data (Optional)**: `ish:make:seeder TableNameSeeder`.\n\n" .
                        "### 5. Reference Documentation\n" .
                        "- Database Conventions: `docs:conventions` (Section 1)\n" .
                        "- Migration Guide: `docs:guide/writing-and-running-migrations` (if available)\n" .
                        "- Schema Manager: `docs:guide/using-schema-manager-safely` (if available)\n\n" .
                        "Do you have a specific list of columns in mind, or should I suggest a starting point?"
                ]
            ]
        ];
    }
}
