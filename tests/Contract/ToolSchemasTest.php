<?php



declare(strict_types=1);



use IshmaelPHP\McpServer\Server\RequestRouter;

use IshmaelPHP\McpServer\Tools\HealthVersionTool;

use IshmaelPHP\McpServer\Tools\FeaturePackListTool;

use IshmaelPHP\McpServer\Tools\FeaturePackCreateTool;

use IshmaelPHP\McpServer\Tools\ProjectInfoTool;

use IshmaelPHP\McpServer\Project\ProjectContext;

use IshmaelPHP\McpServer\Support\JsonSchemaValidator;

use PHPUnit\Framework\TestCase;



final class ToolSchemasTest extends TestCase

{

    public function test_health_version_contract_and_execution_validates(): void

    {

        $router = new RequestRouter();

        $router->registerTool(new HealthVersionTool('0.1.0-test'));



        $list = $router->listTools();

        $found = null;

        foreach ($list as $t) {

            if ($t['name'] === 'health/version') { $found = $t; break; }

        }

        $this->assertNotNull($found, 'health/version should be listed');

        $this->assertIsArray($found['inputSchema']);

        $this->assertIsArray($found['outputSchema']);



        $resp = $router->dispatch('health/version', []);

        $this->assertArrayHasKey('result', $resp);



        // Also validate against schema explicitly

        $validator = new JsonSchemaValidator();

        $this->assertSame([], $validator->validate($resp['result'], (new HealthVersionTool('x'))->getOutputSchema()));

    }



    public function test_feature_pack_list_rejects_additional_props(): void

    {

        $context = ProjectContext::discover();

        $router = new RequestRouter();

        $router->registerTool(new FeaturePackListTool($context));



        // include an unknown property to trigger additionalProperties=false

        $resp = $router->dispatch('ish:featurePack:list', ['unknown' => 'x']);

        $this->assertArrayHasKey('error', $resp, 'Should return validation error');

        $this->assertSame(40001, $resp['error']['code']);

    }



    public function test_all_tools_expose_schemas(): void

    {

        $context = ProjectContext::discover();

        $router = new RequestRouter();

        $router->registerTool(new HealthVersionTool('0.1.0-test'));

        $router->registerTool(new ProjectInfoTool($context));

        $router->registerTool(new FeaturePackListTool($context));

        $router->registerTool(new FeaturePackCreateTool($context));



        foreach ($router->listTools() as $tool) {

            $this->assertIsArray($tool['inputSchema'], $tool['name'] . ' inputSchema');

            $this->assertIsArray($tool['outputSchema'], $tool['name'] . ' outputSchema');

        }

    }

}