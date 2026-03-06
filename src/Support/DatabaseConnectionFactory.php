<?php

declare(strict_types=1);

namespace Ishmael\McpServer\Support;

use Ishmael\McpServer\Project\ProjectContext;
use PDO;
use Exception;

/**
 * Represents a database connection error state instead of throwing exceptions.
 */
class DatabaseConnectionError
{
    public string $error;
    public string $message;
    public ?string $hint;
    public array $searchedPaths;

    public function __construct(string $error, string $message, ?string $hint = null, array $searchedPaths = [])
    {
        $this->error = $error;
        $this->message = $message;
        $this->hint = $hint;
        $this->searchedPaths = $searchedPaths;
    }

    public function toArray(): array
    {
        $result = [
            'error' => $this->error,
            'message' => $this->message,
        ];
        if ($this->hint !== null) {
            $result['hint'] = $this->hint;
        }
        if (!empty($this->searchedPaths)) {
            $result['searched_paths'] = $this->searchedPaths;
        }
        return $result;
    }
}

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
     * Returns a PDO connection or a DatabaseConnectionError object if the connection cannot be established.
     * 
     * @return PDO|DatabaseConnectionError
     */
    public function getConnection(): PDO|DatabaseConnectionError
    {
        $root = $this->context->getRoot();
        if ($root === null) {
            return new DatabaseConnectionError(
                'Project Root Not Found',
                'Cannot connect to database: Project root not detected.',
                'Ensure you are running the MCP server within an Ishmael project or set ISH_PROJECT_ROOT environment variable.'
            );
        }

        $env = $this->loadEnv($root);

        $driver = $env['DB_CONNECTION'] ?? 'sqlite';
        
        switch ($driver) {
            case 'sqlite':
                return $this->connectSqlite($root, $env);

            case 'mysql':
            case 'pgsql':
                return $this->connectMysqlOrPgsql($driver, $env);

            default:
                return new DatabaseConnectionError(
                    'Unsupported Driver',
                    "Unsupported database driver: {$driver}",
                    'Supported drivers are: sqlite, mysql, pgsql'
                );
        }
    }

    /**
     * Connect to SQLite database with Windows path normalization and expanded search logic.
     */
    private function connectSqlite(string $root, array $env): PDO|DatabaseConnectionError
    {
        // Check if pdo_sqlite extension is loaded before attempting connection
        if (!extension_loaded('pdo_sqlite')) {
            $availableDrivers = class_exists('PDO') ? implode(', ', \PDO::getAvailableDrivers()) : 'PDO not loaded';
            return new DatabaseConnectionError(
                'SQLite Driver Not Available',
                'The pdo_sqlite PHP extension is not loaded.',
                "Available PDO drivers: {$availableDrivers}. Enable pdo_sqlite in your php.ini or ensure you're using the correct PHP binary."
            );
        }

        $searchedPaths = [];
        $database = null;

        // Normalize root path for Windows
        $root = $this->normalizePath($root);

        // 1. First check if DB_DATABASE is explicitly set in .env
        if (!empty($env['DB_DATABASE'])) {
            $configuredPath = $env['DB_DATABASE'];
            
            // If relative path, make it absolute from root
            if (!$this->isAbsolutePath($configuredPath)) {
                $configuredPath = $root . DIRECTORY_SEPARATOR . $configuredPath;
            }
            
            $configuredPath = $this->normalizePath($configuredPath);
            $searchedPaths[] = $configuredPath;
            
            if ($this->fileExistsNormalized($configuredPath)) {
                $database = $configuredPath;
            }
        }

        // 2. Check root/ishmael.sqlite (common location)
        if ($database === null) {
            $rootDb = $this->normalizePath($root . DIRECTORY_SEPARATOR . 'ishmael.sqlite');
            $searchedPaths[] = $rootDb;
            
            if ($this->fileExistsNormalized($rootDb)) {
                $database = $rootDb;
            }
        }

        // 3. Check storage/ishmael.sqlite (standard framework path)
        if ($database === null) {
            $storageDb = $this->normalizePath($root . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'ishmael.sqlite');
            $searchedPaths[] = $storageDb;
            
            if ($this->fileExistsNormalized($storageDb)) {
                $database = $storageDb;
            }
        }

        // 4. Check storage/database.sqlite (alternative location)
        if ($database === null) {
            $altStorageDb = $this->normalizePath($root . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'database.sqlite');
            $searchedPaths[] = $altStorageDb;
            
            if ($this->fileExistsNormalized($altStorageDb)) {
                $database = $altStorageDb;
            }
        }

        if ($database === null) {
            return new DatabaseConnectionError(
                'Database Not Initialized',
                'SQLite database file not found. The project may not have been migrated yet.',
                'Run "php ish migrate" to initialize the database, or ensure DB_DATABASE in .env points to the correct path.',
                $searchedPaths
            );
        }

        try {
            // Use realpath for final connection to ensure canonical path
            $realDatabase = realpath($database);
            if ($realDatabase === false) {
                $realDatabase = $database;
            }
            
            $pdo = new PDO('sqlite:' . $realDatabase);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $pdo->exec('PRAGMA foreign_keys = ON');
            return $pdo;
        } catch (\Throwable $e) {
            return new DatabaseConnectionError(
                'Connection Failed',
                'Failed to connect to SQLite database: ' . $e->getMessage(),
                'Ensure the database file is not corrupted and is readable.',
                [$database]
            );
        }
    }

    /**
     * Connect to MySQL or PostgreSQL database.
     */
    private function connectMysqlOrPgsql(string $driver, array $env): PDO|DatabaseConnectionError
    {
        // Check if the required PDO driver extension is loaded
        $extensionName = "pdo_{$driver}";
        if (!extension_loaded($extensionName)) {
            $availableDrivers = class_exists('PDO') ? implode(', ', \PDO::getAvailableDrivers()) : 'PDO not loaded';
            return new DatabaseConnectionError(
                ucfirst($driver) . ' Driver Not Available',
                "The {$extensionName} PHP extension is not loaded.",
                "Available PDO drivers: {$availableDrivers}. Enable {$extensionName} in your php.ini."
            );
        }

        $host = $env['DB_HOST'] ?? '127.0.0.1';
        $port = $env['DB_PORT'] ?? ($driver === 'mysql' ? '3306' : '5432');
        $database = $env['DB_DATABASE'] ?? 'ishmael';
        $username = $env['DB_USERNAME'] ?? 'root';
        $password = $env['DB_PASSWORD'] ?? '';

        try {
            $dsn = "{$driver}:host={$host};port={$port};dbname={$database}";
            $pdo = new PDO($dsn, $username, $password);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            return $pdo;
        } catch (\Throwable $e) {
            return new DatabaseConnectionError(
                'Connection Failed',
                "Failed to connect to {$driver} database: " . $e->getMessage(),
                "Ensure the database server is running and credentials in .env are correct."
            );
        }
    }

    /**
     * Normalize path separators for cross-platform compatibility (especially Windows).
     */
    private function normalizePath(string $path): string
    {
        // Replace forward and backslashes with the system's DIRECTORY_SEPARATOR
        $normalized = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path);
        
        // Remove duplicate separators
        $normalized = preg_replace('#' . preg_quote(DIRECTORY_SEPARATOR, '#') . '+#', DIRECTORY_SEPARATOR, $normalized);
        
        return $normalized;
    }

    /**
     * Check if a path is absolute (handles both Unix and Windows paths).
     */
    private function isAbsolutePath(string $path): bool
    {
        // Unix absolute path
        if (str_starts_with($path, '/')) {
            return true;
        }
        // Windows absolute path (e.g., C:\ or D:/)
        if (preg_match('/^[a-zA-Z]:[\\\\|\/]/', $path)) {
            return true;
        }
        return false;
    }

    /**
     * Check if file exists with normalized path handling.
     */
    private function fileExistsNormalized(string $path): bool
    {
        // Try direct check first
        if (file_exists($path)) {
            return true;
        }
        
        // Try with realpath (resolves symlinks and normalizes)
        $real = realpath($path);
        if ($real !== false && file_exists($real)) {
            return true;
        }
        
        return false;
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
