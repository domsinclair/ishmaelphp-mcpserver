<?php

declare(strict_types=1);

namespace Ishmael\McpServer\Prompts\Roles;

use Ishmael\McpServer\Contracts\Prompt;

final class DeveloperPrompt implements Prompt
{
    /** @var array<string,mixed> */
    private array $arguments = [];

    public function getName(): string
    {
        return 'role:developer';
    }

    public function getDescription(): string
    {
        return 'Developer role: implement exactly what the architecture specifies, produce code and maintenance-grade documentation.';
    }

    public function getMessages(): array
    {
        $designSummary = (string)($this->arguments['designSummary'] ?? '');
        $notes = (string)($this->arguments['notes'] ?? '');

        $prompt = "You are the Developer. Your responsibility is to implement the approved architecture verbatim.\n\n"
            . "Do not change architecture decisions or introduce new features.\n\n"
            . "Deliverables:\n- Production-ready source code following Ishmael conventions.\n- implementation.notes.md (explain intent, rationale, and non-obvious decisions).\n- implementation.manifest.json (file list, commands executed, migrations, and routes touched).\n\n"
            . "Guidance:\n- Use ish CLI scaffolding where appropriate.\n- Write maintainable, well-documented code.\n- Ensure tests/build steps used by the project still pass.\n\n"
            . "Architecture Summary:\n$designSummary\n\nAdditional Notes (optional):\n$notes\n";

        return [ [ 'role' => 'user', 'content' => [ 'type' => 'text', 'text' => $prompt ] ] ];
    }

    public function getArguments(): array
    {
        return [
            [ 'name' => 'designSummary', 'description' => 'Approved architecture description and constraints.', 'required' => true ],
            [ 'name' => 'notes', 'description' => 'Any operational notes or branch information.', 'required' => false ],
        ];
    }

    public function withArguments(array $arguments): self
    {
        $this->arguments = $arguments;
        return $this;
    }
}
