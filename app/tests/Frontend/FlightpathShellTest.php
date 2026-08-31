<?php

declare(strict_types=1);

namespace Architex\Tests\Frontend;

use PHPUnit\Framework\TestCase;

final class FlightpathShellTest extends TestCase
{
    public function testLoadsFlightpathShellAfterAllExistingFeatureModules(): void
    {
        $app = (string) file_get_contents(dirname(__DIR__, 2) . '/public/app.php');
        $features = strpos($app, '/assets/js/demo-sandbox.js');
        $flightpath = strpos($app, '/assets/js/flightpath-shell.js');

        self::assertNotFalse($features);
        self::assertNotFalse($flightpath);
        self::assertGreaterThan($features, $flightpath);
    }

    public function testEnhancesTheFinalRendererWithLifecycleFirstNavigation(): void
    {
        $javascript = $this->javascript();
        foreach ([
            '/assets/route-placement.json',
            'const previousrenderall = window.renderall',
            'window.renderall = function',
            'datum flightpath',
            'fp-global-destination',
            'fp-lifecycle-nav',
            'aria-label="datum flightpath"',
            'aria-current',
            'flightpathopendestination',
            'selectprojectstage',
            'fp-page-family--',
            'dataset.pagefamily',
            'requestanimationframe',
        ] as $token) {
            self::assertStringContainsString($token, strtolower($javascript));
        }
    }

    public function testFailsBackToExistingNavigationWhenPlacementCannotLoad(): void
    {
        $javascript = $this->javascript();
        self::assertStringContainsString('flightpath-placement-unavailable', $javascript);
        self::assertStringContainsString('existing navigation remains available', $javascript);
        self::assertStringNotContainsString('innerHTML = \'\'', $javascript);
    }

    private function javascript(): string
    {
        $javascript = @file_get_contents(dirname(__DIR__, 2) . '/public/assets/js/flightpath-shell.js');
        self::assertIsString($javascript, 'The lifecycle-first shell module must exist.');
        return $javascript;
    }
}
