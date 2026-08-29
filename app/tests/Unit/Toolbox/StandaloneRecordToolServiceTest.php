<?php

declare(strict_types=1);

namespace Architex\Tests\Unit\Toolbox;

use Architex\Platform\Toolbox\StandaloneRecordToolService;
use Architex\Platform\Toolbox\ToolException;
use PHPUnit\Framework\TestCase;

final class StandaloneRecordToolServiceTest extends TestCase
{
    public function testAllThirtyFourRemainingPackToolsHaveProductionSchemas(): void
    {
        $definitions=(new StandaloneRecordToolService())->definitions();
        self::assertCount(34,$definitions);
        foreach(['drawing_register','technical_brief','doc_control_issue','cpd_standalone','soft_cost_estimator','brief_wizard','proposal_comparison','progress_viewer','payment_dashboard','rfi_generator','workforce_timesheet','plant_register','tender_bid_bench','snag_creator','material_procurement','payment_claim_builder','shop_drawing_submission','package_scope_viewer','snag_evidence_upload','rfi_response','catalogue_manager','quote_response','delivery_note','warranty_upload','cad_upload_check','freelancer_timesheet','deliverable_submission','freelancer_resource_centre','admin_governance','audit_trail_viewer','ai_review_queue','payment_rate_config','user_verification_console','fee_tariff_editor']as$id)self::assertArrayHasKey($id,$definitions);
        foreach($definitions as$definition){self::assertNotEmpty($definition['inputSchema']);self::assertCount(3,$definition['tags']);self::assertStringStartsWith('/toolbox/standalone/',$definition['route']);}
    }

    public function testRfiGeneratorBuildsAStableHumanEnteredDraft(): void
    {
        $output=(new StandaloneRecordToolService())->calculate('rfi_generator',['reference'=>'RFI-017','subject'=>'Slab edge detail','question'=>'Confirm the required waterproofing termination.','requiredBy'=>'2026-09-04','details'=>'Drawing A-412 conflicts with specification section 7.']);
        self::assertSame('rfi',$output['recordType']);self::assertSame('RFI-017',$output['reference']);self::assertSame('draft',$output['status']);self::assertTrue($output['humanEntered']);self::assertFalse($output['issueEligible']);self::assertMatchesRegularExpression('/\A[a-f0-9]{64}\z/',$output['recordChecksum']);
    }

    public function testTimesheetAndSoftCostCalculationsUseIntegerCents(): void
    {
        $service=new StandaloneRecordToolService();
        $timesheet=$service->calculate('workforce_timesheet',['employee'=>'A Worker','periodStart'=>'2026-08-24','periodEnd'=>'2026-08-30','regularHours'=>40,'overtimeHours'=>5,'hourlyRate'=>250]);
        self::assertSame(1_187_500,$timesheet['totalCents']);self::assertSame(1.5,$timesheet['overtimeMultiplier']);
        $costs=$service->calculate('soft_cost_estimator',['constructionValue'=>2_500_000,'professionalPercent'=>12,'municipalCosts'=>80_000,'contingencyPercent'=>10]);
        self::assertSame(30_000_000,$costs['professionalFeesCents']);self::assertSame(41_800_000,$costs['totalCents']);
    }

    public function testMissingFieldsUnknownToolsAndInvalidDatesFailClosed(): void
    {
        $service=new StandaloneRecordToolService();
        foreach([['unknown',[]],['rfi_generator',['reference'=>'RFI-1']],['workforce_timesheet',['employee'=>'A','periodStart'=>'not-a-date','periodEnd'=>'2026-08-30','regularHours'=>1,'overtimeHours'=>0,'hourlyRate'=>10]]]as[$id,$input]){
            try{$service->calculate($id,$input);self::fail('Invalid standalone record input must fail.');}catch(ToolException$exception){self::assertContains($exception->reason,['unknown_tool','invalid_tool_input']);}
        }
    }
}
