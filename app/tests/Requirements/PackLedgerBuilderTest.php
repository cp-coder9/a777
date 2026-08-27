<?php

declare(strict_types=1);

namespace Architex\Tests\Requirements;

use Architex\Requirements\PackLedgerBuilder;
use Architex\Requirements\PackLedgerLimits;
use Architex\Requirements\LedgerArtifactWriter;
use PHPUnit\Framework\TestCase;

final class PackLedgerBuilderTest extends TestCase
{
    public function testEveryRequirementStartsNotImplementedAndDuplicatePackTenIsRetained(): void
    {
        $ledger = (new PackLedgerBuilder())->build(dirname(__DIR__, 3) . '/packs');

        self::assertNotEmpty($ledger['sources']);
        self::assertContains('10. architex-site-execution-field-control-pack', array_column($ledger['sources'], 'pack'));
        self::assertNotEmpty($ledger['requirements']);
        self::assertSame(['not_implemented'], array_values(array_unique(array_column($ledger['requirements'], 'status'))));
        $firstDigest = hash('sha256', serialize($ledger));
        unset($ledger);
        self::assertSame($firstDigest, hash('sha256', serialize((new PackLedgerBuilder())->build(dirname(__DIR__, 3) . '/packs'))));
    }

    public function testNodeModuleContentIsExcludedFromDirectoryAndArchiveSources(): void
    {
        $packs = sys_get_temp_dir() . '/architex-pack-ledger-' . bin2hex(random_bytes(8));
        mkdir($packs . '/node_modules', 0777, true);
        file_put_contents($packs . '/included.md', '# Included requirement');
        file_put_contents($packs . '/node_modules/ignored.md', '# Ignored requirement');

        $archive = new \ZipArchive();
        self::assertTrue($archive->open($packs . '/archive.zip', \ZipArchive::CREATE) === true);
        $archive->addFromString('nested-pack/included.txt', 'Included archive requirement');
        $archive->addFromString('node_modules/ignored.txt', 'Ignored archive requirement');
        $archive->close();

        try {
            $ledger = (new PackLedgerBuilder())->build($packs);
            $paths = array_column($ledger['sources'], 'relativePath');

            self::assertContains('included.md', $paths);
            self::assertContains('archive.zip!/nested-pack/included.txt', $paths);
            $archiveSource = array_values(array_filter(
                $ledger['sources'],
                static fn (array $source): bool => $source['relativePath'] === 'archive.zip!/nested-pack/included.txt',
            ));
            self::assertSame('nested-pack', $archiveSource[0]['pack']);
            self::assertSame([], array_values(array_filter($paths, static fn (string $path): bool => str_contains($path, 'node_modules/'))));
        } finally {
            $this->deleteDirectory($packs);
        }
    }

    public function testRepeatedStatementsRetainDistinctOccurrenceStableIds(): void
    {
        $packs = $this->temporaryPacks(['requirements.md' => "# Repeated\n# Repeated\n"]);

        try {
            $requirements = (new PackLedgerBuilder())->build($packs)['requirements'];

            self::assertCount(2, $requirements);
            self::assertSame(['# Repeated', '# Repeated'], array_column($requirements, 'statement'));
            self::assertNotSame($requirements[0]['id'], $requirements[1]['id']);
            self::assertSame([1, 2], array_column($requirements, 'ordinal'));
        } finally {
            $this->deleteDirectory($packs);
        }
    }

    public function testConfiguredSourceAndRequirementLimitsRejectBeforeLedgerGrowth(): void
    {
        $packs = $this->temporaryPacks(['requirements.md' => "# One\n# Two\n"]);

        try {
            $this->expectException(\RuntimeException::class);
            $this->expectExceptionMessage('Maximum requirement count exceeded');
            (new PackLedgerBuilder(new PackLedgerLimits(maximumRequirements: 1)))->build($packs);
        } finally {
            $this->deleteDirectory($packs);
        }
    }

