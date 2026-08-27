<?php

declare(strict_types=1);

namespace Architex\Requirements;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use ZipArchive;

final class PackLedgerBuilder
{
    /** @var list<string> */
    private const SUPPORTED_EXTENSIONS = ['md', 'txt', 'json', 'ts', 'tsx'];

    /**
     * @return array{
     *     schemaVersion: int,
     *     sources: list<array{pack: string, relativePath: string, sha256: string}>,
     *     requirements: list<array{id: string, pack: string, source: string, ordinal: int, statement: string, status: string, release: null, implementation: list<mixed>, evidence: list<mixed>}>
     * }
     */
    public function build(string $packsDir): array
    {
        $sources = $this->discoverSources($packsDir);
        $requirements = [];

        foreach ($sources as $source) {
            foreach ($this->extractStatements($source['content'], $source['extension']) as $index => $statement) {
                $requirements[] = [
                    'id' => 'PACK-' . substr(hash('sha256', $source['relativePath'] . "\n" . $statement), 0, 16),
                    'pack' => $source['pack'],
                    'source' => $source['relativePath'],
                    'ordinal' => $index + 1,
                    'statement' => $statement,
                    'status' => 'not_implemented',
                    'release' => null,
                    'implementation' => [],
                    'evidence' => [],
                ];
            }
        }

        usort(
            $requirements,
            static fn (array $left, array $right): int => [$left['source'], $left['ordinal']] <=> [$right['source'], $right['ordinal']],
        );

        return [
            'schemaVersion' => 1,
            'sources' => $this->withoutContent($sources),
            'requirements' => $requirements,
        ];
    }

    /**
     * @param array{schemaVersion: int, sources: list<array{pack: string, relativePath: string, sha256: string}>, requirements: list<array{id: string, pack: string, source: string, ordinal: int, statement: string, status: string, release: null, implementation: list<mixed>, evidence: list<mixed>}>} $ledger
     */
    public function renderMarkdown(array $ledger): string
    {
        $lines = [
            '# Architex Pack Requirements Ledger',
            '',
            'Generated deterministically from the supplied pack files and ZIP entries. Source claims do not change implementation status.',
            '',
            '## Summary',
            '',
            '- Schema version: ' . $ledger['schemaVersion'],
            '- Sources: ' . count($ledger['sources']),
            '- Requirements: ' . count($ledger['requirements']),
            '- Implemented requirements: 0',
            '',
            '## Sources',
            '',
        ];

        foreach ($ledger['sources'] as $source) {
            $lines[] = '- `' . $source['relativePath'] . '`';
            $lines[] = '  - pack: ' . $source['pack'];
            $lines[] = '  - sha256: `' . $source['sha256'] . '`';
        }

        $lines[] = '';
        $lines[] = '## Requirements';
        $lines[] = '';

        foreach ($ledger['requirements'] as $requirement) {
            $lines[] = '### ' . $requirement['id'];
            $lines[] = '';
            $lines[] = '- status: not_implemented';
            $lines[] = '- pack: ' . $requirement['pack'];
            $lines[] = '- source: `' . $requirement['source'] . '`';
            $lines[] = '- ordinal: ' . $requirement['ordinal'];
            $lines[] = '- statement: ' . str_replace("\n", ' ', $requirement['statement']);
            $lines[] = '- release: null';
            $lines[] = '- implementation: []';
            $lines[] = '- evidence: []';
            $lines[] = '';
        }

        return implode("\n", $lines) . "\n";
    }

