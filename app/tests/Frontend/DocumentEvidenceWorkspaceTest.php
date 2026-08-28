<?php

declare(strict_types=1);

namespace Architex\Tests\Frontend;

use PHPUnit\Framework\TestCase;

final class DocumentEvidenceWorkspaceTest extends TestCase
{
    public function testCanonicalDocumentWorkspaceExposesEvidenceHonestTransmittalsAndCandidates(): void
    {
        $shell = file_get_contents(dirname(__DIR__, 2) . '/public/assets/js/shell.js');
        $styles = file_get_contents(dirname(__DIR__, 2) . '/public/assets/css/architex-v1.css');
        self::assertIsString($shell);
        self::assertIsString($styles);
        foreach ([
            'id="documentTransmittalForm"', 'id="documentTransmittalList"', 'Exact-revision transmittals',
            'External email delivery is not configured or claimed', '/document-transmittals',
            'id="drawingIntelligenceForm"', 'id="drawingIntelligenceList"', 'Human-entered drawing metadata candidates',
            'No OCR or AI provider result is claimed', '/drawing-intelligence-candidates',
            'provenance:\'human_entered\'', 'providerName:null', 'confidence:null', 'metadataApplied',
            'humanConfirmed:true', 'acceptDrawingIntelligenceCandidate', 'rejectDrawingIntelligenceCandidate',
        ] as $expected) self::assertStringContainsString($expected, $shell);
        self::assertStringContainsString('.document-evidence-grid', $styles);
        self::assertStringContainsString('grid-template-columns:minmax(0,.88fr) minmax(0,1.12fr)', $styles);
        self::assertStringContainsString('.document-evidence-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr))', $styles);
        self::assertStringContainsString('@media(max-width:760px)', $styles);
    }
}
