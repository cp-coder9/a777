<?php

declare(strict_types=1);

namespace Architex\Platform\Files;

use RuntimeException;

final readonly class LocalFileStorage
{
    private string $root;

    public function __construct(string $root)
    {
        if (!is_dir($root) && !@mkdir($root, 0700, true) && !is_dir($root)) {
            throw new RuntimeException('Private file storage is unavailable.');
        }
        $resolved = realpath($root);
        if (!is_string($resolved) || !is_writable($resolved)) {
            throw new RuntimeException('Private file storage is unavailable.');
        }
        $this->root = rtrim($resolved, DIRECTORY_SEPARATOR);
    }

    public function put(string $organisationId, string $storageName, string $source, bool $httpUpload): string
    {
        $this->assertIdentifiers($organisationId, $storageName);
        if (!is_file($source) || !is_readable($source)) {
            throw new FileException('invalid_upload', 'The uploaded file is unavailable.');
        }
        if ($httpUpload && !is_uploaded_file($source)) {
            throw new FileException('invalid_upload', 'The uploaded file is invalid.');
        }
        $directory = $this->root . DIRECTORY_SEPARATOR . substr($organisationId, 0, 2) . DIRECTORY_SEPARATOR . $organisationId;
        if (!is_dir($directory) && !@mkdir($directory, 0700, true) && !is_dir($directory)) {
            throw new FileException('storage_unavailable', 'Private file storage is unavailable.');
        }
        $resolvedDirectory = realpath($directory);
        if (!is_string($resolvedDirectory) || !str_starts_with($resolvedDirectory, $this->root . DIRECTORY_SEPARATOR)) {
            throw new FileException('storage_unavailable', 'Private file storage is unavailable.');
        }
        $destination = $resolvedDirectory . DIRECTORY_SEPARATOR . $storageName;
        $temporary = $destination . '.part-' . bin2hex(random_bytes(6));
        $written = $httpUpload ? @move_uploaded_file($source, $temporary) : @copy($source, $temporary);
        if (!$written || !@chmod($temporary, 0600) || !@rename($temporary, $destination)) {
            if (is_file($temporary)) {
                @unlink($temporary);
            }
            throw new FileException('storage_unavailable', 'Private file storage is unavailable.');
        }
        return $destination;
    }

    public function path(string $organisationId, string $storageName): string
    {
        $this->assertIdentifiers($organisationId, $storageName);
        $candidate = $this->root . DIRECTORY_SEPARATOR . substr($organisationId, 0, 2) . DIRECTORY_SEPARATOR . $organisationId . DIRECTORY_SEPARATOR . $storageName;
        $resolved = realpath($candidate);
        if (!is_string($resolved) || !is_file($resolved) || !str_starts_with($resolved, $this->root . DIRECTORY_SEPARATOR)) {
            throw new FileException('not_found', 'File not found.');
        }
        return $resolved;
    }

    public function delete(string $organisationId, string $storageName): void
    {
        try {
            $path = $this->path($organisationId, $storageName);
            @unlink($path);
        } catch (FileException) {
        }
    }

    private function assertIdentifiers(string $organisationId, string $storageName): void
    {
        if (preg_match('/\A[a-f0-9]{32}\z/', $organisationId) !== 1 || preg_match('/\A[a-f0-9]{48}\z/', $storageName) !== 1) {
            throw new FileException('not_found', 'File not found.');
        }
    }
}
