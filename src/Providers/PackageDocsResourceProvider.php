<?php

    declare(strict_types=1);

    namespace IshmaelPHP\McpServer\Providers;

    use IshmaelPHP\McpServer\Contracts\ResourceProvider;

    /**

     * Lists docs:* resources that are bundled within this MCP package itself.

     *

     * This enables Composer installs (where the main repo's Docs/ may be absent)

     * to still expose comprehensive documentation to clients.

     *

     * Looks under tools/mcp/resources/{Docs,site} (resolved relatively from this file).

     * If those folders are missing, returns an empty list (safe no-op).

     */

    final class PackageDocsResourceProvider implements ResourceProvider
    {
        /** @var string[] */

        private array $roots;



        public function __construct(?string $base = null)
        {

            // Resolve package-internal resources directory

            $baseDir = $base ?? dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'resources';

            $this->roots = [

                $baseDir . DIRECTORY_SEPARATOR . 'Docs',

                $baseDir . DIRECTORY_SEPARATOR . 'site',

            ];
        }



        public function listResources(): array
        {

            $items = [];

            foreach ($this->roots as $root) {
                if (!is_dir($root)) {
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

            $entries = @scandir($root) ?: [];

            foreach ($entries as $name) {
                if ($name === '.' || $name === '..') {
                    continue;
                }

                $path = $root . DIRECTORY_SEPARATOR . $name;

                if (is_dir($path)) {
                    // Expose section index.html for built site sections

                    $index = $path . DIRECTORY_SEPARATOR . 'index.html';

                    if (is_file($index)) {
                        $section = strtolower($name);

                        $items[] = [

                            'id' => 'docs:' . $section,

                            'description' => 'Documentation section (bundled): ' . $name,

                            'path' => $index,

                        ];
                    }

                    continue;
                }

                if (preg_match('~\.md$~i', $name) === 1) {
                    $slug = strtolower(preg_replace('~\.[^.]+$~', '', $name) ?? $name);

                    $items[] = [

                        'id' => 'docs:' . $slug,

                        'description' => 'Doc (bundled): ' . $name,

                        'path' => $path,

                    ];
                }
            }
        }
    }
