<?php

    declare(strict_types=1);

    namespace IshmaelPHP\McpServer\Project;

    final class ProjectContext
    {
        private ?string $root;

        private ?PathSandbox $sandbox;

        /** @var array<string, string|null> */

        private array $binaries;



        /**

         * Attempt to build a project context using discovery. If discovery fails, the context is still constructed

         * with null root and empty binaries, allowing the server to run in a degraded mode.

         */

        public static function discover(?string $startDir = null): self
        {

            $locator = new ProjectRootLocator();

            $root = $locator->locate($startDir);



            if ($root === null) {
                return new self(null, null, []);
            }



            $sandbox = new PathSandbox($root);

            $binResolver = new VendorBinaryResolver($root);

            $bins = $binResolver->resolveAll();



            return new self($root, $sandbox, $bins);
        }



        /** @param array<string,string|null> $binaries */

        public function __construct(?string $root, ?PathSandbox $sandbox, array $binaries)
        {

            $this->root = $root;

            $this->sandbox = $sandbox;

            $this->binaries = $binaries;
        }



        public function getRoot(): ?string
        {

            return $this->root;
        }



        public function getSandbox(): ?PathSandbox
        {

            return $this->sandbox;
        }



        /** @return array<string, string|null> */

        public function getBinaries(): array
        {

            return $this->binaries;
        }
    }
