<?php

declare(strict_types=1);

namespace Architex\Http;

use InvalidArgumentException;

final readonly class UploadedFile
{
    private function __construct(
        public string $clientName,
        public string $temporaryPath,
        public int $error,
        public int $size,
        public bool $httpUpload,
    ) {}

    /** @param array<string,mixed> $upload */
    public static function fromPhpUpload(array $upload): self
    {
        if (!is_string($upload['name'] ?? null) || !is_string($upload['tmp_name'] ?? null) || !is_int($upload['error'] ?? null) || !is_int($upload['size'] ?? null)) {
            throw new InvalidArgumentException('The uploaded file metadata is invalid.');
        }
        return new self($upload['name'], $upload['tmp_name'], $upload['error'], $upload['size'], true);
    }

    public static function forTesting(string $clientName, string $temporaryPath, int $error = UPLOAD_ERR_OK): self
    {
        $size = is_file($temporaryPath) ? filesize($temporaryPath) : false;
        return new self($clientName, $temporaryPath, $error, is_int($size) ? $size : 0, false);
    }
}
