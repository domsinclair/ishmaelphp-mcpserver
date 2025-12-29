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

            $items = [];

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
                $byId[$it['id']] = $it;
            }

            return array_values($byId);
        }



        /** @param array<int, array<string,mixed>> $items */

        private function scanDocsRoot(string $root, array &$items): void
        {

            // Shallow scan: list top-level markdown in Docs, and top-level index.html in site subsections

            $iterator = @scandir($root) ?: [];

            foreach ($iterator as $name) {
                if ($name === '.' || $name === '..') {
                    continue;
                }

                $path = $root . DIRECTORY_SEPARATOR . $name;

                if (is_dir($path)) {
                    // For site/, expose section index.html if present

                    $index = $path . DIRECTORY_SEPARATOR . 'index.html';

                    if (is_file($index)) {
                        $section = strtolower($name);

                        $items[] = [

                            'id' => 'docs:' . $section,

                            'description' => 'Documentation section: ' . $name,

                            'path' => $index,

                        ];
                    }

                    continue;
                }

                // Markdown files under Docs root

                if (preg_match('~\.md$~i', $name) === 1) {
                    $slug = strtolower(preg_replace('~\.[^.]+$~', '', $name) ?? $name);

                    $items[] = [

                        'id' => 'docs:' . $slug,

                        'description' => 'Doc: ' . $name,

                        'path' => $path,

                    ];
                }
            }
        }
    }
