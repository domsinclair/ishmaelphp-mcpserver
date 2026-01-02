<?php

declare(strict_types=1);

namespace Ishmael\McpServer\Tests\Prompts;

use Ishmael\McpServer\Prompts\TroubleshootPrompt;
use PHPUnit\Framework\TestCase;

final class TroubleshootPromptTest extends TestCase
{
    public function testGetName(): void
    {
        $prompt = new TroubleshootPrompt();
        $this->assertEquals('ishmael:troubleshoot', $prompt->getName());
    }

    public function testGetDescription(): void
    {
        $prompt = new TroubleshootPrompt();
        $this->assertStringContainsString('Automated diagnostic gathering', $prompt->getDescription());
    }

    public function testGetMessagesWithoutArgs(): void
    {
        $prompt = new TroubleshootPrompt();
        $messages = $prompt->getMessages();

        $this->assertCount(1, $messages);
        $this->assertEquals('assistant', $messages[0]['role']);
        $this->assertStringContainsString('No specific error message provided', $messages[0]['content']['text']);
        $this->assertStringContainsString('ish://project/health', $messages[0]['content']['text']);
        $this->assertStringContainsString('ish:log:tail', $messages[0]['content']['text']);
    }

    public function testGetMessagesWithArgs(): void
    {
        $prompt = new TroubleshootPrompt();
        $prompt = $prompt->withArguments(['error_message' => 'Class not found']);
        $messages = $prompt->getMessages();

        $this->assertStringContainsString('Class not found', $messages[0]['content']['text']);
    }

    public function testGetArguments(): array
    {
        $prompt = new TroubleshootPrompt();
        $args = $prompt->getArguments();

        $this->assertCount(1, $args);
        $this->assertEquals('error_message', $args[0]['name']);
        
        return $args;
    }
}
