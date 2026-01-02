<?php

declare(strict_types=1);

namespace Ishmael\McpServer\Tests\Providers;

use Ishmael\McpServer\Contracts\Prompt;
use Ishmael\McpServer\Providers\StaticPromptProvider;
use PHPUnit\Framework\TestCase;

final class StaticPromptProviderTest extends TestCase
{
    public function testGetPromptInjectsArguments(): void
    {
        $mockPrompt = new class implements Prompt {
            public array $args = [];
            public function getName(): string { return 'test:prompt'; }
            public function getDescription(): string { return 'test desc'; }
            public function getMessages(): array { return []; }
            public function getArguments(): array { return []; }
            public function withArguments(array $args): self {
                $this->args = $args;
                return $this;
            }
        };

        $provider = new StaticPromptProvider([], [$mockPrompt]);
        $result = $provider->getPrompt('test:prompt', ['foo' => 'bar']);

        $this->assertSame($mockPrompt, $result);
        $this->assertEquals(['foo' => 'bar'], $result->args);
    }
}
