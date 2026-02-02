<?php

declare(strict_types=1);

namespace Ishmael\McpServer\Tests\Providers;

use Ishmael\McpServer\Providers\DatabaseSchemaProvider;
use Ishmael\McpServer\Support\DatabaseConnectionFactory;
use Ishmael\McpServer\Project\ProjectContext;
use PHPUnit\Framework\TestCase;
use PDO;

final class DatabaseSchemaProviderTest extends TestCase
{
    private string $dbFile;

    protected function setUp(): void
    {
        $this->dbFile = __DIR__ . '/test.sqlite';
        if (file_exists($this->dbFile)) {
            unlink($this->dbFile);
        }

        $pdo = new PDO('sqlite:' . $this->dbFile);
        $pdo->exec("CREATE TABLE users (users_id INTEGER PRIMARY KEY, email TEXT NOT NULL)");
        $pdo->exec("CREATE TABLE posts (posts_id INTEGER PRIMARY KEY, title TEXT, author_id INTEGER, FOREIGN KEY(author_id) REFERENCES users(users_id))");
        $pdo->exec("CREATE INDEX idx_users_email ON users(email)");
    }

    protected function tearDown(): void
    {
        if (file_exists($this->dbFile)) {
            unlink($this->dbFile);
        }
    }

    public function testReadResourceIntrospectsSchema(): void
    {
        $context = $this->createMock(ProjectContext::class);
        $context->method('getRoot')->willReturn(__DIR__);

        // Create a custom factory or mock it to return our test PDO
        $factory = $this->createMock(DatabaseConnectionFactory::class);
        $factory->method('getConnection')->willReturn(new PDO('sqlite:' . $this->dbFile));

        $provider = new DatabaseSchemaProvider($factory);
        $content = $provider->readResource('ish://database/schema');

        $this->assertNotNull($content);
        $data = json_decode($content, true);

        $this->assertArrayHasKey('database', $data);
        $this->assertEquals('sqlite', $data['database']['engine']);
        $this->assertArrayHasKey('users', $data['database']['tables']);
        $this->assertArrayHasKey('posts', $data['database']['tables']);

        $users = $data['database']['tables']['users'];
        $this->assertEquals('core', $users['owner']);
        $this->assertArrayHasKey('users_id', $users['columns']);
        $this->assertTrue($users['columns']['users_id']['primary_key']);

        $posts = $data['database']['tables']['posts'];
        $this->assertCount(1, $posts['foreign_keys']);
        $this->assertEquals('author_id', $posts['foreign_keys'][0]['local_column']);
        $this->assertEquals('users', $posts['foreign_keys'][0]['referenced_table']);
    }
}
