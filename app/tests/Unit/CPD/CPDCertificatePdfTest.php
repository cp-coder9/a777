<?php

declare(strict_types=1);

namespace Architex\Tests\Unit\CPD;

use Architex\Platform\CPD\CPDCertificatePdf;
use PHPUnit\Framework\TestCase;

final class CPDCertificatePdfTest extends TestCase
{
    public function testAwardFieldUsesTheIssuedRuleSnapshot(): void
    {
        self::assertTrue(method_exists(CPDCertificatePdf::class,'awardField'));
        self::assertSame('CPD award: 1.5 points',CPDCertificatePdf::awardField('1.5',['unitLabel'=>'points']));
        self::assertSame('CPD award: 2 hours',CPDCertificatePdf::awardField('2',['unitLabel'=>'hours']));
        self::assertSame('CPD award: 0.1 credits',CPDCertificatePdf::awardField('0.1',['unitLabel'=>'credits']));
        self::assertSame('CPD award: compliance record',CPDCertificatePdf::awardField('0',['unitLabel'=>'compliance record','calculationPolicy'=>'manual_compliance_only']));
        self::assertSame('CPD award: 2 (unit pending confirmation)',CPDCertificatePdf::awardField('2',[]));
    }

    public function testCertificateHasSafeClickableVerificationAndPaginatedLongFields(): void
    {
        self::assertTrue(class_exists(CPDCertificatePdf::class), 'Certificate PDF renderer is required');
        $url='https://example.test/api/v1/cpd/certificates/verify/'.str_repeat('A',32);
        $pdf=CPDCertificatePdf::render(['Course: Saved course title','Assessment status: passed',str_repeat('Long field ',300)],$url);
        self::assertStringStartsWith('%PDF-1.4',$pdf);
        self::assertStringContainsString('(Course: Saved course title)',$pdf);
        self::assertStringContainsString('(Assessment status: passed)',$pdf);
        self::assertStringContainsString('/Subtype /Link',$pdf);
        self::assertStringContainsString('/URI ('.$url.')',$pdf);
        self::assertGreaterThan(1,substr_count($pdf,'/Type /Page '));
    }
}
