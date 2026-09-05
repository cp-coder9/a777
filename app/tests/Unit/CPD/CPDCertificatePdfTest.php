<?php

declare(strict_types=1);

namespace Architex\Tests\Unit\CPD;

use Architex\Platform\CPD\CPDCertificatePdf;
use PHPUnit\Framework\TestCase;

final class CPDCertificatePdfTest extends TestCase
{
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
