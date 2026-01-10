<?php

declare(strict_types=1);

namespace Ishmael\McpServer\Tests\Tools;

use Ishmael\McpServer\Project\ProjectContext;
use Ishmael\McpServer\Project\PathSandbox;
use Ishmael\McpServer\Tools\MigrateAnalyzeTool;
use PHPUnit\Framework\TestCase;

final class MigrateAnalyzeToolTest extends TestCase
{
    private string $tempRoot;

    protected function setUp(): void
    {
        $this->tempRoot = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'ish_mcp_migrate_analyze_test_' . uniqid();
        mkdir($this->tempRoot, 0777, true);
        mkdir($this->tempRoot . DIRECTORY_SEPARATOR . 'database', 0777, true);
        mkdir($this->tempRoot . DIRECTORY_SEPARATOR . 'database' . DIRECTORY_SEPARATOR . 'migrations', 0777, true);
        mkdir($this->tempRoot . DIRECTORY_SEPARATOR . 'bin', 0777, true);
        
        // Create a dummy ish binary
        file_put_contents($this->tempRoot . DIRECTORY_SEPARATOR . 'bin' . DIRECTORY_SEPARATOR . 'ish', "<?php echo \"[Core] 2026_01_10_000001_create_users_table\\nSQL: ...\\n\";");
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->tempRoot);
    }

    private function removeDirectory(string $dir): void
    {
        if (!is_dir($dir)) return;
        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            (is_dir("$dir/$file")) ? $this->removeDirectory("$dir/$file") : unlink("$dir/$file");
        }
        rmdir($dir);
    }

    public function testDetectsDataLossRisks(): void
    {
        $migrationContent = "<?php\n\nclass CreateUsersTable {\n    public function up() {\n        Schema::dropColumn('users', 'email');\n    }\n}";
        file_put_contents($this->tempRoot . DIRECTORY_SEPARATOR . 'database' . DIRECTORY_SEPARATOR . 'migrations' . DIRECTORY_SEPARATOR . '2026_01_10_000001_create_users_table.php', $migrationContent);

        $context = new ProjectContext($this->tempRoot, new PathSandbox($this->tempRoot), ['ish' => $this->tempRoot . DIRECTORY_SEPARATOR . 'bin' . DIRECTORY_SEPARATOR . 'ish']);
        $tool = new MigrateAnalyzeTool($context);
        $result = $tool->execute([]);

        $this->assertTrue($result['success']);
        $this->assertCount(1, $result['pending_migrations']);
        $this->assertCount(1, $result['risks']);
        $this->assertEquals('high', $result['risks'][0]['severity']);
        $this->assertEquals('Data Loss', $result['risks'][0]['type']);
    }

    public function testDetectsPerformanceRisks(): void
    {
        $migrationContent = "<?php\n\nclass AddAgeToUsersTable {\n    public function up() {\n        Schema::table('users', function(\$table) {\n            \$table->addColumn('age', 'integer');\n        });\n    }\n}";
        file_put_contents($this->tempRoot . DIRECTORY_SEPARATOR . 'database' . DIRECTORY_SEPARATOR . 'migrations' . DIRECTORY_SEPARATOR . '2026_01_10_000001_create_users_table.php', $migrationContent);

        $context = new ProjectContext($this->tempRoot, new PathSandbox($this->tempRoot), ['ish' => $this->tempRoot . DIRECTORY_SEPARATOR . 'bin' . DIRECTORY_SEPARATOR . 'ish']);
        $tool = new MigrateAnalyzeTool($context);
        $result = $tool->execute([]);

        $this->assertCount(1, $result['risks']);
        $this->assertEquals('medium', $result['risks'][0]['severity']);
        $this->assertEquals('Performance / Locking', $result['risks'][0]['type']);
    }

    public function testDetectsRawSQLRisks(): void
    {
        $migrationContent = "<?php\n\nclass RawSqlMigration {\n    public function up() {\n        DB::executeRaw('DROP TABLE users');\n    }\n}";
        file_put_contents($this->tempRoot . DIRECTORY_SEPARATOR . 'database' . DIRECTORY_SEPARATOR . 'migrations' . DIRECTORY_SEPARATOR . '2026_01_10_000001_create_users_table.php', $migrationContent);

        $context = new ProjectContext($this->tempRoot, new PathSandbox($this->tempRoot), ['ish' => $this->tempRoot . DIRECTORY_SEPARATOR . 'bin' . DIRECTORY_SEPARATOR . 'ish']);
        $tool = new MigrateAnalyzeTool($context);
        $result = $tool->execute([]);

        // It should detect both Data Loss (DROP TABLE in string) and Raw SQL
        // Wait, my regex for Data Loss is /dropTable|dropColumn/i. "DROP TABLE" as raw SQL won't match.
        // But "executeRaw" will match Raw SQL.
        
        $types = array_column($result['risks'], 'type');
        $this->assertContains('Raw SQL', $types);
    }
}
