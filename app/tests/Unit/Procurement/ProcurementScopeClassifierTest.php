<?php

declare(strict_types=1);

namespace Architex\Tests\Unit\Procurement;

use Architex\Platform\Procurement\ProcurementException;
use Architex\Platform\Procurement\ProcurementScopeClassifier;
use PHPUnit\Framework\TestCase;

final class ProcurementScopeClassifierTest extends TestCase
{
    public function testClassifiesTypeMethodParticipantsAndTransparentRiskFlags(): void
    {
        $result=(new ProcurementScopeClassifier())->classify([
            'procurementNeed'=>'Appoint main contractor and structural steel trade support',
            'estimatedValueZar'=>2_800_000,
            'complexity'=>'high',
            'urgency'=>'normal',
            'requiredTrades'=>['general_building','structural_steel','plumbing'],
            'requiredSpecialists'=>['structural_engineer'],
            'municipalReadinessScore'=>72,
        ]);

        self::assertSame('main_contractor',$result['procurementType']);
        self::assertSame('open_tender',$result['recommendedMethod']);
        self::assertContains('subcontractor',$result['requiredParticipants']);
        self::assertContains('specialist_consultant',$result['requiredParticipants']);
        self::assertTrue($result['humanMethodSelectionRequired']);
        self::assertFalse($result['statutoryThresholdClaimed']);
        self::assertSame('architex-organisational-default-v1',$result['classificationPolicy']['version']);
        self::assertNotEmpty($result['riskFlags']);
    }

    public function testEmergencyDoesNotSilentlyAuthoriseDirectAppointment(): void
    {
        $result=(new ProcurementScopeClassifier())->classify([
            'procurementNeed'=>'Urgent plumbing repair',
            'estimatedValueZar'=>45_000,
            'complexity'=>'low',
            'urgency'=>'emergency',
            'requiredTrades'=>['plumbing'],
            'requiredSpecialists'=>[],
            'municipalReadinessScore'=>100,
        ]);

        self::assertSame('trade_package',$result['procurementType']);
        self::assertSame('direct_appointment',$result['recommendedMethod']);
        self::assertTrue($result['directAppointmentRationaleRequired']);
        self::assertTrue($result['humanMethodSelectionRequired']);
    }

    public function testInvalidUnboundedInputsFailClosed(): void
    {
        $this->expectException(ProcurementException::class);
        (new ProcurementScopeClassifier())->classify([
            'procurementNeed'=>'Unknown',
            'estimatedValueZar'=>-1,
            'complexity'=>'invented',
            'urgency'=>'normal',
            'requiredTrades'=>[],
            'requiredSpecialists'=>[],
            'municipalReadinessScore'=>101,
        ]);
    }
}
