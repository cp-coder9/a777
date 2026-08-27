<?php

declare(strict_types=1);

namespace Architex\Tests\Frontend;

use PHPUnit\Framework\TestCase;

final class V1ExtractionTest extends TestCase
{
    public function testGeneratedShellRetainsCanonicalStructureAndRegistry(): void
    {
        $root = dirname(__DIR__, 3);
        $html = file_get_contents($root . '/app/public/app.php');
        self::assertIsString($html);
        foreach (['os-rail', 'navigator', 'topbar', 'main', 'inspector'] as $selector) {
            self::assertStringContainsString($selector, $html);
        }
        self::assertStringContainsString('name="viewport" content="width=device-width, initial-scale=1.0"', $html);
        $registry = json_decode((string) file_get_contents($root . '/app/public/assets/tool-registry.json'), true, flags: JSON_THROW_ON_ERROR);
        self::assertCount(45, $registry['tools']);
        self::assertContains('project_passport', array_column($registry['tools'], 'id'));
    }

    public function testGeneratedAssetsDeclareTheirCanonicalSourceHash(): void
    {
        $root = dirname(__DIR__, 3);
        $expected = hash_file('sha256', $root . '/v1.html');
        $manifest = json_decode((string) file_get_contents($root . '/app/public/assets/v1-manifest.json'), true, flags: JSON_THROW_ON_ERROR);
        self::assertSame($expected, $manifest['sourceSha256']);
        self::assertSame(45, $manifest['toolCount']);
        self::assertSame(11, $manifest['prototypeLiveCount']);
        self::assertSame(34, $manifest['scaffoldCount']);
    }
}
