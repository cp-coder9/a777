<?php

declare(strict_types=1);

namespace Architex\Platform\Files;

final readonly class StoredFile
{
    public function __construct(
        public string $id,
        public string $projectId,
        public string $originalName,
        public string $storageName,
        public string $mimeType,
        public int $byteSize,
        public string $sha256,
    ) {}
}
