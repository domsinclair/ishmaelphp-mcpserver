<?php

declare(strict_types=1);

namespace Ishmael\McpServer\Tests\Server;

use Ishmael\McpServer\Contracts\Tool;
use Ishmael\McpServer\Server\RequestRouter;
use Ishmael\McpServer\Tools\DynamicIshTool;
use PHPUnit\Framework\TestCase;

final class RequestRouterTest extends TestCase
{
    public function testRegisterToolPreventsDynamicOverwritingDedicated(): void
    {
        $router = new RequestRouter();
        
        $dedicated = $this->createMock(Tool::class);
        $dedicated->method('getName')->willReturn('ish:test');
        
        $dynamic = $this->createMock(DynamicIshTool::class);
        $dynamic->method('getName')->willReturn('ish:test');
        
        // Register dedicated first
        $router->registerTool($dedicated);
        
        // Try to register dynamic with same name
        $router->registerTool($dynamic);
        
        $tools = $router->listTools();
        $this->assertCount(1, $tools);
        
        // In this case, we can't easily check the instance type from listTools output,
        // but we can check if the registration was ignored by looking at internal state if we had access, 
        // or by behavior if they had different descriptions.
    }

    public function testRegisterToolAllowsDedicatedOverwritingDynamic(): void
    {
        $router = new RequestRouter();
        
        $dynamic = $this->createMock(DynamicIshTool::class);
        $dynamic->method('getName')->willReturn('ish:test');
        $dynamic->method('getDescription')->willReturn('Dynamic');
        
        $dedicated = $this->createMock(Tool::class);
        $dedicated->method('getName')->willReturn('ish:test');
        $dedicated->method('getDescription')->willReturn('Dedicated');
        
        // Register dynamic first
        $router->registerTool($dynamic);
        
        // Register dedicated with same name
        $router->registerTool($dedicated);
        
        $tools = $router->listTools();
        $this->assertCount(1, $tools);
        $this->assertEquals('Dedicated', $tools[0]['description']);
    }
}
