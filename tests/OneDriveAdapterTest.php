<?php

namespace Leapt\FlysystemOneDrive\Tests;

use Microsoft\Graph\Graph;
use PHPUnit\Framework\TestCase;
use Microsoft\Graph\Http\GraphRequest;
use Leapt\FlysystemOneDrive\OneDriveAdapter;

class OneDriveAdapterTest extends TestCase
{
    /** @var \Microsoft\Graph\Graph|\PHPUnit_Framework_MockObject_MockObject */
    protected $graph;

    /** @var \Microsoft\Graph\Http\GraphRequest|\PHPUnit_Framework_MockObject_MockObject */
    public $graphRequest;

    /** @var \NicolasBeauvais\FlysystemOneDrive\OneDriveAdapter */
    protected $oneDriveAdapter;

    public function setUp(): void
    {
        $this->graph = $this->getMockBuilder(Graph::class)->getMock();
        $this->graphRequest = $this->getMockBuilder(GraphRequest::class)->disableOriginalConstructor()->getMock();

        $this->graph->method('createRequest')->willReturn($this->graphRequest);

        $this->oneDriveAdapter = new OneDriveAdapter($this->graph);
    }

    /** @test */
    public function it_can_run_tests()
    {
        $this->assertTrue(true);
    }
}
