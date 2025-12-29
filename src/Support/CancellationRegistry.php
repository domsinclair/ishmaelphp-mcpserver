<?php

    declare(strict_types=1);

    namespace Ishmael\McpServer\Support;

    /**

     * Tracks in-flight requests and allows best-effort cancellation flags.

     * Tools may consult this registry to abort long-running operations early.

     */

    final class CancellationRegistry
    {
        /** @var array<string|int,bool> */

        private array $inFlight = [];

        /** @var array<string|int,bool> */

        private array $cancelled = [];



        /** @param string|int|null $id */

        public function register($id): void
        {

            if ($id === null) {
                return;
            }

            $this->inFlight[$id] = true;

            unset($this->cancelled[$id]);
        }



        /** @param string|int|null $id */

        public function complete($id): void
        {

            if ($id === null) {
                return;
            }

            unset($this->inFlight[$id], $this->cancelled[$id]);
        }



        /** @param string|int|null $id */

        public function cancel($id): bool
        {

            if ($id === null) {
                return false;
            }

            if (!isset($this->inFlight[$id])) {
                // allow marking even if not currently tracked

                $this->cancelled[$id] = true;

                return false;
            }

            $this->cancelled[$id] = true;

            return true;
        }



        /** @param string|int|null $id */

        public function isCancelled($id): bool
        {

            if ($id === null) {
                return false;
            }

            return isset($this->cancelled[$id]) && $this->cancelled[$id] === true;
        }
    }
