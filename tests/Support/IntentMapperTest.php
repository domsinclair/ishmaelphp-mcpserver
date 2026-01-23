<?php

declare(strict_types=1);

namespace Ishmael\McpServer\Tests\Support;

use Ishmael\McpServer\Support\IntentMapper;
use PHPUnit\Framework\TestCase;

final class IntentMapperTest extends TestCase
{
    private string $mapPath;

    protected function setUp(): void
    {
        $this->mapPath = __DIR__ . '/../../resources/intent-map.json';
    }

    public function testDetectsDesignFeaturePackIntent(): void
    {
        $mapper = new IntentMapper($this->mapPath);
        
        $result = $mapper->detect('I want to build a new plugin');
        
        $this->assertEquals('design_feature_pack', $result['intent']);
        $this->assertContains('feature_pack', $result['terms']);
        $this->assertGreaterThanOrEqual(0.6, $result['confidence']);
    }

    public function testDetectsAddLicensingIntent(): void
    {
        $mapper = new IntentMapper($this->mapPath);
        
        // Use "license" which is a synonym for "licensing"
        $result = $mapper->detect('How do I add a license to my addon?');
        
        $this->assertContains('feature_pack', $result['terms'], 'Should detect feature_pack term');
        $this->assertContains('licensing', $result['terms'], 'Should detect licensing term');
        $this->assertEquals('add_licensing_to_pack', $result['intent']);
    }

    public function testReturnsNullOnAmbiguousInput(): void
    {
        $mapper = new IntentMapper($this->mapPath);
        
        $result = $mapper->detect('hello world');
        
        $this->assertNull($result['intent']);
    }

    public function testRetrievesClarificationRules(): void
    {
        $mapper = new IntentMapper($this->mapPath);
        
        $rules = $mapper->getClarificationRules('design_feature_pack');
        
        $this->assertNotNull($rules);
        $this->assertArrayHasKey('questions', $rules);
        $this->assertArrayHasKey('target_environment', $rules['questions']);
    }

    public function testRetrievesBehaviourContract(): void
    {
        $mapper = new IntentMapper($this->mapPath);
        
        $contract = $mapper->getBehaviourContract('design_feature_pack');
        
        $this->assertNotNull($contract);
        $this->assertArrayHasKey('forbidden', $contract);
        $this->assertContains('runtime_secrets', $contract['forbidden']);
    }

    public function testDetectsCreateLocalModuleIntent(): void
    {
        $mapper = new IntentMapper($this->mapPath);
        
        $result = $mapper->detect('I want to create a new local module for my rescue app');
        
        $this->assertEquals('create_local_module', $result['intent']);
        $this->assertContains('local_module', $result['terms']);
    }

    public function testDetectsArchitecturalTermsInLocalModuleContext(): void
    {
        $mapper = new IntentMapper($this->mapPath);
        
        $result = $mapper->detect('Add a module with sqlite and tailwind');
        
        $this->assertContains('local_module', $result['terms']);
        $this->assertContains('database', $result['terms']);
        $this->assertContains('ui_stack', $result['terms']);
    }
}
