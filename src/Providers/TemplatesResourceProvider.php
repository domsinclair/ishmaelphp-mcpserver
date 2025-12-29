<?php

    declare(strict_types=1);

    namespace Ishmael\McpServer\Providers;

    use Ishmael\McpServer\Contracts\ResourceProvider;
    use Ishmael\McpServer\Project\PathSandbox;

    /**

     * Exposes scaffolding templates as read-only resource identifiers (templates:*).

     * Looks under a configured Templates/ root and lists top-level template packs.

     */

    final class TemplatesResourceProvider implements ResourceProvider
    {
        private PathSandbox $sandbox;

        private string $templatesRoot;



        public function __construct(PathSandbox $sandbox, string $templatesRoot)
        {

            $this->sandbox = $sandbox;

            $this->templatesRoot = $templatesRoot;
        }



        public function listResources(): array
        {

            $items = [];

            if (!$this->sandbox->isWithinRoot($this->templatesRoot) || !is_dir($this->templatesRoot)) {
                return $items;
            }

            $entries = @scandir($this->templatesRoot) ?: [];

            foreach ($entries as $name) {
                if ($name === '.' || $name === '..') {
                    continue;
                }

                $path = $this->templatesRoot . DIRECTORY_SEPARATOR . $name;

                if (is_dir($path)) {
                    $items[] = [

                        'id' => 'templates:' . strtolower($name),

                        'description' => 'Template pack: ' . $name,

                        'path' => $path,

                    ];
                }
            }

            return $items;
        }
    }
