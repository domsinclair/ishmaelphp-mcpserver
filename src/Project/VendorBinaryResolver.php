<?php

    declare(strict_types=1);

    namespace IshmaelPHP\McpServer\Project;

    final class VendorBinaryResolver
    {
        private string $root;

        private string $vendorBin;



        public function __construct(string $projectRoot)
        {

            $this->root = rtrim($projectRoot, DIRECTORY_SEPARATOR);

            $this->vendorBin = $this->root . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'bin';
        }



        /** Resolve a vendor binary by canonical name (e.g., "phpunit", "phpstan", "phpcs"). */

        public function resolve(string $name): ?string
        {

            $candidates = $this->candidatePaths($name);

            foreach ($candidates as $path) {
                if (is_file($path)) {
                    return $path;
                }
            }

            return null;
        }



        /** Return a map of known binaries to resolved paths (or null when missing). */

        public function resolveAll(): array
        {

            $names = ['phpunit', 'phpstan', 'phpcs'];

            $out = [];

            foreach ($names as $n) {
                $out[$n] = $this->resolve($n);
            }

            return $out;
        }



        /** @return array<int,string> */

        private function candidatePaths(string $name): array
        {

            $paths = [];

            $base = $this->vendorBin . DIRECTORY_SEPARATOR . $name;

            $paths[] = $base;

            // Windows .bat shims created by Composer

            $paths[] = $base . '.bat';

            // Some tools may use .phar in vendor/bin

            $paths[] = $base . '.phar';

            return $paths;
        }
    }