    /**
     * @param array{schemaVersion: int, sources: list<array{pack: string, relativePath: string, sha256: string}>, requirements: list<array{id: string, pack: string, source: string, ordinal: int, statement: string, status: string, release: null, implementation: list<mixed>, evidence: list<mixed>}>} $ledger
     */
    public function writeMarkdown(string $path, array $ledger): void
    {
        $handle = fopen($path, 'wb');
        if ($handle === false) {
            throw new RuntimeException(sprintf('Unable to write Markdown ledger: %s', $path));
        }

        try {
            fwrite($handle, "# Architex Pack Requirements Ledger\n\n");
            fwrite($handle, "Generated deterministically from the supplied pack files and ZIP entries. Source claims do not change implementation status.\n\n");
            fwrite($handle, "## Summary\n\n");
            fwrite($handle, '- Schema version: ' . $ledger['schemaVersion'] . "\n");
            fwrite($handle, '- Sources: ' . count($ledger['sources']) . "\n");
            fwrite($handle, '- Requirements: ' . count($ledger['requirements']) . "\n");
            fwrite($handle, "- Implemented requirements: 0\n\n## Sources\n\n");

            foreach ($ledger['sources'] as $source) {
                fwrite($handle, '- `' . $source['relativePath'] . "`\n");
                fwrite($handle, '  - pack: ' . $source['pack'] . "\n");
                fwrite($handle, '  - sha256: `' . $source['sha256'] . "`\n");
            }

            fwrite($handle, "\n## Requirements\n\n");
            foreach ($ledger['requirements'] as $index => $requirement) {
                fwrite($handle, '### ' . $requirement['id'] . "\n\n");
                fwrite($handle, "- status: not_implemented\n");
                fwrite($handle, '- pack: ' . $requirement['pack'] . "\n");
                fwrite($handle, '- source: `' . $requirement['source'] . "`\n");
                fwrite($handle, '- ordinal: ' . $requirement['ordinal'] . "\n");
                fwrite($handle, '- statement: ' . str_replace("\n", ' ', $requirement['statement']) . "\n");
                fwrite($handle, "- release: null\n- implementation: []\n- evidence: []\n");
                if ($index !== array_key_last($ledger['requirements'])) {
                    fwrite($handle, "\n");
                }
            }
        } finally {
            fclose($handle);
        }
    }

    /**
     * @return list<array{pack: string, relativePath: string, content: string, extension: string}>
     */
    private function discoverSources(string $packsDir): array
    {
        $basePath = realpath($packsDir);
        if ($basePath === false || !is_dir($basePath)) {
            throw new RuntimeException(sprintf('Packs directory does not exist: %s', $packsDir));
        }

        $sources = [];
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($basePath, FilesystemIterator::SKIP_DOTS),
        );

        foreach ($iterator as $file) {
            if (!$file->isFile()) {
                continue;
            }

            $relativePath = $this->relativePath($basePath, $file->getPathname());
            if ($this->isNodeModulePath($relativePath)) {
                continue;
            }

            $extension = strtolower($file->getExtension());
            if (in_array($extension, self::SUPPORTED_EXTENSIONS, true)) {
                $absolutePath = $file->getPathname();
                $content = file_get_contents($absolutePath);
                if ($content === false) {
                    throw new RuntimeException(sprintf('Unable to read pack source: %s', $relativePath));
                }

                $sources[] = [
                    'pack' => $this->packForPath($relativePath),
                    'relativePath' => $relativePath,
                    'content' => $content,
                    'extension' => $extension,
                ];
            }

            if ($extension === 'zip') {
                foreach ($this->discoverZipSources($file->getPathname(), $relativePath) as $source) {
                    $sources[] = $source;
                }
            }
        }

        usort(
            $sources,
            static fn (array $left, array $right): int => $left['relativePath'] <=> $right['relativePath'],
        );

