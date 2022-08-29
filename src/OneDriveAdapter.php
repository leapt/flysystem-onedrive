<?php

declare(strict_types=1);

namespace Leapt\FlysystemOneDrive;

use ArrayObject;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Psr7\Utils;
use League\Flysystem\Config;
use League\Flysystem\DirectoryAttributes;
use League\Flysystem\FileAttributes;
use League\Flysystem\FilesystemAdapter;
use League\Flysystem\PathPrefixer;
use League\Flysystem\StorageAttributes;
use League\Flysystem\UnableToCopyFile;
use League\Flysystem\UnableToCreateDirectory;
use League\Flysystem\UnableToDeleteDirectory;
use League\Flysystem\UnableToDeleteFile;
use League\Flysystem\UnableToMoveFile;
use League\Flysystem\UnableToReadFile;
use League\Flysystem\UnableToRetrieveMetadata;
use League\Flysystem\UnableToSetVisibility;
use League\Flysystem\UnableToWriteFile;
use League\MimeTypeDetection\FinfoMimeTypeDetector;
use Microsoft\Graph\Graph;
use Microsoft\Graph\Model;

class OneDriveAdapter implements FilesystemAdapter
{
    private PathPrefixer $prefixer;
    private FinfoMimeTypeDetector $mimeTypeDetector;

    public function __construct(
        private readonly Graph $graph,
        string $basePath = '/me/drive/root',
        string $subdirectory = '',
        private readonly bool $usePath = true,
    ) {
        $this->prefixer = new PathPrefixer($basePath . $subdirectory . ($this->usePath ? ':' : ''));
        $this->mimeTypeDetector = new FinfoMimeTypeDetector();
    }

    public function fileExists(string $path): bool
    {
        try {
            $this->graph->createRequest('GET', $this->applyPathPrefix($path))->execute();

            return true;
        } catch (ClientException) {
            return false;
        }
    }

    public function directoryExists(string $path): bool
    {
        return $this->fileExists($path);
    }

    public function lastModified(string $path): FileAttributes
    {
        $location = $this->applyPathPrefix($path);
        try {
            $metadata = $this->getMetadata($location);
        } catch (ClientException $e) {
            throw UnableToRetrieveMetadata::lastModified($location, $e->getMessage());
        }

        return new FileAttributes(
            $path,
            lastModified: $metadata->lastModified->getTimestamp(),
        );
    }

    public function fileSize(string $path): FileAttributes
    {
        $location = $this->applyPathPrefix($path);
        try {
            $metadata = $this->getMetadata($location);
        } catch (ClientException $e) {
            throw UnableToRetrieveMetadata::fileSize($location, $e->getMessage());
        }

        return new FileAttributes(
            $path,
            fileSize: $metadata->fileSize,
        );
    }

    public function mimeType(string $path): FileAttributes
    {
        return new FileAttributes(
            $path,
            mimeType: $this->mimeTypeDetector->detectMimeTypeFromPath($path),
        );
    }

    public function setVisibility(string $path, string $visibility): void
    {
        throw UnableToSetVisibility::atLocation($path, 'Adapter does not support visibility controls.');
    }

    public function visibility(string $path): FileAttributes
    {
        // Noop
        return new FileAttributes($path);
    }

    public function createDirectory(string $path, Config $config): void
    {
        $patch = explode('/', $path);
        $sliced = implode('/', \array_slice($patch, 0, -1));

        if (empty($sliced) && $this->usePath) {
            $endpoint = str_replace(':/', '', $this->applyPathPrefix('') . '//children');
        } else {
            $endpoint = $this->applyPathPrefix($sliced) . ($this->usePath ? ':' : '') . '/children';
        }

        try {
            $this->graph->createRequest('POST', $endpoint)->attachBody([
                'name'   => end($patch),
                'folder' => new ArrayObject(),
            ])->execute();
        } catch (ClientException $e) {
            throw UnableToCreateDirectory::dueToFailure($path, $e);
        }
    }

    public function delete(string $path): void
    {
        try {
            $endpoint = $this->applyPathPrefix($path);
            $this->graph->createRequest('DELETE', $endpoint)->execute();
        } catch (ClientException $e) {
            throw UnableToDeleteFile::atLocation($path, $e->getMessage());
        }
    }

    public function deleteDirectory(string $path): void
    {
        try {
            $endpoint = $this->applyPathPrefix($path);
            $this->graph->createRequest('DELETE', $endpoint)->execute();
        } catch (ClientException $e) {
            throw UnableToDeleteDirectory::atLocation($path, $e->getMessage());
        }
    }

    public function write(string $path, string $contents, Config $config): void
    {
        $this->upload($path, $contents);
    }

    /**
     * @param resource $contents
     */
    public function writeStream(string $path, $contents, Config $config): void
    {
        $this->upload($path, $contents);
    }

    public function copy(string $source, string $destination, Config $config): void
    {
        $endpoint = $this->applyPathPrefix($source);

        $patch = explode('/', $destination);
        $sliced = implode('/', \array_slice($patch, 0, -1));

        try {
            $promise = $this->graph->createRequest('POST', $endpoint . ($this->usePath ? ':' : '') . '/copy')
                ->attachBody([
                    'name'            => end($patch),
                    'parentReference' => [
                        'path' => $this->applyPathPrefix('') . (empty($sliced) ? '' : rtrim($sliced, '/') . '/'),
                    ],
                ])
                ->executeAsync();
            $promise->wait();
        } catch (ClientException $e) {
            throw UnableToCopyFile::fromLocationTo($source, $destination, $e);
        }
    }

