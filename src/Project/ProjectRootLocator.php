<?php

    declare(strict_types=1);

    namespace IshmaelPHP\McpServer\Project;

    final class ProjectRootLocator
    {
        /** Locate the Ishmael project root starting from the current working directory and walking upwards. */

        public function locate(?string $startDir = null): ?string
        {

            $dir = $startDir !== null ? $this->normalizePath($startDir) : $this->normalizePath(getcwd() ?: '.');



            // Stop at drive root

            while ($dir !== '' && $this->hasParent($dir)) {
                if ($this->looksLikeIshmaelProject($dir)) {
                    return $dir;
                }

                $parent = dirname($dir);

                if ($parent === $dir) {
                    break;
                }

                $dir = $parent;
            }



            // Final check on the last dir (root itself)

            return $this->looksLikeIshmaelProject($dir) ? $dir : null;
        }



        private function looksLikeIshmaelProject(string $dir): bool
        {

            // Composer detection

            $composer = $dir . DIRECTORY_SEPARATOR . 'composer.json';

            if (is_file($composer)) {
                $data = json_decode((string)@file_get_contents($composer), true);

                if (is_array($data)) {
                    $require = (array)($data['require'] ?? []);

                    $requireDev = (array)($data['require-dev'] ?? []);

                    $all = array_merge(array_keys($require), array_keys($requireDev));

                    foreach ($all as $pkg) {
                        if (is_string($pkg) && str_starts_with(strtolower($pkg), 'ishmaelphp/')) {
                            return true;
                        }
                    }
                }
            }



            // Bootstrap markers (repo structure during development)

            $marker = $dir . DIRECTORY_SEPARATOR . 'IshmaelPHP-Core' . DIRECTORY_SEPARATOR . 'bootstrap';

            if (is_dir($marker)) {
                return true;
            }



            return false;
        }



        private function hasParent(string $dir): bool
        {

            $parent = dirname($dir);

            return $parent !== $dir;
        }



        private function normalizePath(string $path): string
        {

            $path = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path);

            // Remove trailing separators except when path is root (like C:\)

            if (preg_match('~^[A-Za-z]:\\\\$~', $path) === 1) {
                return strtoupper($path);
            }

            return rtrim($path, DIRECTORY_SEPARATOR);
        }
    }
