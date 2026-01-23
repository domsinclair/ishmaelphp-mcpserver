<?php

declare(strict_types=1);

namespace Ishmael\McpServer\Prompts;

use Ishmael\McpServer\Contracts\Prompt;
use Ishmael\McpServer\Support\IntentMapper;

final class IntentRouterPrompt implements Prompt
{
    private IntentMapper $mapper;
    private array $arguments = [];

    public function __construct(IntentMapper $mapper)
    {
        $this->mapper = $mapper;
    }

    public function getName(): string
    {
        return 'ishmael:intent-router';
    }

    public function getDescription(): string
    {
        return 'Analyzes user input to detect canonical Ishmael intents and provide authoritative guidance.';
    }

    public function getMessages(): array
    {
        $query = $this->arguments['query'] ?? '';
        $detection = $this->mapper->detect($query);
        $intentId = $detection['intent'];

        if ($intentId === null) {
            return [
                [
                    'role' => 'user',
                    'content' => ['type' => 'text', 'text' => $query]
                ],
                [
                    'role' => 'assistant',
                    'content' => [
                        'type' => 'text',
                        'text' => "I've analyzed your request but couldn't map it to a specific Ishmael canonical intent. \n\n" .
                                "To help me assist you better, could you please clarify if you are trying to:\n" .
                                "- Create or design a new Feature Pack (reusable module/plugin)?\n" .
                                "- Create a new local App Module for your project?\n" .
                                "- Add licensing or protection to an existing module?\n" .
                                "- Scaffold a brand new Ishmael project?\n\n" .
                                "You can also check `ish://docs/intent-map` for a full list of concepts I understand."
                    ]
                ]
            ];
        }

        $description = $this->mapper->getIntentDescription($intentId);
        $rules = $this->mapper->getClarificationRules($intentId);
        $contract = $this->mapper->getBehaviourContract($intentId);

        $response = "Detected Intent: **{$intentId}** ({$description})\n\n";

        if ($rules && !empty($rules['questions'])) {
            $response .= "### Clarifying Questions\n";
            $response .= "To provide the best guidance, please answer the following:\n";
            foreach ($rules['questions'] as $ctx => $question) {
                $response .= "- {$question}\n";
            }
            $response .= "\n";
        }

        if ($contract) {
            $response .= "### Guidance Parameters\n";
            if (!empty($contract['allowed_outputs'])) {
                $response .= "- **Allowed Outputs**: " . implode(', ', $contract['allowed_outputs']) . "\n";
            }
            if (!empty($contract['must_reference'])) {
                $response .= "- **Must Reference**: " . implode(', ', $contract['must_reference']) . "\n";
            }
            if (!empty($contract['forbidden'])) {
                $response .= "- **Strictly Forbidden**: " . implode(', ', $contract['forbidden']) . "\n";
            }
        }

        return [
            [
                'role' => 'user',
                'content' => ['type' => 'text', 'text' => $query]
            ],
            [
                'role' => 'assistant',
                'content' => [
                    'type' => 'text',
                    'text' => $response
                ]
            ]
        ];
    }

    public function getArguments(): array
    {
        return [
            [
                'name' => 'query',
                'description' => 'The user query or natural language request to analyze.',
                'required' => true
            ]
        ];
    }

    public function withArguments(array $arguments): self
    {
        $this->arguments = $arguments;
        return $this;
    }
}
