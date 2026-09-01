<?php

declare(strict_types=1);

namespace Architex\Tests\Frontend;

use PHPUnit\Framework\TestCase;

final class BirdFlocksTransitionTest extends TestCase
{
    public function testBirdFlocksTransitionImplementsThePackContract(): void
    {
        $public = dirname(__DIR__, 2) . '/public';
        $app = strtolower((string) file_get_contents($public . '/app.php'));
        $javascript = strtolower((string) file_get_contents($public . '/assets/js/public-access.js'));
        $css = strtolower((string) file_get_contents($public . '/assets/css/public-access.css'));

        self::assertStringContainsString('id="birdflocktransition"', $app);
        self::assertStringContainsString('aria-hidden="true"', $app);
        foreach (['bird-flock-ghost', 'bird-flock-fragment', '24', '2400', '.bird-orbit'] as $token) {
            self::assertStringContainsString($token, $javascript);
        }
        foreach (['.bird-flock-transition', '.bird-flock-ghost', '.bird-flock-fragment',
            'cubic-bezier', 'filter:blur(6px)', 'prefers-reduced-motion'] as $token) {
            self::assertStringContainsString($token, $css);
        }
        self::assertStringNotContainsString('math.random', $javascript);
    }
}
