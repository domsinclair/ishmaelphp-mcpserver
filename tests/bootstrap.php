<?php



declare(strict_types=1);



// Prefer this package's Composer autoloader if present

$autoloadCandidates = [

    __DIR__ . '/../vendor/autoload.php',          // tools/mcp/vendor

    __DIR__ . '/../../vendor/autoload.php',       // project-root/vendor when running from tools/mcp

];

foreach ($autoloadCandidates as $path) {

    if (is_file($path)) {

        require_once $path;

        break;

    }

}



// Ensure classes under IshmaelPHP\\McpServer namespace are loadable even if Composer isn't set up for this package

if (!class_exists('IshmaelPHP\\McpServer\\Server\\RequestRouter')) {

    spl_autoload_register(function (string $class): void {

        $prefix = 'IshmaelPHP\\McpServer\\';

        $baseDir = __DIR__ . '/../src/';

        if (strncmp($class, $prefix, strlen($prefix)) !== 0) {

            return;

        }

        $relative = substr($class, strlen($prefix));

        $file = $baseDir . str_replace('\\', DIRECTORY_SEPARATOR, $relative) . '.php';

        if (is_file($file)) {

            require $file;

        }

    });

}