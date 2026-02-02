<?php

declare(strict_types=1);

namespace Ishmael\McpServer\Support;

use Ishmael\McpServer\Project\ProjectContext;
use PDO;
use Exception;

/**
 * Resolves the database connection for the current project.
 */
class DatabaseConnectionFactory
{
    private ProjectContext $context;

    public function __construct(ProjectContext $context)
    {
        $this->context = $context;
    }

    /**
     * @return PDO
     * @throws Exception
     */
    public function getConnection(): PDO
    {
        $root = $this->context->getRoot();
        if ($root === null) {
            throw new Exception("Cannot connect to database: Project root not found.");
        }

        $env = $this->loadEnv($root);

        $driver = $env['DB_CONNECTION'] ?? 'sqlite';
        
        switch ($driver) {
            case 'sqlite':
                $database = $env['DB_DATABASE'] ?? ($root . DIRECTORY_SEPARATOR . 'ishmael.sqlite');
                // If relative path, make it absolute from root
                if (!str_starts_with($database, DIRECTORY_SEPARATOR) && !preg_match('/^[a-zA-Z]:\\\\/', $database)) {
                    $database = $root . DIRECTORY_SEPARATOR . $database;
                }
                
                if (!file_exists($database)) {
                    // Fallback to project root ishmael.sqlite if specified one doesn't exist but root one might
                    $rootDb = $root . DIRECTORY_SEPARATOR . 'ishmael.sqlite';
                    if (file_exists($rootDb)) {
                        $database = $rootDb;
                    }
                }

                $pdo = new PDO('sqlite:' . $database);
                $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                $pdo->exec('PRAGMA foreign_keys = ON');
                return $pdo;

            case 'mysql':
            case 'pgsql':
                $host = $env['DB_HOST'] ?? '127.0.0.1';
                $port = $env['DB_PORT'] ?? ($driver === 'mysql' ? '3306' : '5432');
                $database = $env['DB_DATABASE'] ?? 'ishmael';
                $username = $env['DB_USERNAME'] ?? 'root';
                $password = $env['DB_PASSWORD'] ?? '';

                $dsn = "{$driver}:host={$host};port={$port};dbname={$database}";
                $pdo = new PDO($dsn, $username, $password);
                $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                return $pdo;

            default:
                throw new Exception("Unsupported database driver: {$driver}");
        }
    }

    /** @return array<string,string> */
    private function loadEnv(string $root): array
    {
        $envPath = $root . DIRECTORY_SEPARATOR . '.env';
        if (!is_file($envPath)) {
            return [];
        }

        $contents = file_get_contents($envPath) ?: '';
        $out = [];
        foreach (preg_split('/\r?\n/', $contents) as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }
            $parts = explode('=', $line, 2);
            if (count($parts) === 2) {
                $key = trim($parts[0]);
                $val = trim($parts[1]);
                $val = trim($val, "\"' ");
                $out[$key] = $val;
            }
        }
        return $out;
    }
}
