<?php

declare(strict_types=1);

namespace Leapt\FlysystemOneDrive\Tests;

use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Promise\Promise;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use League\Flysystem\Config;
use League\Flysystem\FileAttributes;
use League\Flysystem\UnableToCopyFile;
use League\Flysystem\UnableToCreateDirectory;
use League\Flysystem\UnableToDeleteDirectory;
use League\Flysystem\UnableToDeleteFile;
use League\Flysystem\UnableToMoveFile;
use League\Flysystem\UnableToReadFile;
use League\Flysystem\UnableToRetrieveMetadata;
use League\Flysystem\UnableToSetVisibility;
use League\Flysystem\UnableToWriteFile;
use League\Flysystem\Visibility;
use Leapt\FlysystemOneDrive\OneDriveAdapter;
use Microsoft\Graph\Graph;
use Microsoft\Graph\Http\GraphRequest;
use Microsoft\Graph\Http\GraphResponse;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class OneDriveAdapterTest extends TestCase
{
    private Graph&MockObject $graph;
    private GraphRequest&MockObject $graphRequest;
    private OneDriveAdapter $oneDriveAdapter;

    protected function setUp(): void
    {
        $this->graph = $this->getMockBuilder(Graph::class)->getMock();
        $this->graphRequest = $this->getMockBuilder(GraphRequest::class)->disableOriginalConstructor()->getMock();
        $this->graph->method('createRequest')->willReturn($this->graphRequest);
        $this->oneDriveAdapter = new OneDriveAdapter($this->graph);
    }

    public function testDirectoryExists(): void
    {
        self::assertTrue($this->oneDriveAdapter->directoryExists('/works'));
        $this->makeGraphRequestExecuteMethodThrowException();
        self::assertFalse($this->oneDriveAdapter->directoryExists('/unknown'));
    }

    public function testFileExists(): void
    {
        self::assertTrue($this->oneDriveAdapter->fileExists('/sheet.xlsx'));
        $this->makeGraphRequestExecuteMethodThrowException();
        self::assertFalse($this->oneDriveAdapter->fileExists('/unknown.xlsx'));
    }

    public function testLastModified(): void
    {
        $response = $this->createMock(GraphResponse::class);
        $response->method('getBody')->willReturn(['size' => 1000, 'lastModifiedDateTime' => '2022-01-01T01:00:00Z']);
        $this->graphRequest->method('execute')->willReturn($response);
        $fileAttributes = $this->oneDriveAdapter->lastModified('/sheet.xlsx');
        self::assertInstanceOf(FileAttributes::class, $fileAttributes);
        self::assertSame(1640998800, $fileAttributes->lastModified());

        $this->expectException(UnableToRetrieveMetadata::class);
        $this->makeGraphRequestExecuteMethodThrowException();
        $this->oneDriveAdapter->lastModified('/unknown.xlsx');
    }

    public function testFileSize(): void
    {
        $response = $this->createMock(GraphResponse::class);
        $response->method('getBody')->willReturn(['size' => 1000, 'lastModifiedDateTime' => '2022-01-01T01:00:00Z']);
        $this->graphRequest->method('execute')->willReturn($response);
        $fileAttributes = $this->oneDriveAdapter->fileSize('/sheet.xlsx');
        self::assertInstanceOf(FileAttributes::class, $fileAttributes);
        self::assertSame(1000, $fileAttributes->fileSize());

        $this->expectException(UnableToRetrieveMetadata::class);
        $this->makeGraphRequestExecuteMethodThrowException();
        $this->oneDriveAdapter->fileSize('/unknown.xlsx');
    }

    public function testMimeType(): void
    {
        self::assertSame('video/mp4', $this->oneDriveAdapter->mimeType('/video.mp4')->mimeType());
    }

    public function testSetVisibility(): void
    {
        $this->expectException(UnableToSetVisibility::class);
        $this->oneDriveAdapter->setVisibility('/sheet.xlsx', Visibility::PUBLIC);
    }

    public function testVisibility(): void
    {
        self::assertNull($this->oneDriveAdapter->visibility('/sheet.xlsx')->visibility());
    }

    public function testCreateDirectory(): void
    {
        $response = $this->createMock(GraphResponse::class);
        $response->method('getBody')->willReturn([]);
        $this->graphRequest->method('execute')->willReturn($response);
        $this->graphRequest->method('attachBody')->willReturn($this->graphRequest);
        $this->oneDriveAdapter->createDirectory('/test', new Config());

        $this->expectException(UnableToCreateDirectory::class);
        $this->makeGraphRequestExecuteMethodThrowException();
        $this->oneDriveAdapter->createDirectory('/test2', new Config());
    }

    public function testDelete(): void
    {
        $response = $this->createMock(GraphResponse::class);
        $response->method('getBody')->willReturn([]);
        $this->graphRequest->method('execute')->willReturn($response);
        $this->oneDriveAdapter->delete('/sheet.xlsx');

        $this->expectException(UnableToDeleteFile::class);
        $this->makeGraphRequestExecuteMethodThrowException();
        $this->oneDriveAdapter->delete('/unknown.xlsx');
    }

    public function testDeleteDirectory(): void
    {
        $response = $this->createMock(GraphResponse::class);
        $response->method('getBody')->willReturn([]);
        $this->graphRequest->method('execute')->willReturn($response);
        $this->oneDriveAdapter->deleteDirectory('/test');

        $this->expectException(UnableToDeleteDirectory::class);
        $this->makeGraphRequestExecuteMethodThrowException();
        $this->oneDriveAdapter->deleteDirectory('/unknown');
    }

    public function testWrite(): void
    {
        $response = $this->createMock(GraphResponse::class);
        $response->method('getBody')->willReturn([]);
        $this->graphRequest->method('execute')->willReturn($response);
        $this->graphRequest->method('attachBody')->willReturn($this->graphRequest);
        $this->oneDriveAdapter->write('/test.txt', 'hi', new Config());

        $this->expectException(UnableToWriteFile::class);
        $this->makeGraphRequestExecuteMethodThrowException();
        $this->oneDriveAdapter->write('/text.txt', 'how', new Config());
    }

    public function testWriteStream(): void
    {
        $response = $this->createMock(GraphResponse::class);
        $response->method('getBody')->willReturn([]);
        $this->graphRequest->method('execute')->willReturn($response);
        $this->graphRequest->method('attachBody')->willReturn($this->graphRequest);
        $stream = fopen(__DIR__ . '/bootstrap.php', 'r');
        $this->oneDriveAdapter->writeStream('/test.txt', $stream, new Config());

        $this->expectException(UnableToWriteFile::class);
        $this->makeGraphRequestExecuteMethodThrowException();
        $this->oneDriveAdapter->writeStream('/text.txt', $stream, new Config());
    }

    public function testCopy(): void
    {
        $this->graphRequest->method('executeAsync')->willReturn($this->createMock(Promise::class));
        $this->graphRequest->method('attachBody')->willReturn($this->graphRequest);
        $this->oneDriveAdapter->copy('/test.txt', 'test2.txt', new Config());

        $this->expectException(UnableToCopyFile::class);
        $this->graphRequest->method('executeAsync')->willThrowException(new ClientException('404', $this->createMock(Request::class), $this->createMock(Response::class)));
        $this->oneDriveAdapter->copy('/test.txt', 'test2.txt', new Config());
    }

    public function testMove(): void
    {
        $response = $this->createMock(GraphResponse::class);
        $response->method('getBody')->willReturn([]);
        $this->graphRequest->method('execute')->willReturn($response);
        $this->graphRequest->method('attachBody')->willReturn($this->graphRequest);
        $this->oneDriveAdapter->move('/text.txt', 'text2.txt', new Config());

        $this->expectException(UnableToMoveFile::class);
        $this->makeGraphRequestExecuteMethodThrowException();
        $this->oneDriveAdapter->move('/text.txt', 'text2.txt', new Config());
    }

    public function testRead(): void
    {
        $this->oneDriveAdapter->read('/text.txt');

        $this->expectException(UnableToReadFile::class);
        $this->graphRequest->method('download')->willThrowException(new ClientException('404', $this->createMock(Request::class), $this->createMock(Response::class)));
        $this->oneDriveAdapter->read('/text.txt');
    }

    public function testReadStream(): void
    {
        $this->oneDriveAdapter->readStream('/text.txt');

        $this->expectException(UnableToReadFile::class);
        $this->graphRequest->method('download')->willThrowException(new ClientException('404', $this->createMock(Request::class), $this->createMock(Response::class)));
        $this->oneDriveAdapter->readStream('/text.txt');
    }

    public function testListContents(): void
    {
        $this->expectNotToPerformAssertions();
        $response = $this->createMock(GraphResponse::class);
        $response->method('getBody')->willReturn(['value' => []]);
        $this->graphRequest->method('execute')->willReturn($response);
        $this->oneDriveAdapter->listContents('/test');
    }

    private function makeGraphRequestExecuteMethodThrowException(): void
    {
        $this->graphRequest->method('execute')->willThrowException(new ClientException('404', $this->createMock(Request::class), $this->createMock(Response::class)));
    }
}
