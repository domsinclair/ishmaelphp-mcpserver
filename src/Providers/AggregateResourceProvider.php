<?php

    declare(strict_types=1);

    namespace Ishmael\McpServer\Providers;

    use Ishmael\McpServer\Contracts\ResourceProvider;

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
                    if (!is_array($item)) {
                        continue;
                    }

                    $key = $item['uri'] ?? $item['id'] ?? null;
                    if ($key === null) {
                        continue;
                    }

                    // de-dupe by uri or id
                    if (!array_key_exists($key, $all)) {
                        $all[$key] = $item;
                    }
                }
            }

            // return numeric-indexed array

            return array_values($all);
        }



        public function readResource(string $uri): ?string
        {
            foreach ($this->providers as $provider) {
                $content = $provider->readResource($uri);
                if ($content !== null) {
                    return $content;
                }
            }
            return null;
        }
    }
