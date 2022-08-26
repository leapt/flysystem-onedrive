<?php

declare(strict_types=1);

namespace Leapt\FlysystemOneDrive;

class OneDriveMetadata
{
    public function __construct(
        public readonly int $fileSize,
        public readonly \DateTimeImmutable $lastModified,
    ) {
    }
}
