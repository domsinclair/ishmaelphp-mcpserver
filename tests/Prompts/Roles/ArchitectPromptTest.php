<?php

declare(strict_types=1);

namespace Ishmael\McpServer\Tests\Prompts\Roles;

use Ishmael\McpServer\Prompts\Roles\ArchitectPrompt;
use PHPUnit\Framework\TestCase;

final class ArchitectPromptTest extends TestCase
{
    public function testGetName(): void
    {
        $prompt = new ArchitectPrompt();
        $this->assertEquals('role:architect', $prompt->getName());
    }

    public function testGetMessagesContainsRegistryTool(): void
    {
        $prompt = new ArchitectPrompt();
        $prompt->withArguments([
            'analysisSummary' => 'Test requirements',
            'constraints' => 'None'
        ]);

        $messages = $prompt->getMessages();
        $this->assertCount(1, $messages);
        $text = $messages[0]['content']['text'];

        $this->assertStringContainsString('ish:featurePack:registry', $text);
        $this->assertStringContainsString('Mandatory', $text);
        $this->assertStringContainsString('search for existing capabilities', $text);
    }

    public function testGetArguments(): void
    {
        $prompt = new ArchitectPrompt();
        $args = $prompt->getArguments();

        $this->assertCount(2, $args);
        $this->assertEquals('analysisSummary', $args[0]['name']);
        $this->assertTrue($args[0]['required']);
        $this->assertEquals('constraints', $args[1]['name']);
        $this->assertFalse($args[1]['required']);
    }
}
