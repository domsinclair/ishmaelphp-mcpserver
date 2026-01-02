<?php

declare(strict_types=1);

namespace Ishmael\McpServer\Tests\Support;

use Ishmael\McpServer\Project\ProjectContext;
use Ishmael\McpServer\Support\IshCliBridge;
use PHPUnit\Framework\TestCase;

class IshCliBridgePreviewTest extends TestCase
{
    public function testParsePreviewOutput(): void
    {
        $context = $this->createMock(ProjectContext::class);
        $bridge = new IshCliBridge($context);

        $output = <<<TXT
Some other output
---PREVIEW-START---
Path: /path/to/Controller.php
Content:
<?php class Controller {}
---PREVIEW-END---
---PREVIEW-START---
Path: /path/to/View.php
Content:
<h1>View</h1>
---PREVIEW-END---
TXT;

        $reflection = new \ReflectionClass(IshCliBridge::class);
        $method = $reflection->getMethod('parsePreviewOutput');
        $method->setAccessible(true);

        $result = $method->invoke($bridge, $output);

        $this->assertCount(2, $result);
        $this->assertEquals('/path/to/Controller.php', $result[0]['path']);
        $this->assertEquals('<?php class Controller {}', $result[0]['content']);
        $this->assertEquals('/path/to/View.php', $result[1]['path']);
        $this->assertEquals('<h1>View</h1>', $result[1]['content']);
    }
}
