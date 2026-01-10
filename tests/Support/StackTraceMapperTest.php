<?php

declare(strict_types=1);

namespace Ishmael\McpServer\Tests\Support;

use Ishmael\McpServer\Support\StackTraceMapper;
use PHPUnit\Framework\TestCase;

final class StackTraceMapperTest extends TestCase
{
    public function testMapExtractsStackTraceAndResolvesPaths(): void
    {
        $root = '/var/www/html';
        $mapper = new StackTraceMapper($root);

        $logMessage = "Something went wrong\n#0 " . $root . "/app/Services/PostService.php(42): Ishmael\\Core\\Database->query()\n#1 [internal function]: Modules\\Blog\\Controllers\\PostController->index()\n#2 /other/path/file.php(10): foo()";

        $result = $mapper->map($logMessage);

        $this->assertEquals("Something went wrong", $result['message']);
        $this->assertCount(3, $result['stack_trace']);

        // Frame #0: Within root
        $this->assertEquals("app/Services/PostService.php", str_replace(DIRECTORY_SEPARATOR, '/', $result['stack_trace'][0]['file']));
        $this->assertEquals(42, $result['stack_trace'][0]['line']);
        $this->assertEquals("Ishmael\\Core\\Database->query()", $result['stack_trace'][0]['function']);

        // Frame #1: [internal function]
        $this->assertEquals("[internal function]", $result['stack_trace'][1]['file']);

        // Frame #2: Outside root
        $this->assertEquals("/other/path/file.php", str_replace(DIRECTORY_SEPARATOR, '/', $result['stack_trace'][2]['file']));
    }
}