    public function testConfiguredInputByteLimitsRejectBeforeReadingSources(): void
    {
        $packs = $this->temporaryPacks(['one.md' => '# one', 'two.md' => '# two']);

        try {
            $this->expectException(\RuntimeException::class);
            $this->expectExceptionMessage('Maximum total input bytes exceeded');
            (new PackLedgerBuilder(new PackLedgerLimits(maximumTotalInputBytes: 5)))->build($packs);
        } finally {
            $this->deleteDirectory($packs);
        }
    }

    public function testConfiguredMaximumSourcesRejectsEmptyFilesBeforeAccumulation(): void
    {
        $packs = $this->temporaryPacks(['one.md' => '', 'two.md' => '']);

        try {
            $this->expectException(\RuntimeException::class);
            $this->expectExceptionMessage('Maximum source count exceeded');
            (new PackLedgerBuilder(new PackLedgerLimits(maximumSources: 1)))->build($packs);
        } finally {
            $this->deleteDirectory($packs);
        }
    }

    public function testConfiguredZipEntryAndCompressionLimitsRejectArchiveMetadata(): void
    {
        $packs = $this->temporaryPacks([]);
        $archive = new \ZipArchive();
        self::assertTrue($archive->open($packs . '/pack.zip', \ZipArchive::CREATE) === true);
        $archive->addFromString('one.md', str_repeat('a', 200));
        $archive->addFromString('two.md', '# two');
        $archive->close();

        try {
            $this->expectException(\RuntimeException::class);
            $this->expectExceptionMessage('Maximum ZIP entry count exceeded');
            (new PackLedgerBuilder(new PackLedgerLimits(maximumZipEntries: 1)))->build($packs);
        } finally {
            $this->deleteDirectory($packs);
        }
    }

    public function testConfiguredZipEntryBytesAndCompressionRatioRejectBeforeReading(): void
    {
        $packs = $this->temporaryPacks([]);
        $archive = new \ZipArchive();
        self::assertTrue($archive->open($packs . '/pack.zip', \ZipArchive::CREATE) === true);
        $archive->addFromString('one.md', str_repeat('a', 200));
        $archive->close();

        try {
            $this->expectException(\RuntimeException::class);
            $this->expectExceptionMessage('Maximum ZIP entry uncompressed bytes exceeded');
            (new PackLedgerBuilder(new PackLedgerLimits(maximumZipEntryBytes: 100)))->build($packs);
        } finally {
            $this->deleteDirectory($packs);
        }
    }

    public function testConfiguredCompressionRatioRejectsCompressibleZipEntry(): void
    {
        $packs = $this->temporaryPacks([]);
        $archive = new \ZipArchive();
        self::assertTrue($archive->open($packs . '/pack.zip', \ZipArchive::CREATE) === true);
        $archive->addFromString('one.md', str_repeat('a', 2_000));
        $archive->close();

        try {
            $this->expectException(\RuntimeException::class);
            $this->expectExceptionMessage('Maximum ZIP compression ratio exceeded');
            (new PackLedgerBuilder(new PackLedgerLimits(maximumCompressionRatio: 1)))->build($packs);
        } finally {
            $this->deleteDirectory($packs);
        }
    }

    public function testAtomicWriterPreservesExistingArtifactsWhenAWriteIsShort(): void
    {
        $directory = sys_get_temp_dir() . '/architex-ledger-output-' . bin2hex(random_bytes(8));
        mkdir($directory, 0777, true);
        $json = $directory . '/ledger.json';
        $markdown = $directory . '/ledger.md';
        file_put_contents($json, 'previous-json');
        file_put_contents($markdown, 'previous-markdown');

        try {
            $writer = new LedgerArtifactWriter(static function ($handle, string $data): int|false {
                fwrite($handle, $data);
                return max(0, strlen($data) - 1);
            });

            $this->expectException(\RuntimeException::class);
            $this->expectExceptionMessage('Short write');
            $writer->write($json, $markdown, ['schemaVersion' => 1, 'sources' => [], 'requirements' => []], new PackLedgerBuilder());
        } finally {
            self::assertSame('previous-json', file_get_contents($json));
            self::assertSame('previous-markdown', file_get_contents($markdown));
            $this->deleteDirectory($directory);
        }
    }

