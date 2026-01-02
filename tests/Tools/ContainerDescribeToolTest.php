<?php

declare(strict_types=1);

namespace Ishmael\McpServer\Tests\Tools;

use Ishmael\McpServer\Project\ProjectContext;
use Ishmael\McpServer\Tools\ContainerDescribeTool;
use PHPUnit\Framework\TestCase;

class ContainerDescribeToolTest extends TestCase
{
    public function testExecuteAgainstReferenceApp(): void
    {
        $referenceAppRoot = 'D:\JetBrainsProjects\PhpStorm\my-app';
        if (!is_dir($referenceAppRoot)) {
            $this->markTestSkipped('Reference app not found at ' . $referenceAppRoot);
        }

        $context = ProjectContext::discover($referenceAppRoot);
        $tool = new ContainerDescribeTool($context);

        $result = $tool->execute([]);

        if (isset($result['error'])) {
            $this->fail('Tool execution failed with error: ' . $result['error']);
        }

        $this->assertIsArray($result);
        $this->assertArrayHasKey('services', $result);
        $this->assertArrayHasKey('aliases', $result);

        $serviceIds = array_column($result['services'], 'id');
        // If config_repo is not there, let's see what IS there
        if (!in_array('config_repo', $serviceIds)) {
             // print_r($serviceIds);
        }
        
        $this->assertNotEmpty($result['services'], 'Service list should not be empty');
    }
}
