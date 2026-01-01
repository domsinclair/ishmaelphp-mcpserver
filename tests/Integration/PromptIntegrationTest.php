<?php

declare(strict_types=1);

namespace Ishmael\McpServer\Tests\Integration;

use Ishmael\McpServer\Protocol\StdioTransport;
use Ishmael\McpServer\Providers\StaticPromptProvider;
use Ishmael\McpServer\Prompts\BestPracticesPrompt;
use Ishmael\McpServer\Prompts\SetupProjectPrompt;
use Ishmael\McpServer\Server\RequestRouter;
use Ishmael\McpServer\Server\Server;
use Ishmael\McpServer\Providers\AggregateResourceProvider;
use Ishmael\McpServer\Providers\StaticResourceProvider;
use PHPUnit\Framework\TestCase;

final class PromptIntegrationTest extends TestCase
{
    private function readAllLines(string $buffer): array
    {
        $lines = array_filter(array_map('trim', explode("\n", $buffer)), fn($l) => $l !== '');
        return array_map(fn($l) => json_decode($l, true), $lines);
    }

    public function test_list_and_get_prompts(): void
    {
        $in = fopen('php://temp', 'w+');
        $out = fopen('php://temp', 'w+');
        $err = fopen('php://temp', 'w+');

        $bestPractices = new BestPracticesPrompt();
        $setupProject = new SetupProjectPrompt();

        $promptList = [
            ['id' => $bestPractices->getName(), 'description' => $bestPractices->getDescription()],
            ['id' => $setupProject->getName(), 'description' => $setupProject->getDescription()],
        ];

        fwrite($in, json_encode(['id' => 1, 'method' => 'listPrompts']) . "\n");
        fwrite($in, json_encode(['id' => 2, 'method' => 'prompts/get', 'params' => ['name' => 'ishmael:best-practices']]) . "\n");
        rewind($in);

        $transport = new StdioTransport($in, $out, $err);
        $router = new RequestRouter();
        $resources = new AggregateResourceProvider([new StaticResourceProvider([])]);
        $prompts = new StaticPromptProvider($promptList, [$bestPractices, $setupProject]);
        $server = new Server($router, $transport, $resources, $prompts);

        $server->run();

        rewind($out);
        $buffer = stream_get_contents($out) ?: '';
        $messages = $this->readAllLines($buffer);

        $this->assertCount(2, $messages);

        // listPrompts
        $this->assertSame(1, $messages[0]['id']);
        $this->assertCount(2, $messages[0]['result']['prompts']);
        $this->assertSame('ishmael:best-practices', $messages[0]['result']['prompts'][0]['id']);

        // prompts/get
        $this->assertSame(2, $messages[1]['id']);
        $this->assertArrayHasKey('messages', $messages[1]['result']);
        $this->assertStringContainsString('Ishmael best practices', $messages[1]['result']['description']);
        $this->assertStringContainsString('Use the CLI for Scaffolding', $messages[1]['result']['messages'][0]['content']['text']);
    }
}
