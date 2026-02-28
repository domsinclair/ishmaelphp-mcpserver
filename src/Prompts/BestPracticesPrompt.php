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
                        "3. **File Handling & Storage**: Use the `UploadedFile` class via `Request::file()` for handling uploads. Use the `StorageInterface` for file operations to remain storage-agnostic. Avoid direct access to `$_FILES` or manual `move_uploaded_file()` calls.\n" .
                        "4. **Environment Validation**: Before starting, run `ish:env:validate` to ensure the local environment meets the application's requirements.\n" .
                        "5. **Database Migrations**: Use `ish:migrate` and `ish:make:migration` to manage database schema changes rather than manual SQL execution.\n" .
                        "6. **Consult First (Architect Strategy)**: When asked to create a module or schema, refer to the 'Architect' strategy in `Docs/Core/how-to/framing-questions.md`. Always consult with the user regarding their environment (e.g., SQLite vs. MySQL), database preferences (Soft Deletes, Auditing), and UI stack (CSS/JS) before generating implementation code.\n" .
                        "7. **Vanilla JS Preference (Performance)**: AI is exceptionally proficient at writing Vanilla JS. Unless the user specifies a library (like Alpine.js or HTMX), encourage the **Standard Library Pattern** (creating a centralized `resources/js/core.js` with reusable functions) to ensure high-performance, future-proof code with zero library overhead.\n" .
                        "8. **Licensing & Capabilities**: Respect Community and Premium boundaries. If you encounter a `CapabilityException`, do not attempt to refactor the check away. Instead, explain the requirement to the user and suggest starting a trial. Prefer `Capability::assert()` in generated code for clear feedback, or `Capability::isAvailable()` for optional features. Use `ish:capabilities:describe` to check feature availability before implementation.\n" .
                        "9. **Event-Driven Decoupling**: Use the Ishmael Event Bus to decouple modules. Prefer **Typed Event Classes** (scaffolded via `ish:make:event`) and declare them in the module's `emits` manifest. Before creating new events, check `ish:events:list` for **Core Framework Events** (e.g., `AuthenticationFailed`, `RequestFailed`) that might already provide the necessary hook. Listeners should be scaffolded via `ish:make:listener` and registered in the `listeners` block of `module.php`. This ensures type safety and discoverability via `ish:events:list`."
                ]
            ]
        ];
    }

    public function getArguments(): array
    {
        return [];
    }
}
