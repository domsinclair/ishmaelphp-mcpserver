<?php

declare(strict_types=1);

namespace Ishmael\McpServer\Tests\Prompts;

use Ishmael\McpServer\Prompts\PlanFeaturePrompt;
use PHPUnit\Framework\TestCase;

final class PlanFeaturePromptTest extends TestCase
{
    public function testGetName(): void
    {
        $prompt = new PlanFeaturePrompt();
        $this->assertEquals('ishmael:plan-feature', $prompt->getName());
    }

    public function testGetMessagesWithRequirements(): void
    {
        $prompt = new PlanFeaturePrompt();
        $prompt->withArguments(['requirements' => 'Build a user profile page']);
        
        $messages = $prompt->getMessages();
        
        $this->assertCount(2, $messages);
        $this->assertEquals('user', $messages[0]['role']);
        $this->assertStringContainsString('Build a user profile page', $messages[0]['content']['text']);
        
        $this->assertEquals('assistant', $messages[1]['role']);
        $this->assertStringContainsString('App-Specific Feature', $messages[1]['content']['text']);
        $this->assertStringContainsString('Feature Pack', $messages[1]['content']['text']);
        $this->assertStringContainsString('ish:make:controller', $messages[1]['content']['text']);
        $this->assertStringContainsString('ish://docs/framework-map', $messages[1]['content']['text']);
    }

    public function testGetMessagesWithoutRequirements(): void
    {
        $prompt = new PlanFeaturePrompt();
        $messages = $prompt->getMessages();
        
        $this->assertStringContainsString('No requirements provided', $messages[0]['content']['text']);
    }

    public function testGetArguments(): void
    {
        $prompt = new PlanFeaturePrompt();
        $args = $prompt->getArguments();
        
        $this->assertCount(1, $args);
        $this->assertEquals('requirements', $args[0]['name']);
        $this->assertTrue($args[0]['required']);
    }
}
