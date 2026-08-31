<?php

declare(strict_types=1);

namespace Architex\Tests\Frontend;

use PHPUnit\Framework\TestCase;

final class FlightpathVisualSystemTest extends TestCase
{
    public function testLoadsTheFlightpathVisualLayerAfterFeatureStyles(): void
    {
        $app = (string) file_get_contents(dirname(__DIR__, 2) . '/public/app.php');
        $feature = strpos($app, '/assets/css/job-ticket-marketplace.css');
        $flightpath = strpos($app, '/assets/css/flightpath-ui.css');

        self::assertNotFalse($feature);
        self::assertNotFalse($flightpath);
        self::assertGreaterThan($feature, $flightpath);
    }

    public function testDefinesTheApprovedV1TokensAndTypography(): void
    {
        $css = $this->css();
        foreach ([
            '--fp-ink:#102033',
            '--fp-datum-teal:#0f817b',
            '--fp-marker-aqua:#19b7b0',
            '--fp-tracing-wash:#eaf8f6',
            '--fp-review-amber:#ffb020',
            '--fp-paper:#ffffff',
            '--fp-heading:"bahnschrift semicondensed"',
            '--fp-body:aptos,"segoe ui"',
            '--fp-mono:consolas',
        ] as $token) {
            self::assertStringContainsString($token, $css);
        }
    }

    public function testDefinesAccessibleSharedInputsAndPagePrimitives(): void
    {
        $css = $this->css();
        foreach ([
            '.fp-page-header', '.fp-workflow', '.fp-action-bar', '.fp-card', '.fp-table-wrap',
            '.fp-filter-bar', '.fp-field', '.fp-field-help', '.fp-field-error', '.fp-empty',
            '.fp-loading', '.fp-error', '.fp-dialog', '.fp-page-family--orient',
            '.fp-page-family--find', '.fp-page-family--create', '.fp-page-family--review',
            '.fp-page-family--administer', ':focus-visible', '[aria-invalid="true"]',
            'input[type="search"]', 'input[type="file"]', 'textarea', 'select',
        ] as $token) {
            self::assertStringContainsString($token, $css);
        }
        self::assertStringContainsString('@media (prefers-reduced-motion: reduce)', $css);
        self::assertStringContainsString('@media (max-width: 760px)', $css);
        self::assertStringContainsString('overflow-x:clip', $css);
    }

    private function css(): string
    {
        $css = @file_get_contents(dirname(__DIR__, 2) . '/public/assets/css/flightpath-ui.css');
        self::assertIsString($css, 'The shared Datum Flightpath stylesheet must exist.');
        return strtolower($css);
    }
}
