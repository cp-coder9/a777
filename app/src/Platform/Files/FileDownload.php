<?php

declare(strict_types=1);

namespace Architex\Platform\Files;

final readonly class FileDownload
{
    public function __construct(
        public string $originalName,
        public string $mimeType,
        public int $byteSize,
        public string $sha256,
        public string $path,
        public string $contents,
    ) {}
}
