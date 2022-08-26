<?php

declare(strict_types=1);

namespace Leapt\FlysystemOneDrive\Tests;

use Leapt\FlysystemOneDrive\OneDriveAdapter;
use Microsoft\Graph\Graph;
use Microsoft\Graph\Http\GraphRequest;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class OneDriveAdapterTest extends TestCase
{
    /** @var \Microsoft\Graph\Http\GraphRequest|MockObject */
    public $graphRequest;
    /** @var \Microsoft\Graph\Graph|MockObject */
    protected $graph;

    /** @var \Leapt\FlysystemOneDrive\OneDriveAdapter */
    protected $oneDriveAdapter;

    protected function setUp(): void
    {
        $this->graph = $this->getMockBuilder(Graph::class)->getMock();
        $this->graphRequest = $this->getMockBuilder(GraphRequest::class)->disableOriginalConstructor()->getMock();

        $this->graph->method('createRequest')->willReturn($this->graphRequest);

        $this->oneDriveAdapter = new OneDriveAdapter($this->graph);
    }

    public function testItCanRunTests()
    {
        $this->assertTrue(true);
    }
}
