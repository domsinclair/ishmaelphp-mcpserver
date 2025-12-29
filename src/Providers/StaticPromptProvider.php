<?php

    declare(strict_types=1);

    namespace Ishmael\McpServer\Providers;

    use Ishmael\McpServer\Contracts\PromptProvider;

    final class StaticPromptProvider implements PromptProvider
    {
        /** @var array<int, array<string, string>> */

        private array $prompts;



        /**

         * @param array<int, array<string, string>> $prompts

         */

        public function __construct(array $prompts)
        {

            $this->prompts = $prompts;
        }



        public function listPrompts(): array
        {

            return $this->prompts;
        }
    }
