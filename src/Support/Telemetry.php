<?php

    declare(strict_types=1);

    namespace Ishmael\McpServer\Support;

    use Ishmael\McpServer\Config\Settings;

    final class Telemetry
    {
        private Settings $settings;



        public function __construct(Settings $settings)
        {

            $this->settings = $settings;
        }



        /**

         * Log a structured telemetry event to STDERR when enabled.

         * @param array<string,mixed> $fields

         */

        public function emit(string $event, array $fields = []): void
        {

            if (!$this->settings->telemetryEnabled) {
                return;
            }

            $payload = [

                'ts' => date('c'),

                'event' => $event,

                'fields' => ErrorEnvelope::redact($fields),

            ];

            // Write JSON line to STDERR

            $json = json_encode($payload, JSON_UNESCAPED_SLASHES);

            if ($json !== false) {
                fwrite(\STDERR, "[ish-mcp/telemetry] " . $json . "\n");

                fflush(\STDERR);
            }
        }
    }
