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
        self::assertStringContainsString('name="projectSelector" aria-label="Active project"', $html);
        self::assertStringContainsString('name="feedback" aria-label="Feedback description"', $html);
        self::assertStringContainsString('href="/assets/css/community.css"', $html);
        self::assertStringContainsString('href="/assets/css/demo-sandbox.css"', $html);
        self::assertStringContainsString('src="/assets/js/demo-sandbox.js"', $html);
        self::assertStringContainsString('TOOLS.drawing_compliance_navigator=', $html);
        self::assertStringContainsString("'planning','municipal','drawing_compliance_navigator','xa'", $html);
        $css = file_get_contents($root . '/app/public/assets/css/architex-v1.css');
        self::assertIsString($css);
        self::assertStringContainsString('/* Release 2 project-team workspace */', $css);
        self::assertStringContainsString('/* Release 13 standalone tool tiles */', $css);
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
