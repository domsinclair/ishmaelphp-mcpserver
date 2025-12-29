<?php

    declare(strict_types=1);

    namespace IshmaelPHP\McpServer\FeaturePacks;

    use IshmaelPHP\McpServer\Project\PathSandbox;

    /**

     * Scans local Templates/FeaturePacks to build a lightweight catalog for ish:featurePack:list.

     */

    final class LocalTemplateScanner
    {
        private PathSandbox $sandbox;

        private string $templatesRoot;



        public function __construct(PathSandbox $sandbox, string $projectRoot)
        {

            $this->sandbox = $sandbox;

            $this->templatesRoot = rtrim($projectRoot, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'Templates' . DIRECTORY_SEPARATOR . 'FeaturePacks';
        }



        /**

         * @param array{name?:string,query?:string,vendorPrefix?:string,includePrerelease?:bool} $filters

         * @return array<int, array<string, mixed>>

         */

        public function list(array $filters = []): array
        {

            $items = [];

            if (!$this->sandbox->isWithinRoot($this->templatesRoot) || !is_dir($this->templatesRoot)) {
                return $items;
            }

            $dirs = @scandir($this->templatesRoot) ?: [];

            foreach ($dirs as $dir) {
                if ($dir === '.' || $dir === '..') {
                    continue;
                }

                $full = $this->templatesRoot . DIRECTORY_SEPARATOR . $dir;

                if (!is_dir($full)) {
                    continue;
                }



                $pack = $this->readPackMetadata($full, $dir);

                if (!$this->matchesFilters($pack, $filters)) {
                    continue;
                }

                $items[] = $pack;
            }



            return $items;
        }



        /**

         * @return array<string, mixed>

         */

        private function readPackMetadata(string $dir, string $name): array
        {

            $description = null;

            $readme = $dir . DIRECTORY_SEPARATOR . 'README.md';

            if (is_file($readme)) {
                $first = trim((string)preg_split("~\r?\n~", (string)file_get_contents($readme), 2)[0]);

                if ($first !== '') {
                    $description = ltrim($first, "# \t");
                }
            }



            $composerPath = $dir . DIRECTORY_SEPARATOR . 'composer.json';

            $requires = [];

            $keywords = [];

            $version = '0.1.0';

            $packageName = null;

            $stability = 'stable';

            if (is_file($composerPath)) {
                $data = json_decode((string)file_get_contents($composerPath), true);

                if (is_array($data)) {
                    $requires = isset($data['require']) && is_array($data['require']) ? array_keys($data['require']) : [];

                    $keywords = isset($data['keywords']) && is_array($data['keywords']) ? array_values($data['keywords']) : [];

                    if (isset($data['version']) && is_string($data['version'])) {
                        $version = $data['version'];
                    }

                    if (isset($data['name']) && is_string($data['name'])) {
                        $packageName = $data['name'];
                    }

                    if (isset($data['minimum-stability']) && is_string($data['minimum-stability'])) {
                        $stability = $data['minimum-stability'];
                    }

                    if ($description === null && isset($data['description']) && is_string($data['description'])) {
                        $description = $data['description'];
                    }
                }
            }



            return [

                'name' => $name,

                'description' => $description ?? ('Feature Pack template: ' . $name),

                'version' => $version,

                'package' => $packageName,

                'repoUrl' => null,

                'keywords' => $keywords,

                'requires' => $requires,

                'stability' => $stability,

                'source' => 'local-template',

                'path' => $dir,

            ];
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
