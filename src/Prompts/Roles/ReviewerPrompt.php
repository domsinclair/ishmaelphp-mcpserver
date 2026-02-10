<?php

declare(strict_types=1);

namespace Ishmael\McpServer\Prompts\Roles;

use Ishmael\McpServer\Contracts\Prompt;

final class ReviewerPrompt implements Prompt
{
    /** @var array<string,mixed> */
    private array $arguments = [];

    public function getName(): string
    {
        return 'role:reviewer';
    }

    public function getDescription(): string
    {
        return 'Reviewer role: verify implementation against analysis and architecture, produce compliance report. Outputs review.report.md and review.report.json.';
    }

    public function getMessages(): array
    {
        $analysisRef = (string)($this->arguments['analysisRef'] ?? '');
        $designRef = (string)($this->arguments['designRef'] ?? '');
        $implementationRef = (string)($this->arguments['implementationRef'] ?? '');

        $prompt = "You are the Reviewer. Validate that the implementation matches the approved analysis and architecture.\n\n"
            . "Do not modify code or architecture.\n\n"
            . "Deliverables:\n- review.report.md (narrative with identified issues and pass/fail conclusions)\n- review.report.json (machine-readable with violations, severities, and recommendations)\n\n"
            . "Compliance Scope:\n- License compatibility and dependency chain validation.\n- Documentation quality and completeness.\n- Deviation from architecture or requirements.\n\n"
            . "References:\n$analysisRef\n$designRef\n$implementationRef\n";

        return [ [ 'role' => 'user', 'content' => [ 'type' => 'text', 'text' => $prompt ] ] ];
    }

    public function getArguments(): array
    {
        return [
            [ 'name' => 'analysisRef', 'description' => 'Path/summary for analysis artifacts.', 'required' => true ],
            [ 'name' => 'designRef', 'description' => 'Path/summary for design artifacts.', 'required' => true ],
            [ 'name' => 'implementationRef', 'description' => 'Path/summary for implementation artifacts.', 'required' => true ],
        ];
    }

    public function withArguments(array $arguments): self
    {
        $this->arguments = $arguments;
        return $this;
    }
}
