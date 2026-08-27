<?php

declare(strict_types=1);

namespace Architex\Tests\Requirements;

use Architex\Requirements\PackLedgerBuilder;
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
        self::assertSame($ledger, (new PackLedgerBuilder())->build(dirname(__DIR__, 3) . '/packs'));
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
