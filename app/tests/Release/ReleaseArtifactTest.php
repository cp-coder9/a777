<?php

declare(strict_types=1);

namespace Architex\Tests\Release;

use Architex\Infrastructure\Deployment\ReleaseBuilder;
use Architex\Infrastructure\Deployment\ReleaseVerifier;
use FilesystemIterator;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

final class ReleaseArtifactTest extends TestCase
{
    private string $outputRoot;

    protected function setUp(): void
    {
        $this->outputRoot = dirname(__DIR__, 2) . '/storage/tmp/release-test-' . bin2hex(random_bytes(8));
        mkdir($this->outputRoot, 0700, true);
    }

    protected function tearDown(): void
    {
        if (!is_dir($this->outputRoot)) return;
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($this->outputRoot, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($iterator as $item) $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
        rmdir($this->outputRoot);
    }

    public function testArtifactContainsRuntimeAndExcludesSecretsTestsAndWritableData(): void
    {
        $root = dirname(__DIR__, 3);
        $installer = static function (string $release): void {
            mkdir($release . '/vendor/composer', 0700, true);
            file_put_contents($release . '/vendor/autoload.php', "<?php return true;\n");
            file_put_contents($release . '/vendor/composer/installed.json', json_encode(['packages'=>[],'dev'=>false], JSON_THROW_ON_ERROR));
        };
        $release = (new ReleaseBuilder($root, $this->outputRoot, $installer))->build('test-release');

        self::assertFileExists($release . '/public/index.php');
        self::assertFileExists($release . '/public/maintenance.php');
        self::assertFileExists($release . '/vendor/autoload.php');
        self::assertFileExists($release . '/release-manifest.json');
        self::assertFileDoesNotExist($release . '/.env');
        self::assertDirectoryDoesNotExist($release . '/tests');
        self::assertDirectoryDoesNotExist($release . '/storage/files');
        self::assertDirectoryDoesNotExist($release . '/vendor/phpunit');
        self::assertTrue((new ReleaseVerifier())->verify($release)->valid);
    }

    public function testRejectsUnsafeVersion(): void
    {
        $root = dirname(__DIR__, 3);
        $installer = static function (string $release): void {
            mkdir($release . '/vendor', 0700, true);
            file_put_contents($release . '/vendor/autoload.php', "<?php return true;\n");
        };
        $builder = new ReleaseBuilder($root, $this->outputRoot, $installer);
        $this->expectException(\InvalidArgumentException::class);
        $builder->build('../escape');
    }

    public function testDetectsPostBuildTampering(): void
    {
        $root = dirname(__DIR__, 3);
        $installer = static function (string $release): void {
            mkdir($release . '/vendor', 0700, true);
            file_put_contents($release . '/vendor/autoload.php', "<?php return true;\n");
        };
        $release = (new ReleaseBuilder($root, $this->outputRoot, $installer))->build('tamper-test');
        file_put_contents($release . '/public/index.php', "\nTAMPERED", FILE_APPEND);

        $report = (new ReleaseVerifier())->verify($release);

        self::assertFalse($report->valid);
        self::assertContains('manifest_mismatch', $report->errors);
    }
}