        return $sources;
    }

    /**
     * @return list<array{pack: string, relativePath: string, content: string, extension: string}>
     */
    private function discoverZipSources(string $archivePath, string $relativeArchivePath): array
    {
        $archiveName = basename($relativeArchivePath);
        $archive = new ZipArchive();
        if ($archive->open($archivePath) !== true) {
            throw new RuntimeException(sprintf('Unable to open pack archive: %s', $archiveName));
        }

        $sources = [];
        try {
            for ($index = 0; $index < $archive->numFiles; $index++) {
                $entry = $archive->statIndex($index);
                if ($entry === false || str_ends_with($entry['name'], '/')) {
                    continue;
                }

                $extension = strtolower(pathinfo($entry['name'], PATHINFO_EXTENSION));
                if ($this->isNodeModulePath($entry['name']) || !in_array($extension, self::SUPPORTED_EXTENSIONS, true)) {
                    continue;
                }

                $content = $archive->getFromIndex($index);
                if ($content === false) {
                    throw new RuntimeException(sprintf('Unable to read %s from pack archive %s', $entry['name'], $archiveName));
                }

                $sources[] = [
                    'pack' => $this->packForZipEntry($relativeArchivePath, $entry['name']),
                    'relativePath' => $relativeArchivePath . '!/' . str_replace('\\', '/', $entry['name']),
                    'content' => $content,
                    'extension' => $extension,
                ];
            }
        } finally {
            $archive->close();
        }

        return $sources;
    }

    /** @return list<string> */
    private function extractStatements(string $content, string $extension): array
    {
        $content = str_replace(["\r\n", "\r"], "\n", preg_replace('/^\xEF\xBB\xBF/', '', $content) ?? $content);

        if ($extension === 'json') {
            return $this->extractJsonStatements($content);
        }

        $statements = [];
        $paragraph = [];
        foreach (explode("\n", $content) as $line) {
            $line = trim(preg_replace('/\s+/', ' ', $line) ?? '');
            if ($line === '') {
                $this->appendParagraph($statements, $paragraph);
                $paragraph = [];
                continue;
            }

            if (preg_match('/^(#{1,6}\s+|[-*+]\s+|\d+[.)]\s+|>\s+)/', $line) === 1) {
                $this->appendParagraph($statements, $paragraph);
                $paragraph = [];
                $statements[] = $line;
                continue;
            }

            if (in_array($extension, ['ts', 'tsx'], true)
                && preg_match('/^(export\s+)?(abstract\s+)?(class|interface|type|enum|function|const|let)\b/', $line) === 1) {
                $this->appendParagraph($statements, $paragraph);
                $paragraph = [];
                $statements[] = $line;
                continue;
            }

            $paragraph[] = $line;
        }
        $this->appendParagraph($statements, $paragraph);

        return array_values(array_unique($statements));
    }

    /** @return list<string> */
    private function extractJsonStatements(string $content): array
    {
        try {
            $decoded = json_decode($content, true, flags: JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return $this->extractStatements($content, 'txt');
        }

        $statements = [];
        $this->collectJsonStatements($decoded, '$', $statements);

        return array_values(array_unique($statements));
    }

    /** @param list<string> $statements */
    private function collectJsonStatements(mixed $value, string $path, array &$statements, ?string $key = null): void
    {
        if ($key !== null && $this->isSemanticJsonKey($key)) {
            $encoded = json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
            $statements[] = $path . ': ' . $encoded;
            return;
        }

        if (is_array($value)) {
            foreach ($value as $key => $child) {
                $childPath = is_int($key) ? $path . '[' . $key . ']' : $path . '.' . $key;
                $this->collectJsonStatements($child, $childPath, $statements, is_int($key) ? null : $key);
            }
        }
    }

    /** @param list<string> $statements @param list<string> $paragraph */
    private function appendParagraph(array &$statements, array $paragraph): void
    {
        if ($paragraph !== []) {
            $statements[] = implode(' ', $paragraph);
        }
    }

    /**
     * @param list<array{pack: string, relativePath: string, content: string, extension: string}> $sources
     * @return list<array{pack: string, relativePath: string, sha256: string}>
     */
    private function withoutContent(array $sources): array
    {
        return array_map(
            static fn (array $source): array => [
                'pack' => $source['pack'],
                'relativePath' => $source['relativePath'],
                'sha256' => hash('sha256', $source['content']),
            ],
            $sources,
        );
    }

    private function relativePath(string $basePath, string $absolutePath): string
    {
        return str_replace('\\', '/', substr($absolutePath, strlen($basePath) + 1));
    }

    private function packForPath(string $relativePath): string
    {
        return explode('/', $relativePath, 2)[0];
    }

    private function packForZipEntry(string $relativeArchivePath, string $entry): string
    {
        $normalisedEntry = str_replace('\\', '/', $entry);
        if (str_contains($normalisedEntry, '/')) {
            return explode('/', $normalisedEntry, 2)[0];
        }

        return pathinfo(basename($relativeArchivePath), PATHINFO_FILENAME);
    }

    private function isNodeModulePath(string $path): bool
    {
        return preg_match('#(?:^|/)node_modules(?:/|$)#', str_replace('\\', '/', $path)) === 1;
    }

    private function isSemanticJsonKey(string $key): bool
    {
        return preg_match(
            '/^(?:requirements?|acceptance_?criteria|roles?|permissions?|actions?|routes?|states?|status(?:es)?|outputs?|guardrails?|modules?|tools?|workflows?|features?|entities|events?|services?|screens?|pages?|apis?|endpoints?)$/i',
            $key,
        ) === 1;
    }
}
