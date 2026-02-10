<?php

declare(strict_types=1);

namespace Ishmael\McpServer\Prompts\Roles;

use Ishmael\McpServer\Contracts\Prompt;

final class AnalystPrompt implements Prompt
{
    /** @var array<string,mixed> */
    private array $arguments = [];

    public function getName(): string
    {
        return 'role:analyst';
    }

    public function getDescription(): string
    {
        return 'Analyst role: clarify problem, gather requirements, and formalize intent. Outputs analysis.problem.md and analysis.problem.json.';
    }

    /**
     * @return array<int, array{role: string, content: array{type: string, text: string}}>
     */
    public function getMessages(): array
    {
        $context = (string)($this->arguments['context'] ?? '');
        $constraints = (string)($this->arguments['constraints'] ?? '');

        $prompt = "You are the Analyst. Your sole responsibility is to clarify the problem and formalize requirements.\n\n"
            . "Do not propose architecture, technologies, or write code.\n\n"
            . "Deliverables:\n- analysis.problem.md (narrative)\n- analysis.problem.json (machine-readable schema)\n\n"
            . "Guidance:\n- Ask clarifying questions if needed.\n- Validate scope, inputs, outputs, and acceptance criteria.\n- Identify risks and assumptions.\n\n"
            . "Context (optional):\n$context\n\nConstraints (optional):\n$constraints\n";

        return [
            [ 'role' => 'user', 'content' => [ 'type' => 'text', 'text' => $prompt ] ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function getArguments(): array
    {
        return [
            [ 'name' => 'context', 'description' => 'Any initial context or user request summary.', 'required' => false ],
            [ 'name' => 'constraints', 'description' => 'Constraints, non-goals, timelines, or dependencies.', 'required' => false ],
        ];
    }

    public function withArguments(array $arguments): self
    {
        $this->arguments = $arguments;
        return $this;
    }
}
