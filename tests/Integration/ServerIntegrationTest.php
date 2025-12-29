<?php



declare(strict_types=1);



use IshmaelPHP\McpServer\Protocol\StdioTransport;

use IshmaelPHP\McpServer\Providers\AggregateResourceProvider;

use IshmaelPHP\McpServer\Providers\DocsResourceProvider;

use IshmaelPHP\McpServer\Providers\PackageDocsResourceProvider;

use IshmaelPHP\McpServer\Providers\StaticPromptProvider;

use IshmaelPHP\McpServer\Providers\StaticResourceProvider;

use IshmaelPHP\McpServer\Providers\TemplatesResourceProvider;

use IshmaelPHP\McpServer\Project\ProjectContext;

use IshmaelPHP\McpServer\Server\RequestRouter;

use IshmaelPHP\McpServer\Server\Server;

use IshmaelPHP\McpServer\Tools\FeaturePackCreateTool;

use IshmaelPHP\McpServer\Tools\FeaturePackListTool;

use IshmaelPHP\McpServer\Tools\HealthVersionTool;

use IshmaelPHP\McpServer\Tools\ProjectInfoTool;

use PHPUnit\Framework\TestCase;



final class ServerIntegrationTest extends TestCase

{

    private function makeServer($in, $out, $err, string $appVersion = '0.1.0-test'): Server

    {

        $transport = new StdioTransport($in, $out, $err);

        $router = new RequestRouter();

        $router->registerTool(new HealthVersionTool($appVersion));

        $context = ProjectContext::discover();

        $router->registerTool(new ProjectInfoTool($context));

        $router->registerTool(new FeaturePackListTool($context));

        $router->registerTool(new FeaturePackCreateTool($context));



        // Minimal resources and prompts similar to bin entrypoint

        if ($context->getRoot() !== null && $context->getSandbox() !== null) {

            $sandbox = $context->getSandbox();

            $root = $context->getRoot();

            $docsProvider = new DocsResourceProvider($sandbox, [

                $root . DIRECTORY_SEPARATOR . 'Docs',

                $root . DIRECTORY_SEPARATOR . 'site',

            ]);

            $templatesProvider = new TemplatesResourceProvider($sandbox, $root . DIRECTORY_SEPARATOR . 'Templates');

            $packagedDocs = new PackageDocsResourceProvider();

            $resources = new AggregateResourceProvider([

                new StaticResourceProvider([]),

                $docsProvider,

                $templatesProvider,

                $packagedDocs

            ]);

        } else {

            $resources = new AggregateResourceProvider([

                new StaticResourceProvider([]),

                new PackageDocsResourceProvider(),

            ]);

        }

        $prompts = new StaticPromptProvider([]);



        return new Server($router, $transport, $resources, $prompts);

    }



    private function readAllLines(string $buffer): array

    {

        $lines = array_filter(array_map('trim', explode("\n", $buffer)), fn($l) => $l !== '');

        return array_map(fn($l) => json_decode($l, true), $lines);

    }



    public function test_envelopes_and_health_version(): void

    {

        $in = fopen('php://temp', 'w+');

        $out = fopen('php://temp', 'w+');

        $err = fopen('php://temp', 'w+');



        fwrite($in, json_encode(['id' => 1, 'method' => 'listTools']) . "\n");

        fwrite($in, json_encode(['id' => 2, 'method' => 'health/version']) . "\n");

        rewind($in);



        $server = $this->makeServer($in, $out, $err);

        $server->run();



        rewind($out);

        $buffer = stream_get_contents($out) ?: '';

        $messages = $this->readAllLines($buffer);



        $this->assertCount(2, $messages);

        foreach ($messages as $msg) {

            $this->assertArrayHasKey('version', $msg);

            $this->assertSame('0.1', $msg['version']);

            $this->assertArrayHasKey('meta', $msg);

            $this->assertArrayHasKey('id', $msg);

            $this->assertIsInt($msg['meta']['durationMs']);

        }

        // Ensure health/version result shape

        $hv = $messages[1];

        $this->assertArrayHasKey('result', $hv);

        $this->assertArrayHasKey('ok', $hv['result']);

        $this->assertTrue($hv['result']['ok']);

    }

}