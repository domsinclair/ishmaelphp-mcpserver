<?php

    declare(strict_types=1);

    namespace Ishmael\McpServer\Providers;

    use Ishmael\McpServer\Contracts\Prompt;
    use Ishmael\McpServer\Contracts\PromptProvider;

    final class StaticPromptProvider implements PromptProvider
    {
        /** @var array<int, array<string, string>> */
        private array $promptList;

        /** @var array<string, Prompt> */
        private array $prompts = [];

        /**
         * @param array<int, array<string, string>> $promptList
         * @param Prompt[] $prompts
         */
        public function __construct(array $promptList, array $prompts = [])
        {
            $this->promptList = $promptList;
            foreach ($prompts as $prompt) {
                $this->prompts[$prompt->getName()] = $prompt;
            }
        }

        public function listPrompts(): array
        {
            return $this->promptList;
        }

        public function getPrompt(string $name, array $arguments = []): ?Prompt
        {
            return $this->prompts[$name] ?? null;
        }
    }
