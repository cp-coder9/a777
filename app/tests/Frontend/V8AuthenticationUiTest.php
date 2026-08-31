<?php

declare(strict_types=1);

namespace Architex\Tests\Frontend;

use PHPUnit\Framework\TestCase;

final class V8AuthenticationUiTest extends TestCase
{
    public function testMatchesTheCanonicalV8AuthenticationCompositionAndCopy(): void
    {
        $javascript = strtolower((string) file_get_contents(dirname(__DIR__, 2) . '/public/assets/js/public-access.js'));
        foreach ([
            'v8-signin', 'v8-signin-panel', 'v8-signin-identity', 'v8-signin-form',
            'v8-mode-switch', 'v8-morph-zone', 'v8-status-stack', 'v8-access-badge',
            'v8 secure access', 'v8 secure enrolment', 'welcome back', 'join architex os',
            'identity boundary active', 'audit trail ready', 'workspace encrypted',
            '01 / authenticate', '01 / enrol', 'enter architex os', 'create your account',
            'use your organisation credentials to mount the v8 shell.',
            'protected by role-based access and immutable session logging.',
        ] as $token) {
            self::assertStringContainsString($token, $javascript);
        }
    }

    public function testConnectsSignInAndRegistrationToRealFirstPartyContracts(): void
    {
        $javascript = strtolower((string) file_get_contents(dirname(__DIR__, 2) . '/public/assets/js/public-access.js'));
        foreach ([
            '/legal-notices/signup', '/sessions', '/registrations',
            'acceptednoticeversions', 'organisationname', 'organisationslug',
            'fullname', 'rolekey', 'profile', 'pending_email_verification',
        ] as $token) {
            self::assertStringContainsString($token, $javascript);
        }
        self::assertStringNotContainsString('prototype verification token', $javascript);
        self::assertStringNotContainsString('nvapi-', $javascript);
        self::assertStringNotContainsString('localstorage', $javascript);
    }

    public function testOwnsCanonicalResponsiveStyles(): void
    {
        $css = strtolower((string) file_get_contents(dirname(__DIR__, 2) . '/public/assets/css/public-access.css'));
        foreach ([
            '.v8-signin', '.v8-signin-panel', 'grid-template-columns:.95fr 1.05fr',
            '.v8-signin-identity', 'background:#0a4540', '.v8-signin-form',
            '.v8-enter-button', '.v8-mode-switch', '@media (max-width: 760px)',
            '@media (prefers-reduced-motion: reduce)',
        ] as $token) {
            self::assertStringContainsString($token, $css);
        }
    }
}
