<?php

    declare(strict_types=1);

    namespace IshmaelPHP\McpServer\Providers;

    use IshmaelPHP\McpServer\Contracts\ResourceProvider;

    /**

     * Simple aggregator that merges resource lists from multiple providers.

     */

    final class AggregateResourceProvider implements ResourceProvider
    {
        /** @var ResourceProvider[] */

        private array $providers;



        /**

         * @param ResourceProvider[] $providers

         */

        public function __construct(array $providers)
        {

            $this->providers = $providers;
        }



        public function listResources(): array
        {

            $all = [];

            foreach ($this->providers as $provider) {
                $list = $provider->listResources();

                if (!is_array($list)) {
                    continue;
                }

                foreach ($list as $item) {
                    if (!is_array($item) || !isset($item['id'])) {
                        continue;
                    }

                    // Preserve the first occurrence of a resource id so earlier providers

                    // (e.g., explicit static resources in tests) are not overridden by later

                    // providers that might add extra fields (like path) or different descriptions.

                    if (!array_key_exists($item['id'], $all)) {
                        $all[$item['id']] = $item; // de-dupe by id
                    }
                }
            }

            // return numeric-indexed array

            return array_values($all);
        }
    }
