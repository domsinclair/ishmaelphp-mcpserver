<?php

    declare(strict_types=1);

    namespace IshmaelPHP\McpServer\Contracts;

    interface ResourceProvider
    {
        /** Return a list of available resources (identifiers and brief descriptions). */

        public function listResources(): array;
    }
