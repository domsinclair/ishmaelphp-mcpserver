<?php

    declare(strict_types=1);

    namespace Ishmael\McpServer\Support;

    use Ishmael\McpServer\Config\Settings;

    /**

     * Simple token bucket rate limiter for global and per-method limits (per minute windows).

     */

    final class RateLimiter
    {
        private Settings $settings;

        /** @var array<string,array{windowStart:int,count:int}> */

        private array $counters = [];



        public function __construct(Settings $settings)
        {

            $this->settings = $settings;
        }



        /**

         * Check whether a call is allowed for the given method. Returns null if allowed, or an error array.

         * @return array<string,mixed>|null

         */

        public function check(string $method): ?array
        {

            $now = (int)floor(microtime(true)); // seconds

            $minuteWindow = intdiv($now, 60);



            // Helper to bump a key and test against limit

            $test = function (string $key, int $limit) use ($minuteWindow): bool {

                if (!isset($this->counters[$key]) || $this->counters[$key]['windowStart'] !== $minuteWindow) {
                    $this->counters[$key] = ['windowStart' => $minuteWindow, 'count' => 0];
                }

                $this->counters[$key]['count']++;

                return $this->counters[$key]['count'] <= $limit;
            };



            // Global limit

            $global = $this->settings->globalRatePerMinute;

            if ($global > 0 && !$test('global', $global)) {
                return [

                    'code' => 42900,

                    'message' => 'Rate limit exceeded (global)',

                    'details' => [ 'retryAfterSec' => 60 - ($now % 60) ],

                ];
            }



            // Per-method limit

            $rate = $this->settings->methodRates[$method] ?? 0;

            if ($rate > 0 && !$test('method:' . $method, $rate)) {
                return [

                    'code' => 42901,

                    'message' => 'Rate limit exceeded for method: ' . $method,

                    'details' => [ 'retryAfterSec' => 60 - ($now % 60) ],

                ];
            }



            return null;
        }
    }
