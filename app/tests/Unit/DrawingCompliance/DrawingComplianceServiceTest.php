<?php

declare(strict_types=1);

namespace Architex\Tests\Unit\DrawingCompliance;

use Architex\Platform\DrawingCompliance\DrawingComplianceService;
use PHPUnit\Framework\TestCase;

final class DrawingComplianceServiceTest extends TestCase
{
    public function testIllustrativeSourcesAndPartKBoundaryOutcomesFailClosed(): void
    {
        $service = new DrawingComplianceService();
        $source = $service->sourceRegistry()[0];
        self::assertSame('illustrative_poc_until_verified', $source['status']);
        self::assertFalse($source['productionRelianceAllowed']);

        self::assertSame('needs_input', $service->evaluateBoundaryWall(['unitType'=>'solid'])['status']);
        self::assertSame('needs_input', $service->evaluateBoundaryWall(['unitType'=>'solid','thicknessMm'=>140,'heightM'=>null,'earthRetained'=>false])['status']);
        self::assertSame('watch', $service->evaluateBoundaryWall(['unitType'=>'solid','thicknessMm'=>140,'heightM'=>1.0,'earthRetained'=>true])['status']);
        self::assertSame('fail', $service->evaluateBoundaryWall(['unitType'=>'solid','thicknessMm'=>90,'heightM'=>1.2,'earthRetained'=>false])['status']);
        self::assertSame('watch', $service->evaluateBoundaryWall(['unitType'=>'solid','thicknessMm'=>140,'heightM'=>1.7,'earthRetained'=>false,'pierSpacingM'=>2.2])['status']);
        self::assertSame('pass', $service->evaluateBoundaryWall(['unitType'=>'solid','thicknessMm'=>90,'heightM'=>0.8,'earthRetained'=>false])['status']);
    }

    public function testDrawingReviewCoversOccupancyPackageReferencesAndSplumaWithoutApprovalClaims(): void
    {
        $service = new DrawingComplianceService();
        $review = $service->evaluate([
            'buildingDescription'=>'Alterations to a synagogue and community hall',
            'seatingCounts'=>['main_hall'=>220],
            'availableDrawingTypes'=>['general_notes_title_block','site_plan','floor_plans'],
            'drawingRegister'=>[['drawingNo'=>'A101','details'=>[['detailNo'=>'1']]]],
            'references'=>[['detailNo'=>'2','targetSheet'=>'A999']],
            'projectDescription'=>'Place of worship additions and boundary wall',
            'siteContext'=>['municipality'=>'Johannesburg','erfNumber'=>'Erf 10','currentUse'=>'dwelling','proposedUse'=>'place of worship'],
        ]);

        self::assertSame('A2', $review['occupancy']['classification']['class']);
        self::assertSame(220, $review['occupancy']['estimatedPopulation']);
        self::assertNotEmpty($review['package']['missingDrawingTypes']);
        self::assertSame(1, $review['crossReferences']['unresolvedCount']);
        self::assertContains('place_of_worship', array_column($review['landUse']['triggers'], 'trigger'));
        self::assertContains('zoning', $review['landUse']['missingInputs']);
        self::assertSame('needs_input', $review['overallStatus']);
        self::assertTrue($review['professionalReviewRequired']);
        self::assertContains('approved by municipality', $review['answerContract']['noGoPhrases']);
        self::assertStringContainsString('cannot grant or deny municipal approval', $review['boundary']);
    }

    public function testNullPlanningValuesRemainMissingEvidence(): void
    {
        $service = new DrawingComplianceService();
        $review = $service->evaluate([
            'buildingDescription'=>'Boundary wall alteration',
            'siteContext'=>[
                'municipality'=>null,
                'erfNumber'=>null,
                'zoning'=>null,
                'landUseSchemeName'=>null,
                'currentUse'=>null,
                'proposedUse'=>null,
                'parkingProvided'=>null,
                'coverage'=>null,
                'FAR'=>null,
                'heightStoreys'=>null,
                'buildingLinesChecked'=>null,
            ],
        ]);

        self::assertCount(11, $review['landUse']['missingInputs']);
        self::assertSame('needs_input', $review['overallStatus']);
    }
}
