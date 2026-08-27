<?php

declare(strict_types=1);

namespace Architex\Requirements;

use Closure;
use InvalidArgumentException;
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
    public function write(string $jsonPath, string $markdownPath, array $ledger, PackLedgerBuilder $builder): string
    {
        $jsonPath = $this->canonicalDestination($jsonPath);
        $markdownPath = $this->canonicalDestination($markdownPath);
        if (dirname($jsonPath) !== dirname($markdownPath)) {
            throw new InvalidArgumentException('JSON and Markdown outputs must share a directory for portable publication.');
        }
        $markerPath = $this->canonicalDestination($jsonPath . '.generation.json');
        if ($jsonPath === $markdownPath || $jsonPath === $markerPath || $markdownPath === $markerPath) {
            throw new InvalidArgumentException('JSON, Markdown, and generation marker output destinations must differ.');
        }
        $jsonTemporary = $this->temporaryPath($jsonPath);
        $markdownTemporary = $this->temporaryPath($markdownPath);

        try {
            $this->writeFile($jsonTemporary, $builder->jsonChunks($ledger));
            $this->writeFile($markdownTemporary, $builder->markdownChunks($ledger));
            $this->replacePair($jsonTemporary, $jsonPath, $markdownTemporary, $markdownPath);
            $markerTemporary = null;
            try {
                $markerTemporary = $this->temporaryPath($markerPath);
                $this->writeFile($markerTemporary, [$this->markerPayload($jsonPath, $markdownPath)]);
                $this->replaceSingle($markerTemporary, $markerPath);
            } finally {
                if ($markerTemporary !== null) $this->removeIfPresent($markerTemporary);
            }
        } finally {
            $this->removeIfPresent($jsonTemporary);
            $this->removeIfPresent($markdownTemporary);
        }

        return $markerPath;
    }

    public function isPublishedGenerationValid(string $markerPath): bool
    {
        try {
            if (!is_file($markerPath)) return false;
            $canonicalMarkerPath = realpath($markerPath);
            if ($canonicalMarkerPath === false) return false;
            $markerDirectory = dirname($canonicalMarkerPath);
            $marker = json_decode((string) file_get_contents($markerPath), true, flags: JSON_THROW_ON_ERROR);
            if (!is_array($marker) || !isset($marker['generationId'], $marker['json'], $marker['markdown'])) return false;
            $artifactPaths = [];
            $artifactHashes = [];
            foreach (['json', 'markdown'] as $type) {
                $artifact = $marker[$type];
                if (!is_array($artifact) || !isset($artifact['path'], $artifact['sha256'])) return false;
                $canonicalArtifactPath = $this->resolveMarkerArtifact($markerDirectory, $artifact['path']);
                if ($canonicalArtifactPath === false || $canonicalArtifactPath === $canonicalMarkerPath) return false;
                $artifactPaths[] = $canonicalArtifactPath;
                $actualHash = hash_file('sha256', $canonicalArtifactPath);
                if ($actualHash === false || !hash_equals($artifact['sha256'], $actualHash)) return false;
                $artifactHashes[$type] = $actualHash;
            }
            $expectedJsonPath = substr(basename($canonicalMarkerPath), 0, -strlen('.generation.json'));
            return count(array_unique($artifactPaths)) === 2
                && $marker['json']['path'] === $expectedJsonPath
                && hash_equals('ledger-' . substr(hash('sha256', $artifactHashes['json'] . "\n" . $artifactHashes['markdown']), 0, 16), $marker['generationId']);
        } catch (\Throwable) {
            return false;
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

    private function replaceSingle(string $temporary, string $destination): void
    {
        $backup = $destination . '.bak-' . bin2hex(random_bytes(8));
        $backedUp = false;
        try {
            if (file_exists($destination)) { $this->move($destination, $backup); $backedUp = true; }
            $this->move($temporary, $destination);
        } catch (\Throwable $error) {
            if ($backedUp) {
                try { $this->move($backup, $destination); } catch (\Throwable) { throw new RuntimeException('Unable to restore generation marker after publication failure.', previous: $error); }
            }
            throw $error;
        } finally {
            $this->removeIfPresent($backup);
        }
    }

    private function markerPayload(string $jsonPath, string $markdownPath): string
    {
        $jsonHash = hash_file('sha256', $jsonPath);
        $markdownHash = hash_file('sha256', $markdownPath);
        if ($jsonHash === false || $markdownHash === false) throw new RuntimeException('Unable to hash published ledger artifacts.');
        return json_encode([
            'generationId' => 'ledger-' . substr(hash('sha256', $jsonHash . "\n" . $markdownHash), 0, 16),
            'json' => ['path' => $this->portableName($jsonPath), 'sha256' => $jsonHash],
            'markdown' => ['path' => $this->portableName($markdownPath), 'sha256' => $markdownHash],
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n";
    }

    private function canonicalDestination(string $path): string { $directory = dirname($path); $this->ensureDirectory($directory); $canonicalDirectory = realpath($directory); if ($canonicalDirectory === false) throw new RuntimeException(sprintf('Unable to canonicalize output directory: %s', $directory)); return $canonicalDirectory . DIRECTORY_SEPARATOR . basename($path); }
    private function portableName(string $path): string { return str_replace('\\', '/', basename($path)); }
    private function resolveMarkerArtifact(string $markerDirectory, mixed $path): string|false { if (!is_string($path) || $path === '') return false; $normalised = str_replace('\\', '/', $path); if (str_contains($normalised, '/') || $normalised === '.' || $normalised === '..' || basename($normalised) !== $normalised) return false; $candidate = $markerDirectory . DIRECTORY_SEPARATOR . $normalised; $resolved = realpath($candidate); return $resolved !== false && is_file($resolved) && dirname($resolved) === $markerDirectory ? $resolved : false; }
    private function ensureDirectory(string $directory): void { if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) throw new RuntimeException(sprintf('Unable to create output directory: %s', $directory)); }
    private function temporaryPath(string $destination): string { $temporary = tempnam(dirname($destination), '.' . basename($destination) . '.'); if ($temporary === false || !unlink($temporary)) throw new RuntimeException(sprintf('Unable to allocate temporary ledger artifact for: %s', $destination)); return $temporary; }
    private function move(string $from, string $to): void { $moved = $this->moveFile === null ? rename($from, $to) : ($this->moveFile)($from, $to); if (!$moved) throw new RuntimeException(sprintf('Unable to replace ledger artifact: %s', $to)); }
    private function removeIfPresent(string $path): void { if (file_exists($path) && !unlink($path)) throw new RuntimeException(sprintf('Unable to remove temporary ledger artifact: %s', $path)); }
}
