<?php

    declare(strict_types=1);

    namespace IshmaelPHP\McpServer\Project;

    final class PathSandbox
    {
        private string $root;



        public function __construct(string $root)
        {

            $this->root = $this->normalize($root);
        }



        public function getRoot(): string
        {

            return $this->root;
        }



        /** Return true if the given path is within the sandbox root (after canonicalization). */

        public function isWithinRoot(string $path): bool
        {

            $canon = $this->canonicalize($path);

            return $canon === $this->root || str_starts_with($canon . DIRECTORY_SEPARATOR, $this->root . DIRECTORY_SEPARATOR);
        }



        /** Assert the path is within root; throws RuntimeException otherwise. */

        public function assertWithinRoot(string $path, string $what = 'path'): void
        {

            if (!$this->isWithinRoot($path)) {
                throw new \RuntimeException(sprintf('Refusing to access %s outside project root: %s', $what, $path));
            }
        }



        /** Resolve a relative path inside the root safely (no traversal outside). */

        public function resolveInside(string $relative): string
        {

            $joined = $this->root . DIRECTORY_SEPARATOR . $relative;

            $canon = $this->canonicalize($joined);

            $this->assertWithinRoot($canon);

            return $canon;
        }



        /** Canonicalize without requiring the path to exist (like realpath but tolerant). */

        private function canonicalize(string $path): string
        {

            $path = $this->normalize($path);

            $parts = explode(DIRECTORY_SEPARATOR, $path);

            $stack = [];



            // Preserve Windows drive prefix if present

            $drive = '';

            if (preg_match('~^[A-Za-z]:$~', $parts[0]) === 1) {
                $drive = array_shift($parts);
            }



            foreach ($parts as $part) {
                if ($part === '' || $part === '.') {
                    continue;
                }

                if ($part === '..') {
                    if (!empty($stack)) {
                        array_pop($stack);
                    }

                    continue;
                }

                $stack[] = $part;
            }



            $prefix = $drive !== '' ? $drive . DIRECTORY_SEPARATOR : '';

            return $prefix . implode(DIRECTORY_SEPARATOR, $stack);
        }



        private function normalize(string $path): string
        {

            $path = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path);

            // Uppercase Windows drive letter

            if (preg_match('~^([A-Za-z]):~', $path, $m) === 1) {
                $path = strtoupper($m[1]) . substr($path, 1);
            }

            return rtrim($path, DIRECTORY_SEPARATOR);
        }
    }
