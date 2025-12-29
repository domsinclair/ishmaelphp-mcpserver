<?php

    declare(strict_types=1);

    namespace Ishmael\McpServer\Support;

    use Ishmael\McpServer\Config\Settings;

    /**

     * In-memory result cache with TTL and simple file mtime based invalidation.

     */

    final class ResultCache
    {
        private Settings $settings;

        /** @var array<string, array{value:array, expires:int, snapshot:array<string,int>}> */

        private array $entries = [];



        public function __construct(Settings $settings)
        {

            $this->settings = $settings;
        }



        /** @return array<string,mixed>|null */

        public function get(string $method, string $key): ?array
        {

            $ttl = $this->settings->cacheTtls[$method] ?? 0;

            if ($ttl <= 0) {
                return null;
            }

            $e = $this->entries[$key] ?? null;

            if ($e === null) {
                return null;
            }

            if ($e['expires'] < time()) {
                unset($this->entries[$key]);

                return null;
            }

            // validate snapshot

            $watches = $this->settings->cacheWatchFiles[$method] ?? [];

            if ($this->hasChanged($watches, $e['snapshot'])) {
                unset($this->entries[$key]);

                return null;
            }

            return $e['value'];
        }



        /** @param array<string,mixed> $value */

        public function put(string $method, string $key, array $value): void
        {

            $ttl = $this->settings->cacheTtls[$method] ?? 0;

            if ($ttl <= 0) {
                return; // caching disabled for this method
            }

            $snapshot = $this->snapshot($this->settings->cacheWatchFiles[$method] ?? []);

            $this->entries[$key] = [

                'value' => $value,

                'expires' => time() + $ttl,

                'snapshot' => $snapshot,

            ];
        }



        /** @param string[] $patterns */

        private function snapshot(array $patterns): array
        {

            $files = $this->expandGlobs($patterns);

            $snap = [];

            foreach ($files as $f) {
                $snap[$f] = is_file($f) ? (int)filemtime($f) : 0;
            }

            return $snap;
        }



        /**

         * @param string[] $patterns

         * @param array<string,int> $previous

         */

        private function hasChanged(array $patterns, array $previous): bool
        {

            $current = $this->snapshot($patterns);

            // naive compare

            if (count($current) !== count($previous)) {
                return true;
            }

            foreach ($current as $file => $mtime) {
                if (!isset($previous[$file]) || $previous[$file] !== $mtime) {
                    return true;
                }
            }

            return false;
        }



        /** @param string[] $patterns @return string[] */

        private function expandGlobs(array $patterns): array
        {

            $out = [];

            foreach ($patterns as $p) {
                $matches = glob($p, GLOB_NOSORT | GLOB_BRACE) ?: [];

                foreach ($matches as $m) {
                    if (is_string($m)) {
                        $out[] = $m;
                    }
                }
            }

            return array_values(array_unique($out));
        }
    }
