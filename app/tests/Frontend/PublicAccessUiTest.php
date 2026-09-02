<?php

declare(strict_types=1);

namespace Architex\Tests\Frontend;

use PHPUnit\Framework\TestCase;

final class PublicAccessUiTest extends TestCase
{
    public function testShellBootsBehindCanonicalLocalLanding(): void
    {
        $public = dirname(__DIR__, 2) . '/public';
        $app = (string) file_get_contents($public . '/app.php');
        $landing = @file_get_contents($public . '/preview3.html');

        self::assertIsString($landing, 'The canonical landing must be vendored for shared hosting.');
        self::assertStringContainsString('<title>Architex — Interactive Public Landing Concept</title>', $landing);
        self::assertStringContainsString('id="publicAccess"', $app);
        self::assertStringContainsString('id="publicLandingFrame"', $app);
        self::assertStringContainsString('src="/preview3.html"', $app);
        self::assertStringContainsString('data-access-state="checking-session"', $app);
        self::assertStringContainsString('href="/assets/css/public-access.css"', $app);
        self::assertStringContainsString('src="/assets/js/public-access.js"', $app);
    }

    public function testPublicControllerOwnsLandingAuthAndWorkspaceStates(): void
    {
        $javascript = strtolower((string) @file_get_contents(dirname(__DIR__, 2) . '/public/assets/js/public-access.js'));

        foreach ([
            'checking-session', 'public', 'auth', 'workspace',
            'showlanding', 'showauth', 'showworkspace',
            'publiclandingframe', 'prefers-reduced-motion',
            'sign in', 'sign up', 'create account',
        ] as $token) {
            self::assertStringContainsString($token, $javascript);
        }
        self::assertStringNotContainsString('nvapi-', $javascript);
        self::assertStringNotContainsString('localstorage', $javascript);
    }

    public function testSignedOutSessionDelegatesToPublicAccessController(): void
    {
        $javascript = strtolower((string) file_get_contents(dirname(__DIR__, 2) . '/public/assets/js/shell.js'));

        self::assertStringContainsString('architexpublicaccess.showlanding', $javascript);
        self::assertStringContainsString('architexpublicaccess.showworkspace', $javascript);
    }

    public function testLandingActionsBindWhenIframeLoadedBeforeController(): void
    {
        $javascript = (string) file_get_contents(dirname(__DIR__, 2) . '/public/assets/js/public-access.js');

        self::assertMatchesRegularExpression(
            "/landing\\?\\.addEventListener\\('load', bindLandingActions\\);\\s*bindLandingActions\\(\\);/",
            $javascript,
        );
    }
}
