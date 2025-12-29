<?php

    declare(strict_types=1);

    namespace Ishmael\McpServer\Contracts;

    /**

     * A deterministic MCP Tool with explicit input and output schemas.

     */

    interface Tool
    {
        /** Returns the unique method name exposed over MCP (e.g., "health/version"). */

        public function getName(): string;



        /** Human-readable description for discovery listings. */

        public function getDescription(): string;



        /** JSON schema for input parameters (as associative array). */

        public function getInputSchema(): array;



        /** JSON schema for output result (as associative array). */

        public function getOutputSchema(): array;



        /** Execute the tool with validated input and return result data. */

        public function execute(array $input): array;
    }