    public function testAtomicWriterRestoresBothArtifactsWhenSecondBackupMoveFails(): void
    {
        $directory = sys_get_temp_dir() . '/architex-ledger-output-' . bin2hex(random_bytes(8));
        mkdir($directory, 0777, true);
        $json = $directory . '/ledger.json';
        $markdown = $directory . '/ledger.md';
        file_put_contents($json, 'previous-json');
        file_put_contents($markdown, 'previous-markdown');

        try {
            $writer = new LedgerArtifactWriter(
                moveFile: static function (string $from, string $to) use ($markdown): bool {
                    return str_starts_with(str_replace('\\', '/', $to), str_replace('\\', '/', $markdown) . '.bak-') ? false : rename($from, $to);
                },
            );

            $this->expectException(\RuntimeException::class);
            $writer->write($json, $markdown, ['schemaVersion' => 1, 'sources' => [], 'requirements' => []], new PackLedgerBuilder());
        } finally {
            self::assertSame('previous-json', file_get_contents($json));
            self::assertSame('previous-markdown', file_get_contents($markdown));
            $this->deleteDirectory($directory);
        }
    }

    public function testAtomicWriterPublishesVerifiableMarkerAfterBothArtifacts(): void
    {
        $directory = $this->temporaryPacks([]);
        $json = $directory . '/ledger.json';
        $markdown = $directory . '/ledger.md';

        try {
            $writer = new LedgerArtifactWriter();
            $marker = $writer->write($json, $markdown, ['schemaVersion' => 1, 'sources' => [], 'requirements' => []], new PackLedgerBuilder());

            self::assertTrue($writer->isPublishedGenerationValid($marker));
            $published = json_decode((string) file_get_contents($marker), true, flags: JSON_THROW_ON_ERROR);
            self::assertSame('ledger.json', $published['json']['path']);
            self::assertSame('ledger.md', $published['markdown']['path']);
        } finally {
            $this->deleteDirectory($directory);
        }
    }

    public function testSecondFinalInstallationFailureLeavesNoValidMixedGeneration(): void
    {
        $directory = $this->temporaryPacks([]);
        $json = $directory . '/ledger.json';
        $markdown = $directory . '/ledger.md';

        try {
            $canonicalMarkdown = realpath($directory) . DIRECTORY_SEPARATOR . 'ledger.md';
            $writer = new LedgerArtifactWriter(moveFile: static fn (string $from, string $to): bool => $to === $canonicalMarkdown ? false : rename($from, $to));
            try {
                $writer->write($json, $markdown, ['schemaVersion' => 1, 'sources' => [], 'requirements' => []], new PackLedgerBuilder());
                self::fail('Expected second final installation to fail.');
            } catch (\RuntimeException) {
                self::assertFalse($writer->isPublishedGenerationValid($json . '.generation.json'));
            }
        } finally {
            $this->deleteDirectory($directory);
        }
    }

    public function testMarkerReplacementFailureLeavesInstalledPairInvalid(): void
    {
        $directory = $this->temporaryPacks([]);
        $json = $directory . '/ledger.json';
        $markdown = $directory . '/ledger.md';

        try {
            $baseline = new LedgerArtifactWriter();
            $marker = $baseline->write($json, $markdown, ['schemaVersion' => 1, 'sources' => [], 'requirements' => []], new PackLedgerBuilder());
            $writer = new LedgerArtifactWriter(moveFile: static fn (string $from, string $to): bool => $to === $marker ? false : rename($from, $to));
            try {
                $writer->write($json, $markdown, ['schemaVersion' => 1, 'sources' => [['pack' => 'new', 'relativePath' => 'new.md', 'sha256' => str_repeat('a', 64)]], 'requirements' => []], new PackLedgerBuilder());
                self::fail('Expected marker replacement to fail.');
            } catch (\RuntimeException) {
                self::assertFalse($baseline->isPublishedGenerationValid($marker));
            }
        } finally {
            $this->deleteDirectory($directory);
        }
    }

