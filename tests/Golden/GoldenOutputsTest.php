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

use IshmaelPHP\McpServer\Tools\HealthVersionTool;

use PHPUnit\Framework\TestCase;



final class GoldenOutputsTest extends TestCase

{

    private function makeServerWithStatic($in, $out, $err): Server

    {

        $transport = new StdioTransport($in, $out, $err);

        $router = new RequestRouter();

        $router->registerTool(new HealthVersionTool('0.1.0-test'));



        $staticResources = new StaticResourceProvider([

            ['id' => 'docs:feature-packs', 'description' => 'Ishmael Feature Packs overview'],

            ['id' => 'docs:getting-started', 'description' => 'Getting started with Ishmael'],

        ]);



        $context = ProjectContext::discover();

        if ($context->getRoot() !== null && $context->getSandbox() !== null) {

            $sandbox = $context->getSandbox();

            $root = $context->getRoot();

            $docsProvider = new DocsResourceProvider($sandbox, [

                $root . DIRECTORY_SEPARATOR . 'Docs',

                $root . DIRECTORY_SEPARATOR . 'site',

            ]);

            $templatesProvider = new TemplatesResourceProvider($sandbox, $root . DIRECTORY_SEPARATOR . 'Templates');

            $packagedDocs = new PackageDocsResourceProvider();

            $resources = new AggregateResourceProvider([$staticResources, $docsProvider, $templatesProvider, $packagedDocs]);

        } else {

            $resources = new AggregateResourceProvider([$staticResources, new PackageDocsResourceProvider()]);

        }

        $prompts = new StaticPromptProvider([]);

        return new Server($router, $transport, $resources, $prompts);

    }



    public function test_list_resources_static_subset_matches_golden(): void

    {

        $in = fopen('php://temp', 'w+');

        $out = fopen('php://temp', 'w+');

        $err = fopen('php://temp', 'w+');



        fwrite($in, json_encode(['id' => 1, 'method' => 'listResources']) . "\n");

        rewind($in);



        $server = $this->makeServerWithStatic($in, $out, $err);

        $server->run();



        rewind($out);

        $line = trim((string)fgets($out));

        $resp = json_decode($line, true);

        $this->assertIsArray($resp);

        $this->assertArrayHasKey('result', $resp);

        $resources = $resp['result']['resources'] ?? [];

        // Filter only our two static ones in stable order by id

        $subset = array_values(array_filter($resources, fn($r) => in_array($r['id'] ?? '', [

            'docs:feature-packs','docs:getting-started'

        ], true)));

        usort($subset, fn($a,$b) => strcmp($a['id'], $b['id']));



        $goldenPath = __DIR__ . '/../fixtures/resources_static.golden.json';

        $expected = json_decode((string)file_get_contents($goldenPath), true);

        $this->assertSame($expected, $subset, 'Static resources subset should match golden file');

    }



    public function test_parse_error_envelope_matches_golden(): void

    {

        $in = fopen('php://temp', 'w+');

        $out = fopen('php://temp', 'w+');

        $err = fopen('php://temp', 'w+');



        fwrite($in, '{this is not json}\n' . "\n");

        rewind($in);



        $server = $this->makeServerWithStatic($in, $out, $err);

        $server->run();



        rewind($out);

        $line = trim((string)fgets($out));

        $resp = json_decode($line, true);

        $this->assertIsArray($resp);



        $goldenPath = __DIR__ . '/../fixtures/parse_error_envelope.golden.json';

        $expected = json_decode((string)file_get_contents($goldenPath), true);

        // durationMs varies; drop meta for comparison

        unset($resp['meta']);

        $this->assertSame($expected, $resp, 'Parse error envelope should match golden (excluding meta)');

    }

}