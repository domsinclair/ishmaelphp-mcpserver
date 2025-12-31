<?php

    declare(strict_types=1);

    namespace Ishmael\McpServer\Contracts;

    interface ResourceProvider
    {
        /** Return a list of available resources (identifiers and brief descriptions). */

        public function listResources(): array;



        /**
         * Read the content of a specific resource by its URI.
         * Returns null if the provider does not handle this URI.
         */
        public function readResource(string $uri): ?string;
    }
