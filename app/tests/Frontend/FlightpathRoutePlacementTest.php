<?php

declare(strict_types=1);

namespace Architex\Tests\Frontend;

use PHPUnit\Framework\TestCase;

final class FlightpathRoutePlacementTest extends TestCase
{
    /** @var array<string,mixed> */
    private array $manifest;
    private string $publicRoot;

    protected function setUp(): void
    {
        $this->publicRoot = dirname(__DIR__, 2) . '/public';
        $raw = @file_get_contents($this->publicRoot . '/assets/route-placement.json');
        self::assertIsString($raw, 'The Datum Flightpath route-placement manifest must exist.');
        $decoded = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($decoded);
        $this->manifest = $decoded;
    }

    public function testDefinesTheApprovedLifecycleAndGlobalNavigation(): void
    {
        self::assertSame(
            ['brief', 'appoint', 'design', 'comply', 'procure', 'build', 'pay', 'close-out'],
            array_column($this->manifest['stages'] ?? [], 'id'),
        );
        self::assertSame(
            ['command-centre', 'projects', 'workspace-tools', 'collaboration', 'knowledge-cpd', 'admin-settings'],
            array_column($this->manifest['globalDestinations'] ?? [], 'id'),
        );
    }

    public function testPlacesEveryCurrentGlobalRouteExactlyOnce(): void
    {
        $app = file_get_contents($this->publicRoot . '/app.php');
        self::assertIsString($app);
        self::assertSame(1, preg_match('/const globalItems=\[(.*?)\];/s', $app, $match));
        preg_match_all('/\bid:["\']([a-z0-9_-]+)["\']/', $match[1], $ids);

        $placed = array_column($this->manifest['globalRoutes'] ?? [], 'id');
        sort($ids[1]);
        sort($placed);
        self::assertSame($ids[1], $placed);
        self::assertSame($placed, array_values(array_unique($placed)), 'A global route may be placed only once.');
    }

    public function testPlacesEveryRegistryAndRuntimeToolExactlyOnce(): void
    {
        $registry = json_decode(
            (string) file_get_contents($this->publicRoot . '/assets/tool-registry.json'),
            true,
            512,
            JSON_THROW_ON_ERROR,
        );
        $expected = array_column($registry['tools'], 'id');

        foreach (['app.php', 'assets/js/shell.js'] as $relativePath) {
            $source = file_get_contents($this->publicRoot . '/' . $relativePath);
            self::assertIsString($source);
            preg_match_all('/\bTOOLS\.([A-Za-z0-9_]+)\s*=/', $source, $matches);
            $expected = array_merge($expected, $matches[1]);
        }

        $expected = array_values(array_unique($expected));
        $placed = array_column($this->manifest['tools'] ?? [], 'id');
        sort($expected);
        sort($placed);
        self::assertSame($expected, $placed);
        self::assertSame($placed, array_values(array_unique($placed)), 'A tool may be placed only once.');
    }

    public function testEveryPlacementHasAValidDestinationStageAndPageFamily(): void
    {
        $destinations = array_column($this->manifest['globalDestinations'] ?? [], 'id');
        $stages = array_column($this->manifest['stages'] ?? [], 'id');
        $families = ['orient', 'find', 'create', 'review', 'administer'];

        foreach (array_merge($this->manifest['globalRoutes'] ?? [], $this->manifest['tools'] ?? []) as $placement) {
            self::assertContains($placement['destination'] ?? null, $destinations, 'Unknown global destination.');
            self::assertContains($placement['stage'] ?? null, array_merge($stages, ['platform']), 'Unknown lifecycle stage.');
            self::assertContains($placement['family'] ?? null, $families, 'Unknown page family.');
            self::assertIsBool($placement['requiresProject'] ?? null);
        }
    }
}
