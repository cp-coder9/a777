<?php

declare(strict_types=1);

namespace Architex\Tests\Frontend;

use PHPUnit\Framework\TestCase;

final class FlightpathCoverageTest extends TestCase
{
    public function testEveryReachableRouteIsCoveredWithoutHiddenOrphans(): void
    {
        $public = dirname(__DIR__, 2) . '/public';
        $manifest = json_decode((string) file_get_contents($public . '/assets/route-placement.json'), true, 512, JSON_THROW_ON_ERROR);
        $app = (string) file_get_contents($public . '/app.php');

        self::assertSame(1, preg_match('/const globalItems=\[(.*?)\];/s', $app, $globalMatch));
        preg_match_all('/\bid:["\']([a-z0-9_-]+)["\']/', $globalMatch[1], $globalIds);
        $placedGlobals = array_column($manifest['globalRoutes'], 'id');
        self::assertEqualsCanonicalizing($globalIds[1], $placedGlobals);

        $registry = json_decode((string) file_get_contents($public . '/assets/tool-registry.json'), true, 512, JSON_THROW_ON_ERROR);
        preg_match_all('/\bTOOLS\.([A-Za-z0-9_]+)\s*=/', $app . (string) file_get_contents($public . '/assets/js/shell.js'), $runtimeTools);
        $reachableTools = array_values(array_unique(array_merge(array_column($registry['tools'], 'id'), $runtimeTools[1])));
        $placedTools = array_column($manifest['tools'], 'id');
        self::assertEqualsCanonicalizing($reachableTools, $placedTools);
        self::assertSame(count($placedTools), count(array_unique($placedTools)));
    }

    public function testEveryRenderReceivesTitleActionContextAndStateGrammar(): void
    {
        $javascript = strtolower((string) file_get_contents(dirname(__DIR__, 2) . '/public/assets/js/flightpath-shell.js'));
        foreach ([
            'enhancepagegrammar', 'fp-page-header', 'flightpathpagetitle',
            "setattribute('aria-labelledby'", 'context navigation', 'ask wingman',
            '.empty-state,.jtm-empty,[data-empty-state]', '.loading-state,[data-loading-state]',
            '.error-state,[data-error-state],[role="alert"]', 'fp-empty', 'fp-loading', 'fp-error',
            'enhancepagegrammar();', 'requestanimationframe', 'data-flightpath-focus',
        ] as $token) self::assertStringContainsString($token, $javascript);
    }

    public function testWingmanRemainsOutsidePageRenderAndUsesStructuredContextOnly(): void
    {
        $javascript = strtolower((string) file_get_contents(dirname(__DIR__, 2) . '/public/assets/js/wingman.js'));
        foreach (['document.body.appendchild(root)', 'route:', 'projectid:', 'selectedrecordid:', 'const previousrenderall'] as $token) {
            self::assertStringContainsString($token, $javascript);
        }
        self::assertStringNotContainsString('document.body.innerhtml', $javascript);
        self::assertStringNotContainsString('outerhtml', $javascript);
    }
}
