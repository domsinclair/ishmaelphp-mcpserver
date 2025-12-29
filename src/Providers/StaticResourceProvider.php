<?php

    declare(strict_types=1);

    namespace Ishmael\McpServer\Providers;

    use Ishmael\McpServer\Contracts\ResourceProvider;

    final class StaticResourceProvider implements ResourceProvider
    {
        /** @var array<int, array<string, string>> */

        private array $resources;



        /**

         * @param array<int, array<string, string>> $resources

         */

        public function __construct(array $resources)
        {

            $this->resources = $resources;
        }



        public function listResources(): array
        {

            return $this->resources;
        }
    }
