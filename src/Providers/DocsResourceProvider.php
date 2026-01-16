<?php

    declare(strict_types=1);

    namespace Ishmael\McpServer\Providers;

    use Ishmael\McpServer\Contracts\ResourceProvider;
    use Ishmael\McpServer\Project\PathSandbox;

    /**

     * Exposes curated docs as read-only resource identifiers (docs:*).

     * This is a lightweight indexer that scans common docs locations in the repo:

     * - Docs/ (markdown sources)

     * - site/ (built docs, html)

     * If these folders do not exist (e.g., when installed as a package), it returns an empty list.

     */

    final class DocsResourceProvider implements ResourceProvider
    {
        private PathSandbox $sandbox;

        /** @var string[] */

        private array $roots;



        /**

         * @param string[] $roots Absolute paths to candidate docs roots under sandbox root.

         */

        public function __construct(PathSandbox $sandbox, array $roots)
        {

            $this->sandbox = $sandbox;

            $this->roots = $roots;
        }



    public function listResources(): array
    {
        $items = [
            [
                'uri' => 'ish://docs/index',
                'name' => 'Ishmael Documentation Index',
                'description' => 'A structured map of all available documentation, categorized by type.',
                'mimeType' => 'application/json',
            ]
        ];

        foreach ($this->roots as $root) {
            // Only index if within sandbox and directory exists
            if (!$this->sandbox->isWithinRoot($root) || !is_dir($root)) {
                continue;
            }

            $this->scanDocsRoot($root, $items);
        }

        // De-dupe by id
        $byId = [];
        foreach ($items as $it) {
            $byId[$it['uri']] = $it;
        }

        return array_values($byId);
    }

    public function readResource(string $uri): ?string
    {
        if ($uri === 'ish://docs/index') {
            return $this->generateDocsIndex();
        }

        if (!str_starts_with($uri, 'docs:')) {
            return null;
        }

        $resources = $this->listResources();
        foreach ($resources as $res) {
            if ($res['uri'] === $uri && isset($res['path']) && is_file($res['path'])) {
                return file_get_contents($res['path']);
            }
        }
        return null;
    }

    private function generateDocsIndex(): string
    {
        $resources = $this->listResources();
        $index = [
            'tutorials' => [],
            'guides' => [],
            'concepts' => [],
            'feature-packs' => [],
            'api' => [],
            'other' => [],
        ];

        foreach ($resources as $res) {
            if ($res['uri'] === 'ish://docs/index') {
                continue;
            }

            $uri = $res['uri'];
            $name = $res['name'];

            if (str_contains($uri, 'guide/blog-part')) {
                $index['tutorials'][] = ['uri' => $uri, 'name' => $name];
            } elseif (str_contains($uri, 'guide/')) {
                $index['guides'][] = ['uri' => $uri, 'name' => $name];
            } elseif (str_contains($uri, 'concepts/')) {
                $index['concepts'][] = ['uri' => $uri, 'name' => $name];
            } elseif (str_starts_with($uri, 'docs:feature-packs/')) {
                $index['feature-packs'][] = ['uri' => $uri, 'name' => $name];
            } elseif (str_contains($uri, 'api/')) {
                $index['api'][] = ['uri' => $uri, 'name' => $name];
            } else {
                $index['other'][] = ['uri' => $uri, 'name' => $name];
            }
        }

        return json_encode($index, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }

        /** @param array<int, array<string,mixed>> $items */
        private function scanDocsRoot(string $root, array &$items): void
        {
            if (!is_dir($root)) {
                return;
            }

            $isModulesRoot = basename($root) === 'Modules';

            $directory = new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS);
            $iterator = new \RecursiveIteratorIterator($directory);

            foreach ($iterator as $file) {
                /** @var \SplFileInfo $file */
                if ($file->isDir()) {
                    continue;
                }

                $path = $file->getPathname();
                $relPath = str_replace($root . DIRECTORY_SEPARATOR, '', $path);
                
                // If scanning Modules root, we only care about files in {Module}/Docs/
                if ($isModulesRoot) {
                    if (!preg_match('~^([^' . preg_quote(DIRECTORY_SEPARATOR) . ']+)' . preg_quote(DIRECTORY_SEPARATOR) . 'Docs' . preg_quote(DIRECTORY_SEPARATOR) . '(.*)$~i', $relPath, $matches)) {
                        continue;
                    }
                    $moduleName = $matches[1];
                    $subPath = $matches[2];
                    $uriPath = 'feature-packs/' . $moduleName . '/' . str_replace(DIRECTORY_SEPARATOR, '/', $subPath);
                } else {
                    $uriPath = str_replace(DIRECTORY_SEPARATOR, '/', $relPath);
                }

                // Handle Markdown files
                if (preg_match('~\.md$~i', $relPath) === 1) {
                    $slug = strtolower(preg_replace('~\.md$~i', '', $uriPath));
                    
                    $items[] = [
                        'uri' => 'docs:' . $slug,
                        'name' => basename($relPath),
                        'description' => 'Documentation: ' . ($isModulesRoot ? "Module $moduleName - $subPath" : $relPath),
                        'mimeType' => 'text/markdown',
                        'path' => $path,
                    ];
                } 
                // Handle site/ index.html files if we still want to support them
                elseif (basename($path) === 'index.html') {
                    $slug = strtolower(dirname($uriPath));
                    if ($slug === '.') {
                         $slug = 'index';
                    }

                    $items[] = [
                        'uri' => 'docs:' . $slug,
                        'name' => 'Documentation section: ' . dirname($relPath),
                        'description' => 'Documentation section: ' . dirname($relPath),
                        'path' => $path,
                    ];
                }
            }
        }
    }
