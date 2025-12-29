<?php

    declare(strict_types=1);

    namespace Ishmael\McpServer\Config;

    /**

     * Runtime settings loaded from environment variables with safe defaults.

     */

    final class Settings
    {
        public bool $telemetryEnabled;

        public int $requestTimeoutMs;

        public int $globalRatePerMinute;

        /** @var array<string,int> per-method rates per minute */

        public array $methodRates;

        /** @var array<string,int> cache TTLs by tool name (seconds) */

        public array $cacheTtls;

        /** @var array<string,string[]> cache invalidation watch lists per tool name */

        public array $cacheWatchFiles;



        public function __construct()
        {

            $this->telemetryEnabled = self::envBool('MCP_TELEMETRY', false);

            $this->requestTimeoutMs = self::envInt('MCP_REQUEST_TIMEOUT_MS', 30000);

            $this->globalRatePerMinute = self::envInt('MCP_RATE_GLOBAL_PER_MIN', 120);

            $this->methodRates = self::parseMethodRates();

            $this->cacheTtls = self::parseCacheTtls();

            $this->cacheWatchFiles = self::parseCacheWatchFiles();
        }



        private static function envBool(string $key, bool $default): bool
        {

            $val = getenv($key);

            if ($val === false) {
                return $default;
            }

            $v = strtolower(trim((string)$val));

            return in_array($v, ['1','true','yes','on'], true);
        }



        private static function envInt(string $key, int $default): int
        {

            $val = getenv($key);

            if ($val === false || !is_numeric($val)) {
                return $default;
            }

            return max(0, (int)$val);
        }



        /**

         * Parse per-method rates from env variables of the form MCP_RATE_<UPPER_METHOD>_PER_MIN

         * e.g. MCP_RATE_ISH_LISTROUTES_PER_MIN=10

         */

        private static function parseMethodRates(): array
        {

            $rates = [];

            foreach ($_ENV as $k => $v) {
                if (!is_string($k)) {
                    continue;
                }

                if (preg_match('/^MCP_RATE_(.+)_PER_MIN$/', $k, $m) === 1) {
                    $method = strtolower(str_replace('_', ':', $m[1]));

                    $rates[$method] = is_numeric($v) ? (int)$v : 0;
                }
            }

            // Also support getenv() sources

            foreach (getenv() ?: [] as $k => $v) {
                if (!is_string($k)) {
                    continue;
                }

                if (preg_match('/^MCP_RATE_(.+)_PER_MIN$/', (string)$k, $m) === 1) {
                    $method = strtolower(str_replace('_', ':', $m[1]));

                    if (!isset($rates[$method])) {
                        $rates[$method] = is_numeric($v) ? (int)$v : 0;
                    }
                }
            }

            return $rates;
        }



        private static function parseCacheTtls(): array
        {

            $out = [];

            $raw = getenv('MCP_CACHE_TTLS'); // "tool=ttl,tool2=ttl"

            if (is_string($raw) && $raw !== '') {
                $pairs = explode(',', $raw);

                foreach ($pairs as $p) {
                    [$k, $v] = array_map('trim', array_pad(explode('=', $p, 2), 2, ''));

                    if ($k !== '' && is_numeric($v)) {
                        $out[$k] = (int)$v;
                    }
                }
            }

            // sensible defaults for known expensive tools

            $out += [

                'ish:listRoutes' => $out['ish:listRoutes'] ?? 10,

                'ish:container:map' => $out['ish:container:map'] ?? 10,

            ];

            return $out;
        }



        private static function parseCacheWatchFiles(): array
        {

            $out = [];

            $raw = getenv('MCP_CACHE_WATCH'); // "tool=path1;path2,tool2=pathA"

            if (is_string($raw) && $raw !== '') {
                $pairs = explode(',', $raw);

                foreach ($pairs as $p) {
                    [$k, $v] = array_map('trim', array_pad(explode('=', $p, 2), 2, ''));

                    if ($k !== '' && $v !== '') {
                        $out[$k] = array_values(array_filter(array_map('trim', explode(';', $v)), fn($x) => $x !== ''));
                    }
                }
            }

            // defaults for common Ishmael projects

            $out += [

                'ish:listRoutes' => $out['ish:listRoutes'] ?? ['config/routes.php', 'Modules/*/Routes/*.php'],

                'ish:container:map' => $out['ish:container:map'] ?? ['config/*.php', 'composer.json'],

            ];

            return $out;
        }
    }