    public function testAtomicWriterRejectsIdenticalCanonicalOutputDestinations(): void
    {
        $directory = $this->temporaryPacks([]);
        $output = $directory . '/ledger.json';

        try {
            $this->expectException(\InvalidArgumentException::class);
            (new LedgerArtifactWriter())->write($output, $output, ['schemaVersion' => 1, 'sources' => [], 'requirements' => []], new PackLedgerBuilder());
        } finally {
            $this->deleteDirectory($directory);
        }
    }

    public function testAtomicWriterRejectsMarkdownDestinationThatCollidesWithMarker(): void
    {
        $directory = $this->temporaryPacks([]);
        $json = $directory . '/ledger.json';

        try {
            $this->expectException(\InvalidArgumentException::class);
            (new LedgerArtifactWriter())->write($json, $json . '.generation.json', ['schemaVersion' => 1, 'sources' => [], 'requirements' => []], new PackLedgerBuilder());
        } finally {
            $this->deleteDirectory($directory);
        }
    }

    public function testMarkerValidationRejectsTamperedArtifactPathCollisions(): void
    {
        $directory = $this->temporaryPacks([]);
        $json = $directory . '/ledger.json';
        $markdown = $directory . '/ledger.md';

        try {
            $writer = new LedgerArtifactWriter();
            $marker = $writer->write($json, $markdown, ['schemaVersion' => 1, 'sources' => [], 'requirements' => []], new PackLedgerBuilder());
            $contents = json_decode((string) file_get_contents($marker), true, flags: JSON_THROW_ON_ERROR);
            $contents['markdown'] = $contents['json'];
            file_put_contents($marker, json_encode($contents, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . "\n");

            self::assertFalse($writer->isPublishedGenerationValid($marker));
        } finally {
            $this->deleteDirectory($directory);
        }
    }

    public function testPortableMarkerValidatesAfterGenerationDirectoryMoves(): void
    {
        $source = $this->temporaryPacks([]);
        $destination = $this->temporaryPacks([]);

        try {
            $writer = new LedgerArtifactWriter();
            $marker = $writer->write($source . '/ledger.json', $source . '/ledger.md', ['schemaVersion' => 1, 'sources' => [], 'requirements' => []], new PackLedgerBuilder());
            foreach (['ledger.json', 'ledger.md', basename($marker)] as $file) {
                copy($source . '/' . $file, $destination . '/' . $file);
                unlink($source . '/' . $file);
            }

            self::assertTrue($writer->isPublishedGenerationValid($destination . '/' . basename($marker)));
        } finally {
            $this->deleteDirectory($source);
            $this->deleteDirectory($destination);
        }
    }

    public function testMarkerValidationRejectsAbsoluteAndTraversalArtifactPaths(): void
    {
        $directory = $this->temporaryPacks([]);
        $outside = dirname($directory) . '/outside-ledger.json';

        try {
            $writer = new LedgerArtifactWriter();
            $marker = $writer->write($directory . '/ledger.json', $directory . '/ledger.md', ['schemaVersion' => 1, 'sources' => [], 'requirements' => []], new PackLedgerBuilder());
            $contents = json_decode((string) file_get_contents($marker), true, flags: JSON_THROW_ON_ERROR);
            $contents['json']['path'] = $directory . '/ledger.json';
            file_put_contents($marker, json_encode($contents, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . "\n");
            self::assertFalse($writer->isPublishedGenerationValid($marker));

            copy($directory . '/ledger.json', $outside);
            $contents['json']['path'] = '../' . basename($outside);
            $contents['json']['sha256'] = hash_file('sha256', $outside);
            file_put_contents($marker, json_encode($contents, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . "\n");
            $initialDirectory = getcwd();
            chdir($directory);
            try {
                self::assertFalse($writer->isPublishedGenerationValid($marker));
            } finally {
                chdir($initialDirectory);
            }
        } finally {
            if (file_exists($outside)) unlink($outside);
            $this->deleteDirectory($directory);
        }
    }

    public function testPortableMarkerBytesAreStableAcrossAbsoluteRoots(): void
    {
        $first = $this->temporaryPacks([]);
        $second = $this->temporaryPacks([]);

        try {
            $writer = new LedgerArtifactWriter();
            $firstMarker = $writer->write($first . '/ledger.json', $first . '/ledger.md', ['schemaVersion' => 1, 'sources' => [], 'requirements' => []], new PackLedgerBuilder());
            $secondMarker = $writer->write($second . '/ledger.json', $second . '/ledger.md', ['schemaVersion' => 1, 'sources' => [], 'requirements' => []], new PackLedgerBuilder());
            self::assertSame(hash_file('sha256', $firstMarker), hash_file('sha256', $secondMarker));
        } finally {
            $this->deleteDirectory($first);
            $this->deleteDirectory($second);
        }
    }

    public function testCliProducesParseableByteStableArtifacts(): void
    {
        $packs = $this->temporaryPacks(['requirements.md' => "# One\n- Two\n"]);
        $output = $this->temporaryPacks([]);
        $json = $output . '/ledger.json';
        $markdown = $output . '/ledger.md';

        try {
            $command = implode(' ', [
                escapeshellarg(PHP_BINARY),
                escapeshellarg(dirname(__DIR__, 3) . '/app/bin/build-pack-ledger.php'),
                '--packs=' . escapeshellarg($packs),
                '--json=' . escapeshellarg($json),
                '--markdown=' . escapeshellarg($markdown),
            ]);
            exec($command, $firstOutput, $firstExit);
            self::assertSame(0, $firstExit, implode("\n", $firstOutput));
            self::assertIsArray(json_decode((string) file_get_contents($json), true, flags: JSON_THROW_ON_ERROR));
            $marker = realpath($output) . DIRECTORY_SEPARATOR . 'ledger.json.generation.json';
            self::assertTrue((new LedgerArtifactWriter())->isPublishedGenerationValid($marker));
            $firstHashes = [hash_file('sha256', $json), hash_file('sha256', $markdown), hash_file('sha256', $marker)];

            exec($command, $secondOutput, $secondExit);
            self::assertSame(0, $secondExit, implode("\n", $secondOutput));
            self::assertSame($firstHashes, [hash_file('sha256', $json), hash_file('sha256', $markdown), hash_file('sha256', $marker)]);
            self::assertStringEndsWith("\n", (string) file_get_contents($markdown));
        } finally {
            $this->deleteDirectory($packs);
            $this->deleteDirectory($output);
        }
    }

    /** @param array<string, string> $files */
    private function temporaryPacks(array $files): string
    {
        $directory = sys_get_temp_dir() . '/architex-pack-ledger-' . bin2hex(random_bytes(8));
        mkdir($directory, 0777, true);
        foreach ($files as $path => $content) {
            $target = $directory . '/' . $path;
            $parent = dirname($target);
            if (!is_dir($parent)) {
                mkdir($parent, 0777, true);
            }
            file_put_contents($target, $content);
        }

        return $directory;
    }

    private function deleteDirectory(string $path): void
    {
        $entries = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );
        foreach ($entries as $entry) {
            $entry->isDir() ? rmdir($entry->getPathname()) : unlink($entry->getPathname());
        }
        rmdir($path);
    }
}
