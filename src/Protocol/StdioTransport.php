<?php

    declare(strict_types=1);

    namespace Ishmael\McpServer\Protocol;

    use Ishmael\McpServer\Support\ErrorEnvelope;

    /**
     * Minimal JSON Lines transport over stdio.
     * - Reads one JSON object per line from STDIN.
     * - Writes one JSON object per line to STDOUT.
     * - Logs errors/messages to STDERR.
     */
    final class StdioTransport
    {
        /** @var resource */
        private $in;

        /** @var resource */
        private $out;

        /** @var resource */
        private $err;

        /**
         * @param resource|null $in
         * @param resource|null $out
         * @param resource|null $err
         */
        public function __construct($in = null, $out = null, $err = null)
        {
            $this->in = $in ?? \STDIN;
            $this->out = $out ?? \STDOUT;
            $this->err = $err ?? \STDERR;
        }

        /**
         * Read next JSON message as associative array, or null on EOF.
         * If a parse error occurs, returns a standardized error envelope with id=null.
         */
        public function read(): ?array
        {
            $line = fgets($this->in);

            if ($line === false) {
                return null; // EOF
            }

            $line = trim($line);

            if ($line === '') {
                return [];
            }

            $data = json_decode($line, true);

            if (!is_array($data)) {
                $this->logError('Invalid JSON input: ' . $line);

                // Return standardized parse error so server loop can just write it back
                return ErrorEnvelope::error(null, -32700, 'Parse error');
            }

            return $data;
        }

        /** Write a JSON message followed by a newline. */
        public function write(array $message): void
        {
            $encoded = json_encode($message, JSON_UNESCAPED_SLASHES);

            if ($encoded === false) {
                $this->logError('Failed to encode JSON');

                return;
            }

            fwrite($this->out, $encoded . "\n");
            fflush($this->out);
        }

        public function logError(string $message): void
        {
            $prefix = sprintf('[ish-mcp] [%s] ', date('Y-m-d H:i:s'));
            fwrite($this->err, $prefix . $message . "\n");
            fflush($this->err);
        }
    }
