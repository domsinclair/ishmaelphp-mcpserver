<?php

    declare(strict_types=1);

    namespace IshmaelPHP\McpServer\FeaturePacks;

    /**

     * Reads an optional curated catalog JSON file packaged with the MCP server.

     * Expected shape: { "packs": [ { name, description, version, package, repoUrl, keywords, requires, stability } ] }

     */

    final class CuratedIndexScanner
    {
        private string $indexPath;



        public function __construct(?string $indexPath = null)
        {

            if ($indexPath !== null) {
                $this->indexPath = $indexPath;
            } else {
                // Default packaged location: tools/mcp/resources/feature-packs/index.json

                $base = dirname(__DIR__, 2);

                $this->indexPath = $base . DIRECTORY_SEPARATOR . 'resources' . DIRECTORY_SEPARATOR

                    . 'feature-packs' . DIRECTORY_SEPARATOR . 'index.json';
            }
        }



        /**

         * @param array{query?:string,vendorPrefix?:string,includePrerelease?:bool} $filters

         * @return array<int, array<string, mixed>>

         */

        public function list(array $filters = []): array
        {

            if (!is_file($this->indexPath)) {
                return [];
            }

            $raw = json_decode((string)file_get_contents($this->indexPath), true);

            if (!is_array($raw) || !isset($raw['packs']) || !is_array($raw['packs'])) {
                return [];
            }

            $items = [];

            foreach ($raw['packs'] as $pack) {
                if (!is_array($pack)) {
                    continue;
                }

                $pack['source'] = $pack['source'] ?? 'curated-index';

                if (!$this->matchesFilters($pack, $filters)) {
                    continue;
                }

                $items[] = $pack;
            }

            return $items;
        }



        /**

         * @param array<string,mixed> $pack

         * @param array<string,mixed> $filters

         */

        private function matchesFilters(array $pack, array $filters): bool
        {

            $query = isset($filters['query']) && is_string($filters['query']) ? strtolower($filters['query']) : null;

            if ($query !== null && $query !== '') {
                $hay = strtolower(($pack['name'] ?? '') . ' ' . ($pack['description'] ?? ''));

                if (strpos($hay, $query) === false) {
                    return false;
                }
            }

            $vendorPrefix = isset($filters['vendorPrefix']) && is_string($filters['vendorPrefix']) ? strtolower($filters['vendorPrefix']) : null;

            if ($vendorPrefix !== null && $vendorPrefix !== '') {
                $pkg = strtolower((string)($pack['package'] ?? ''));

                if ($pkg === '' || strpos($pkg, $vendorPrefix) !== 0) {
                    return false;
                }
            }

            $includePrerelease = (bool)($filters['includePrerelease'] ?? false);

            if (!$includePrerelease) {
                $stab = strtolower((string)($pack['stability'] ?? 'stable'));

                if ($stab !== 'stable') {
                    return false;
                }
            }

            return true;
        }
    }
