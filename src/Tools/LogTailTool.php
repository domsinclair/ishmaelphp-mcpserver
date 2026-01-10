<?php

declare(strict_types=1);

namespace Ishmael\McpServer\Tools;

use Ishmael\McpServer\Contracts\Tool;
use Ishmael\McpServer\Project\ProjectContext;
use Ishmael\McpServer\Support\StackTraceMapper;

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

                            'stack_trace' => [
                                'type' => 'array',
                                'items' => [
                                    'type' => 'object',
                                    'properties' => [
                                        'file' => ['type' => 'string'],
                                        'line' => ['type' => 'integer'],
                                        'function' => ['type' => ['string', 'null']],
                                    ]
                                ]
                            ],

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
                    $mapper = new StackTraceMapper($root);

                    foreach ($lines as $line) {
                        $events[] = $this->parseLogLine($line, $mapper);
                    }
                }
            }
        }

        return [ 'events' => $events, 'next' => null ];
    }



    /** @return array<int,string> */

    private function tailFile(string $file, int $maxLines): array
    {
        $content = @file_get_contents($file);
        if ($content === false) {
            return [];
        }

        // Split by Monolog-style line starts: [YYYY-MM-DD...
        $entries = preg_split('/(?=\[\d{4}-\d{2}-\d{2}T)/', $content, -1, PREG_SPLIT_NO_EMPTY);
        
        if (count($entries) > $maxLines) {
            $entries = array_slice($entries, -$maxLines);
        }

        return array_map('trim', $entries);
    }



    private function parseLogLine(string $line, StackTraceMapper $mapper): array
    {
        // Monolog line like: [2025-01-01T12:00:00] channel.LEVEL: message {context}
        // Might be multi-line
        $timestamp = null;
        $channel = null;
        $level = null;
        $message = trim($line);

        if (preg_match('/^\[(?P<timestamp>.*?)\]\s+(?P<channel>[^.]+)\.(?P<level>[A-Z]+):\s+(?P<message>.*)$/s', $line, $m) === 1) {
            $timestamp = $m['timestamp'];
            $channel = $m['channel'];
            $level = $m['level'];
            $message = trim($m['message']);
        }

        $mapped = $mapper->map($message);

        return [
            'timestamp' => $timestamp,
            'level' => $level,
            'channel' => $channel,
            'message' => $mapped['message'],
            'context' => null,
            'stack_trace' => $mapped['stack_trace'],
            'raw' => $line,
        ];
    }
}
