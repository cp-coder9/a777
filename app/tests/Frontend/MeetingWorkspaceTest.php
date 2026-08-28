<?php

declare(strict_types=1);

namespace Architex\Tests\Frontend;

use PHPUnit\Framework\TestCase;

final class MeetingWorkspaceTest extends TestCase
{
    public function testCanonicalShellExposesGovernedPersistentMeetingLifecycle(): void
    {
        $shell = (string) file_get_contents(dirname(__DIR__, 2) . '/public/assets/js/shell.js');
        $styles = (string) file_get_contents(dirname(__DIR__, 2) . '/public/assets/css/architex-v1.css');

        self::assertStringContainsString('id="governedMeetingForm"', $shell);
        self::assertStringContainsString("api.post('/meetings'", $shell);
        self::assertStringContainsString('/consents', $shell);
        self::assertStringContainsString('/start', $shell);
        self::assertStringContainsString('/conclude', $shell);
        self::assertStringContainsString('/outcomes/${index}/review', $shell);
        self::assertStringContainsString('/minutes/publish', $shell);
        self::assertStringContainsString('reviewerConfirmed', $shell);
        self::assertStringContainsString('Transcript source', $shell);
        self::assertStringContainsString('No live media provider is connected', $shell);
        self::assertStringContainsString('.governed-meeting-layout', $styles);
    }
}
