<?php

declare(strict_types=1);

namespace Ishmael\McpServer\Prompts\Roles;

use Ishmael\McpServer\Contracts\Prompt;

final class ArchitectPrompt implements Prompt
{
    /** @var array<string,mixed> */
    private array $arguments = [];

    public function getName(): string
    {
        return 'role:architect';
    }

    public function getDescription(): string
    {
        return 'Architect role: translate approved requirements into a concrete technical design. Outputs architecture.design.md and architecture.design.json.';
    }

    public function getMessages(): array
    {
        $analysisSummary = (string)($this->arguments['analysisSummary'] ?? '');
        $constraints = (string)($this->arguments['constraints'] ?? '');
        $mode = (string)($this->arguments['mode'] ?? 'strict');

        $prompt = "You are the Architect. Your responsibility is to produce a concrete, testable technical design based strictly on the approved requirements.\n\n"
            . "Current Orchestration Mode: **" . strtoupper($mode) . "**\n\n"
            . "Do not write production code. Do not change requirements.\n\n";

        if ($mode === 'strict') {
            $prompt .= "### STRICT MODE ENFORCEMENT\n"
                . "- You MUST NOT define constructs not supported by the Ishmael framework.\n"
                . "- You MUST ONLY use tool-discovered capabilities (from `ish:capabilities:describe` or `FrameworkIntrospectionProvider`).\n"
                . "- Your design MUST be valid for IshmaelPHP. Do not assume Laravel or Symfony patterns.\n\n";
        }

        $prompt .= "Deliverables:\n- architecture.design.md (narrative with diagrams/sections as text)\n- architecture.design.json (machine-readable spec including APIs, data models, and dependencies)\n\n"
            . "Guidance:\n"
            . "- Select libraries and frameworks consistent with Ishmael conventions.\n"
            . "- **Mandatory**: Use the `ish:featurePack:registry` tool to search for existing capabilities or feature packs that satisfy these requirements. If a suitable feature pack exists, specify it as a dependency in your design.\n"
            . "- Define data schemas and migrations as needed.\n"
            . "- Declare Feature Pack dependencies (by name) without installing or coding them.\n\n"
            . "Approved Requirements Summary (for reference):\n$analysisSummary\n\nConstraints (optional):\n$constraints\n";

        return [ [ 'role' => 'user', 'content' => [ 'type' => 'text', 'text' => $prompt ] ] ];
    }

    public function getArguments(): array
    {
        return [
            [ 'name' => 'analysisSummary', 'description' => 'Approved analysis summary and acceptance criteria.', 'required' => true ],
            [ 'name' => 'constraints', 'description' => 'Technical or organizational constraints that affect design.', 'required' => false ],
            [ 'name' => 'mode', 'description' => 'Current orchestration mode (strict, guided, freeform).', 'required' => false ],
        ];
    }

    public function withArguments(array $arguments): self
    {
        $this->arguments = $arguments;
        return $this;
    }
}