    public function move(string $source, string $destination, Config $config): void
    {
        $endpoint = $this->applyPathPrefix($source);

        $patch = explode('/', $destination);
        $sliced = implode('/', \array_slice($patch, 0, -1));

        try {
            $this->graph->createRequest('PATCH', $endpoint)
                ->attachBody([
                    'name'            => end($patch),
                    'parentReference' => [
                        'path' => $this->applyPathPrefix('') . (empty($sliced) ? '' : rtrim($sliced, '/') . '/'),
                    ],
                ])
                ->execute();
        } catch (ClientException) {
            try {
                $this->graph->createRequest('PATCH', $endpoint)
                    ->attachBody([
                        'name'            => end($patch),
                        'parentReference' => [
                            'path' => substr($this->applyPathPrefix(''), 3) . (empty($sliced) ? '' : rtrim($sliced, '/') . '/'),
                        ],
                    ])
                    ->execute();
            } catch (ClientException $e) {
                throw UnableToMoveFile::fromLocationTo($source, $destination, $e);
            }
        }
    }

    public function read(string $path): string
    {
        $object = $this->readStream($path);

        $contents = stream_get_contents($object);
        fclose($object);
        unset($object);

        return $contents;
    }

    public function readStream(string $path)
    {
        $path = $this->applyPathPrefix($path);

        try {
            $file = tempnam(sys_get_temp_dir(), 'onedrive');

            $this->graph->createRequest('GET', $path . ($this->usePath ? ':' : '') . '/content')
                ->download($file);

            $stream = fopen($file, 'r');
            unlink($file);

            return $stream;
        } catch (\Exception $e) {
            throw UnableToReadFile::fromLocation($path, $e->getMessage());
        }
    }

    public function listContents(string $path = '', bool $deep = false): iterable
    {
        if ('' === $path && $this->usePath) {
            $endpoint = str_replace(':/', '', $this->applyPathPrefix('') . '//children');
        } else {
            $endpoint = $this->applyPathPrefix($path) . ($this->usePath ? ':' : '') . '/children';
        }

        $response = $this->graph->createRequest('GET', $endpoint)->execute();
        $items = $response->getBody()['value'];
        $results = [];

        foreach ($items as $item) {
            $results[] = $this->normalizeResponse($item, $this->applyPathPrefix($path));

            if ($deep && isset($item['folder'])) {
                $results = array_merge($results, $this->listContents($path . '/' . $item['name'], true));
            }
        }

        return $results;
    }

    private function applyPathPrefix(string $path): string
    {
        return '/' . trim($this->prefixer->prefixPath($path), '/');
    }

    private function upload(string $path, $contents)
    {
        $filename = basename($path);
        $path = $this->applyPathPrefix($path);

        try {
            $contents = Utils::streamFor($contents);

            $file = $contents->getMetadata('uri');
            $fileSize = $contents->getSize();

            if (4000000 < $fileSize) {
                $uploadSession = $this->graph->createRequest('POST', $path . ($this->usePath ? ':' : '') . '/createUploadSession')
                    ->addHeaders(['Content-Type' => 'application/json'])
                    ->attachBody([
                        'item' => [
                            '@microsoft.graph.conflictBehavior' => 'rename',
                            'name'                              => $filename,
                        ],
                    ])
                    ->setReturnType(Model\UploadSession::class)
                    ->execute();

                $handle = fopen($file, 'r');
                $fileNbByte = $fileSize - 1;
                $chunkSize = 1024 * 1024 * 60;
                $fgetsLength = $chunkSize + 1;
                $start = 0;
                while (!feof($handle)) {
                    $bytes = fread($handle, $fgetsLength);
                    $end = $chunkSize + $start;
                    if ($end > $fileNbByte) {
                        $end = $fileNbByte;
                    }

                    $stream = Utils::streamFor($bytes);

                    $this->graph->createRequest('PUT', $uploadSession->getUploadUrl())
                        ->addHeaders([
                            'Content-Length' => ($end + 1) - $start,
                            'Content-Range'  => 'bytes ' . $start . '-' . $end . '/' . $fileSize,
                        ])
                        ->setReturnType(Model\UploadSession::class)
                        ->attachBody($stream)
                        ->setTimeout('0')
                        ->execute();

                    $start = $end + 1;
                }
            }

            $this->graph->createRequest('PUT', $path . ($this->usePath ? ':' : '') . '/content')
                ->attachBody($contents)
                ->execute();
        } catch (ClientException $e) {
            throw UnableToWriteFile::atLocation($path, $e->getMessage());
        }
    }

    private function getMetadata(string $path): OneDriveMetadata
    {
        $response = $this->graph->createRequest('GET', $path)->execute()->getBody();

        return new OneDriveMetadata(
            $response['size'],
            new \DateTimeImmutable($response['lastModifiedDateTime']),
        );
    }

    private function normalizeResponse(array $response, string $path): StorageAttributes
    {
        $path = str_replace('root/children', 'root:/children', $path);
        $path = trim($this->prefixer->stripDirectoryPrefix($path), '/');
        $path = empty($path) ? $response['name'] : $path . '/' . $response['name'];

        if (isset($response['folder'])) {
            return new DirectoryAttributes(
                $path,
                lastModified: strtotime($response['lastModifiedDateTime']),
            );
        }

        return new FileAttributes(
            $path,
            $response['size'] ?? null,
            null,
            strtotime($response['lastModifiedDateTime']),
            $this->mimeTypeDetector->detectMimeTypeFromPath($path),
        );
    }
}
