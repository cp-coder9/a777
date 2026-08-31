<?php

declare(strict_types=1);

namespace Architex\Tests\Frontend;

use PHPUnit\Framework\TestCase;

final class WingmanUiTest extends TestCase
{
    public function testLoadsPersistentBirdWingmanAfterTheFinalShellRenderer(): void
    {
        $app = (string) file_get_contents(dirname(__DIR__, 2) . '/public/app.php');
        self::assertGreaterThan(strpos($app, '/assets/css/flightpath-ui.css'), strpos($app, '/assets/css/wingman.css'));
        self::assertGreaterThan(strpos($app, '/assets/js/flightpath-shell.js'), strpos($app, '/assets/js/wingman.js'));
        $svg = @file_get_contents(dirname(__DIR__, 2) . '/public/assets/img/wingman-bird.svg');
        self::assertIsString($svg);
        self::assertStringContainsString('<svg', $svg);
        self::assertStringContainsString('aria-label="Architex Wingman bird"', $svg);
    }

    public function testOwnsQuietSuggestionAndExpandedGuidedCopilotStates(): void
    {
        $javascript = $this->javascript();
        foreach ([
            'wingmanassistant', 'document.body.appendchild', 'data-wingman-state',
            "'quiet'", "'suggestion'", "'expanded'", 'wingman-bird.svg',
            "setattribute('role', 'complementary')", 'aria-live="polite"', 'aria-expanded',
            'const previousrenderall = window.renderall',
        ] as $token) self::assertStringContainsString($token, strtolower($javascript));
    }

    public function testUsesAuthenticatedApiStructuredContextAndExplicitNavigationClicks(): void
    {
        $javascript = $this->javascript();
        foreach ([
            '/wingman/bootstrap', '/wingman/messages', '/wingman/conversations/', '/feedback',
            'selectedrecordid', 'projectid', 'route:', 'data-wingman-navigate',
            'flightpathopendestination', 'window.opentool', 'window.openglobal',
            'addeventlistener(\'click\'', 'textcontent',
            'configuration_required', 'provider_unavailable', 'rate_limited',
            'context_denied', 'no_authorised_evidence',
        ] as $token) self::assertStringContainsString($token, strtolower($javascript));
        self::assertStringNotContainsString('document.documentelement.outerhtml', strtolower($javascript));
        self::assertStringNotContainsString('document.body.innerhtml', strtolower($javascript));
        self::assertStringNotContainsString('localstorage', strtolower($javascript));
    }

    public function testKeepsWingmanAbovePanelsButBelowGovernedDialogsAndResponsive(): void
    {
        $css = strtolower((string) @file_get_contents(dirname(__DIR__, 2) . '/public/assets/css/wingman.css'));
        foreach (['.wingman-assistant', 'z-index:4500', '.wingman-panel', '.wingman-bird', '.wingman-suggestion', '@media (max-width: 760px)', '@media (prefers-reduced-motion: reduce)'] as $token) {
            self::assertStringContainsString($token, $css);
        }
        self::assertStringNotContainsString('z-index:8000', $css);
    }

    private function javascript(): string
    {
        $javascript = @file_get_contents(dirname(__DIR__, 2) . '/public/assets/js/wingman.js');
        self::assertIsString($javascript, 'The persistent Wingman module must exist.');
        return $javascript;
    }
}
