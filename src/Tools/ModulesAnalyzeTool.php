<?php

declare(strict_types=1);

namespace Ishmael\McpServer\Tools;

use Ishmael\McpServer\Contracts\Tool;
use Ishmael\McpServer\Project\ProjectContext;
use Ishmael\McpServer\Support\RefactoringAdvisor;

/**
 * ish:modules:analyze — Analyze the project for refactoring opportunities.
 */
final class ModulesAnalyzeTool implements Tool
{
    private ProjectContext $context;

    public function __construct(ProjectContext $context)
    {
        $this->context = $context;
    }

    public function getName(): string
    {
        return 'ish:modules:analyze';
    }

    public function getDescription(): string
    {
        return 'Analyze the project for "logic duplication" and "cross-module coupling" to identify candidates for shared base modules or interfaces.';
    }

    public function getInputSchema(): array
    {
        return [
            'type' => 'object',
            'additionalProperties' => false,
            'properties' => [],
        ];
    }

    public function getOutputSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['opportunities', 'coupling'],
            'properties' => [
                'opportunities' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'type' => ['type' => 'string'],
                            'description' => ['type' => 'string'],
                            'modules' => ['type' => 'array', 'items' => ['type' => 'string']],
                            'impact' => ['type' => 'string'],
                        ],
                    ],
                ],
                'coupling' => [
                    'type' => 'object',
                    'additionalProperties' => [
                        'type' => 'object',
                        'properties' => [
                            'score' => ['type' => 'integer'],
                            'reason' => ['type' => 'string'],
                        ],
                    ],
                ],
            ],
        ];
    }

    public function execute(array $input): array
    {
        try {
            $advisor = new RefactoringAdvisor($this->context);
            return $advisor->analyze();
        } catch (\Throwable $e) {
            fwrite(STDERR, "[ModulesAnalyzeTool] Fatal error: " . $e->getMessage() . "\n");
            return [
                'opportunities' => [],
                'coupling' => [],
                'error' => 'Analysis failed: ' . $e->getMessage(),
            ];
        }
    }
}
