<?php

declare(strict_types=1);

namespace Ishmael\McpServer\Prompts;

use Ishmael\McpServer\Contracts\Prompt;

final class PlanFeaturePrompt implements Prompt
{
    /** @var array<string, mixed> */
    private array $arguments = [];

    public function getName(): string
    {
        return 'ishmael:plan-feature';
    }

    public function getDescription(): string
    {
        return 'Structured architectural planning for new features or Feature Packs in Ishmael.';
    }

    public function getMessages(): array
    {
        $requirements = $this->arguments['requirements'] ?? 'No requirements provided.';

        return [
            [
                'role' => 'user',
                'content' => [
                    'type' => 'text',
                    'text' => "I want to plan a new feature with the following requirements: {$requirements}"
                ]
            ],
            [
                'role' => 'assistant',
                'content' => [
                    'type' => 'text',
                    'text' => "To plan this feature effectively in Ishmael, we should consider the following architectural options:\n\n" .
                        "### 1. Identify the Scope\n" .
                        "- **App-Specific Feature**: If the logic is unique to this project, it should reside in a local module under `Modules/`.\n" .
                        "- **Reusable Capability (Feature Pack)**: If the logic is generic and could be used in other projects, it should be developed as a standalone Feature Pack (reusable module).\n\n" .
                        "### 2. Suggest Logic Placement\n" .
                        "- Use `project/info` and `ish:modules:dependencies` to understand existing module boundaries.\n" .
                        "- If a new module is needed: `ish:make:module <Name>`\n" .
                        "- If extending an existing module: identify the relevant Controller and Service.\n\n" .
                        "### 3. Proposed Workflow (Sequences of `make:` commands)\n" .
                        "1. **Scaffold Module/Feature**: `ish:make:module <Name>` (if new).\n" .
                        "2. **Identify Module Type**: Check `ish://config/manifest` for the module's `type`.\n" .
                        "3. **Define Routes**: Edit `Modules/<Name>/routes.php`.\n" .
                        "4. **Create Controller**: `ish:make:controller <Name> <ControllerName>` (Add `--api` if the module type is `api`).\n" .
                        "5. **Create Service**: `ish:make:service <Name> <ServiceName>` (for business logic).\n" .
                        "6. **Create Migration**: `ish:make:migration create_<table_name>_table` (if persistence is needed).\n\n" .
                        "### 4. Distinguishing Feature Packs\n" .
                        "- Feature Packs are standalone modules often distributed via Composer.\n" .
                        "- To create a new Feature Pack, use `ish:feature-pack:create` (if available) or follow the 'Creating a Feature Pack' guide.\n" .
                        "- To install an existing one, use `composer require` followed by `ish:feature-pack:install` or `ish:feature-pack:integrate`.\n\n" .
                        "### 5. Reference Documentation\n" .
                        "- Core Philosophy: `docs:ai-manifesto`\n" .
                        "- Framework Conventions: `docs:conventions`\n" .
                        "- Documentation Index: `ish://docs/index` (use this to find relevant tutorials or guides)\n" .
                        "- Licensing Strategy: `docs:licensing` (Consult before using premium capabilities)\n\n" .
                        "### 6. Licensing & Capabilities\n" .
                        "- Always check if the planned feature depends on premium modules or capabilities using `ish:capabilities:describe <ModuleName>`.\n" .
                        "- If a premium capability is required, check its availability status. If 'Trial Required', suggest starting a trial to the user.\n" .
                        "- Do not bypass licensing checks. If a `CapabilityException` occurs, it indicates a required license or trial." .
                        "Please refine your requirements if you need a more specific sequence of commands."
                ]
            ]
        ];
    }

    public function getArguments(): array
    {
        return [
            [
                'name' => 'requirements',
                'description' => 'Detailed requirements for the new feature.',
                'required' => true
            ]
        ];
    }

    /**
     * Set arguments for this prompt instance. 
     * Note: In a real MCP server, these would be passed via prompts/get.
     * We'll need to update StaticPromptProvider to handle this if it doesn't already.
     */
    public function withArguments(array $arguments): self
    {
        $this->arguments = $arguments;
        return $this;
    }
}
