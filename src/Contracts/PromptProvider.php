<?php

    declare(strict_types=1);

    namespace Ishmael\McpServer\Contracts;

    interface PromptProvider
    {
        /** Return a list of available prompts (identifiers and brief descriptions). */

        public function listPrompts(): array;
    }
