<?php

declare(strict_types=1);

namespace IshmaelPHP\McpServer\Tools;

use IshmaelPHP\McpServer\Contracts\Tool;
use IshmaelPHP\McpServer\Project\ProjectContext;

/**

 * ish:log:tail — Stream logs with filters (level/channel/since); chunked responses.

 * Incubation note: returns a single chunk of the most recent lines from storage/logs.

 */

final class LogTailTool implements Tool
{
    private ProjectContext $context;



    public function __construct(ProjectContext $context)
    {

        $this->context = $context;
    }



    public function getName(): string
    {

        return 'ish:log:tail';
    }



    public function getDescription(): string
    {

        return 'Tail application logs with basic filters (incubation: single chunk).';
    }



    public function getInputSchema(): array
    {

        return [

            'type' => 'object',

            'additionalProperties' => false,

            'properties' => [

                'level' => ['type' => ['string','null']],

                'channel' => ['type' => ['string','null']],

                'since' => ['type' => ['string','null']],

                'maxItems' => ['type' => 'integer'],

            ],

        ];
    }



    public function getOutputSchema(): array
    {

        return [

            'type' => 'object',

            'required' => ['events','next'],

            'properties' => [

                'events' => [

                    'type' => 'array',

                    'items' => [

                        'type' => 'object',

                        'required' => ['timestamp','level','channel','message','raw'],

                        'properties' => [

                            'timestamp' => ['type' => ['string','null']],

                            'level' => ['type' => ['string','null']],

                            'channel' => ['type' => ['string','null']],

                            'message' => ['type' => 'string'],

                            'context' => ['type' => ['object','null']],

                            'raw' => ['type' => 'string'],

                        ],

                        'additionalProperties' => false,

                    ],

                ],

                'next' => ['type' => ['string','null']],

            ],

        ];
    }



    public function execute(array $input): array
    {

        $max = isset($input['maxItems']) && is_int($input['maxItems']) ? max(1, min(500, $input['maxItems'])) : 100;

        $root = $this->context->getRoot();

        $events = [];

        if ($root !== null) {
            $logDir = $root . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'logs';

            if (is_dir($logDir)) {
                // prefer ish.log, then app.log, then any .log

                $candidates = [

                    $logDir . DIRECTORY_SEPARATOR . 'ish.log',

                    $logDir . DIRECTORY_SEPARATOR . 'app.log',

                ];

                foreach (glob($logDir . DIRECTORY_SEPARATOR . '*.log') ?: [] as $g) {
                    if (!in_array($g, $candidates, true)) {
                        $candidates[] = $g;
                    }
                }

                $logFile = null;

                foreach ($candidates as $f) {
                    if (is_file($f)) {
                        $logFile = $f;

                        break;
                    }
                }

                if ($logFile !== null) {
                    $lines = $this->tailFile($logFile, $max);

                    foreach ($lines as $line) {
                        $events[] = $this->parseLogLine($line);
                    }
                }
            }
        }

        return [ 'events' => $events, 'next' => null ];
    }



    /** @return array<int,string> */

    private function tailFile(string $file, int $maxLines): array
    {

        $buf = @file($file, FILE_IGNORE_NEW_LINES) ?: [];

        if (count($buf) > $maxLines) {
            $buf = array_slice($buf, -$maxLines);
        }

        return $buf;
    }



    private function parseLogLine(string $line): array
    {

        // Very tolerant parser for Monolog line like: [2025-01-01T12:00:00] channel.LEVEL: message {context}

        $timestamp = null;

        $channel = null;

        $level = null;

        $message = trim($line);

        if (preg_match('/^\[(.*?)\]\s+([^.]+)\.([A-Z]+):\s+(.*)$/', $line, $m) === 1) {
            $timestamp = $m[1];

            $channel = $m[2];

            $level = $m[3];

            $message = $m[4];
        }

        return [

            'timestamp' => $timestamp,

            'level' => $level,

            'channel' => $channel,

            'message' => $message,

            'context' => null,

            'raw' => $line,

        ];
    }
}
