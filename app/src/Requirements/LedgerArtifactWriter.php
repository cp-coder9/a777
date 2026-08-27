<?php

declare(strict_types=1);

namespace Architex\Requirements;

use Closure;
use RuntimeException;

final class LedgerArtifactWriter
{
    /** @var null|Closure(resource,string):int|false */
    private readonly ?Closure $writeChunk;
    /** @var null|Closure(string,string):bool */
    private readonly ?Closure $moveFile;

    /** @param null|callable(resource,string):int|false $writeChunk */
    public function __construct(?callable $writeChunk = null, ?callable $moveFile = null)
    {
        $this->writeChunk = $writeChunk === null ? null : Closure::fromCallable($writeChunk);
        $this->moveFile = $moveFile === null ? null : Closure::fromCallable($moveFile);
    }

    /** @param array{schemaVersion: int, sources: list<array<string, mixed>>, requirements: list<array<string, mixed>>} $ledger */
    public function write(string $jsonPath, string $markdownPath, array $ledger, PackLedgerBuilder $builder): void
    {
        $this->ensureDirectory(dirname($jsonPath));
        $this->ensureDirectory(dirname($markdownPath));
        $jsonTemporary = $this->temporaryPath($jsonPath);
        $markdownTemporary = $this->temporaryPath($markdownPath);

        try {
            $this->writeFile($jsonTemporary, $builder->jsonChunks($ledger));
            $this->writeFile($markdownTemporary, $builder->markdownChunks($ledger));
            $this->replacePair($jsonTemporary, $jsonPath, $markdownTemporary, $markdownPath);
        } finally {
            $this->removeIfPresent($jsonTemporary);
            $this->removeIfPresent($markdownTemporary);
        }
    }

    /** @param iterable<string> $chunks */
    private function writeFile(string $path, iterable $chunks): void
    {
        $handle = fopen($path, 'xb');
        if ($handle === false) throw new RuntimeException(sprintf('Unable to create temporary ledger artifact: %s', $path));
        try {
            foreach ($chunks as $chunk) $this->writeAll($handle, $chunk);
            if (!fflush($handle)) throw new RuntimeException(sprintf('Unable to flush temporary ledger artifact: %s', $path));
        } finally {
            if (!fclose($handle)) throw new RuntimeException(sprintf('Unable to close temporary ledger artifact: %s', $path));
        }
    }

    /** @param resource $handle */
    private function writeAll($handle, string $chunk): void
    {
        $offset = 0;
        while ($offset < strlen($chunk)) {
            $remaining = substr($chunk, $offset);
            $written = $this->writeChunk === null ? fwrite($handle, $remaining) : ($this->writeChunk)($handle, $remaining);
            if ($written === false || $written <= 0 || $written > strlen($remaining)) throw new RuntimeException('Short write while creating ledger artifact.');
            $offset += $written;
        }
    }

    private function replacePair(string $jsonTemporary, string $jsonPath, string $markdownTemporary, string $markdownPath): void
    {
        $token = bin2hex(random_bytes(8));
        $jsonBackup = $jsonPath . '.bak-' . $token;
        $markdownBackup = $markdownPath . '.bak-' . $token;
        $jsonBackedUp = false; $markdownBackedUp = false;
        $jsonInstalled = false; $markdownInstalled = false;
        $keepJsonBackup = false; $keepMarkdownBackup = false;
        try {
            if (file_exists($jsonPath)) { $this->move($jsonPath, $jsonBackup); $jsonBackedUp = true; }
            if (file_exists($markdownPath)) { $this->move($markdownPath, $markdownBackup); $markdownBackedUp = true; }
            $this->move($jsonTemporary, $jsonPath); $jsonInstalled = true;
            $this->move($markdownTemporary, $markdownPath); $markdownInstalled = true;
        } catch (\Throwable $error) {
            if ($jsonInstalled) $this->removeIfPresent($jsonPath);
            if ($markdownInstalled) $this->removeIfPresent($markdownPath);
            if ($jsonBackedUp) {
                try { $this->move($jsonBackup, $jsonPath); } catch (\Throwable) { $keepJsonBackup = true; }
            }
            if ($markdownBackedUp) {
                try { $this->move($markdownBackup, $markdownPath); } catch (\Throwable) { $keepMarkdownBackup = true; }
            }
            throw $error;
        } finally {
            if (!$keepJsonBackup) $this->removeIfPresent($jsonBackup);
            if (!$keepMarkdownBackup) $this->removeIfPresent($markdownBackup);
        }
    }

    private function ensureDirectory(string $directory): void { if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) throw new RuntimeException(sprintf('Unable to create output directory: %s', $directory)); }
    private function temporaryPath(string $destination): string { $temporary = tempnam(dirname($destination), '.' . basename($destination) . '.'); if ($temporary === false || !unlink($temporary)) throw new RuntimeException(sprintf('Unable to allocate temporary ledger artifact for: %s', $destination)); return $temporary; }
    private function move(string $from, string $to): void { $moved = $this->moveFile === null ? rename($from, $to) : ($this->moveFile)($from, $to); if (!$moved) throw new RuntimeException(sprintf('Unable to replace ledger artifact: %s', $to)); }
    private function removeIfPresent(string $path): void { if (file_exists($path) && !unlink($path)) throw new RuntimeException(sprintf('Unable to remove temporary ledger artifact: %s', $path)); }
}
