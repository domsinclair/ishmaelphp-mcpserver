<?php

declare(strict_types=1);

use Ishmael\McpServer\Server\ProjectStateManager;
use Ishmael\McpServer\Server\RequestRouter;
use Ishmael\McpServer\Tools\McpModeTool;
use PHPUnit\Framework\TestCase;

final class McpModeToolTest extends TestCase
{
    private function makeTempDir(): string
    {
        $base = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'ishmael_mcp_mode_tool_' . bin2hex(random_bytes(4));
        mkdir($base, 0777, true);
        return $base;
    }

    public function test_mode_get_and_set_with_validation(): void
    {
        $root = $this->makeTempDir();
        $state = new ProjectStateManager($root);
        $tool = new McpModeTool($state);

        // Route through RequestRouter to exercise schema validation
        $router = new RequestRouter();
        $router->registerTool($tool);

        // Query current mode
        $resp = $router->dispatch('ish:mcp:mode', []);
        $this->assertArrayHasKey('result', $resp);
        $this->assertSame(ProjectStateManager::MODE_STRICT, $resp['result']['mode']);
        $this->assertSame(ProjectStateManager::INIT, $resp['result']['state']);
        $this->assertArrayHasKey('tool_invocation_log', $resp['result']);

        // Set to freeform
        $resp2 = $router->dispatch('ish:mcp:mode', ['mode' => ProjectStateManager::MODE_FREEFORM]);
        $this->assertArrayHasKey('result', $resp2);
        $this->assertSame(ProjectStateManager::MODE_FREEFORM, $resp2['result']['mode']);

        // Legacy standard maps to strict
        $resp3 = $router->dispatch('ish:mcp:mode', ['mode' => ProjectStateManager::MODE_STANDARD]);
        $this->assertArrayHasKey('result', $resp3);
        $this->assertSame(ProjectStateManager::MODE_STRICT, $resp3['result']['mode']);

        // Invalid value rejected by input schema
        $bad = $router->dispatch('ish:mcp:mode', ['mode' => 'invalid']);
        $this->assertArrayHasKey('error', $bad);
        $this->assertSame(40001, $bad['error']['code']);
    }
}
