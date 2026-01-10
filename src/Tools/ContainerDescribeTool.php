<?php

declare(strict_types=1);

namespace Ishmael\McpServer\Tools;

use Ishmael\McpServer\Contracts\Tool;
use Ishmael\McpServer\Project\ProjectContext;

/**

 * ish:container:describe — Enumerate container services and aliases.

 * Incubation: returns an empty listing; future versions may introspect Ishmael container.

 */

final class ContainerDescribeTool implements Tool
{
    private ProjectContext $context;

    public function __construct(ProjectContext $context)
    {
        $this->context = $context;
    }



    public function getName(): string
    {

        return 'ish:container:describe';
    }



    public function getDescription(): string
    {
        return 'Describe DI container services and aliases (introspects the app() registry).';
    }



    public function getInputSchema(): array
    {

        return [

            'type' => 'object',

            'additionalProperties' => false,

            'properties' => [

                'id' => ['type' => ['string','null']],

                'tag' => ['type' => ['string','null']],

            ],

        ];
    }



    public function getOutputSchema(): array
    {

        return [

            'type' => 'object',

            'required' => ['services','aliases'],

            'properties' => [

                'services' => [

                    'type' => 'array',

                    'items' => [

                        'type' => 'object',

                        'required' => ['id'],

                        'properties' => [

                            'id' => ['type' => 'string'],

                            'class' => ['type' => ['string','null']],

                            'singleton' => ['type' => ['boolean','null']],

                            'tags' => ['type' => 'array', 'items' => ['type' => 'string']],

                            'aliases' => ['type' => 'array', 'items' => ['type' => 'string']],

                        ],

                        'additionalProperties' => false,

                    ],

                ],

                'aliases' => [

                    'type' => 'array',

                    'items' => [

                        'type' => 'object',

                        'required' => ['alias','target'],

                        'properties' => [

                            'alias' => ['type' => 'string'],

                            'target' => ['type' => 'string'],

                        ],

                        'additionalProperties' => false,

                    ],

                ],

            ],

        ];
    }



    private static ?array $cache = null;

    public function execute(array $input): array
    {
        $idFilter = $input['id'] ?? null;

        if (self::$cache !== null && $idFilter === null) {
            return self::$cache;
        }

        $root = $this->context->getRoot();
        if ($root === null) {
            return ['services' => [], 'aliases' => []];
        }

        // ... existing bootstrap logic ...
        $bootstrap = $root . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'ishmael' . DIRECTORY_SEPARATOR . 'framework' . DIRECTORY_SEPARATOR . 'bootstrap' . DIRECTORY_SEPARATOR . 'app.php';
        
        if (!file_exists($bootstrap)) {
            $bootstrap = $root . DIRECTORY_SEPARATOR . 'bootstrap' . DIRECTORY_SEPARATOR . 'app.php';
        }

        if (!file_exists($bootstrap)) {
             $bootstrap = $root . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php';
        }

        $code = <<<'PHP'
<?php
error_reporting(0);
ini_set('display_errors', '0');
ob_start();

$root = '%s';
$autoload = $root . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php';
if (file_exists($autoload)) {
    require_once $autoload;
}

if (!defined('ISH_APP_BASE')) {
    define('ISH_APP_BASE', $root);
}
define('ISH_BOOTSTRAP_ONLY', true);

if (file_exists('%s')) {
    require_once '%s';
}

$services = [];
if (function_exists('app')) {
    try {
        $rawServices = app();
        if (is_array($rawServices)) {
            foreach ($rawServices as $id => $instance) {
                $class = is_object($instance) ? get_class($instance) : (is_string($instance) ? $instance : null);
                $services[] = [
                    'id' => (string)$id,
                    'class' => $class,
                    'singleton' => true,
                    'tags' => [],
                    'aliases' => [],
                ];
            }
        }
    } catch (\Throwable $e) {
    }
}

ob_end_clean();
echo json_encode(['services' => $services, 'aliases' => []]);
PHP;

        $php = PHP_BINARY ?: 'php';
        $tempFile = tempnam(sys_get_temp_dir(), 'ish_container_');
        file_put_contents($tempFile, sprintf($code, addslashes($root), addslashes($bootstrap), addslashes($bootstrap)));

        $descriptorspec = [
            1 => ["pipe", "w"],
            2 => ["pipe", "w"]
        ];

        $process = proc_open(escapeshellarg($php) . " " . escapeshellarg($tempFile), $descriptorspec, $pipes, $root, [
            'ISH_APP_BASE' => $root,
            'ISH_BOOTSTRAP_ONLY' => '1'
        ]);

        if (!is_resource($process)) {
            @unlink($tempFile);
            return ['services' => [], 'aliases' => []];
        }

        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $exitCode = proc_close($process);
        @unlink($tempFile);

        if ($exitCode !== 0) {
            return ['services' => [], 'aliases' => [], 'error' => trim($stderr)];
        }

        $data = json_decode((string)$stdout, true);
        if (!is_array($data)) {
            return ['services' => [], 'aliases' => []];
        }

        if ($idFilter === null) {
            self::$cache = $data;
        } else {
            $data['services'] = array_values(array_filter($data['services'], fn($s) => $s['id'] === $idFilter));
        }

        return $data;
    }
}
