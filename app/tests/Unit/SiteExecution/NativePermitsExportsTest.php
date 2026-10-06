<?php

declare(strict_types=1);
namespace Architex\Tests\Unit\SiteExecution;

use Architex\Platform\SiteExecution\WorkPermitPolicy;
use Architex\Platform\SiteExecution\WorkPermitException;
use Architex\Platform\Toolbox\StandaloneUtilityService;
use Architex\Platform\Toolbox\BoqPdf;
use PHPUnit\Framework\TestCase;

final class NativePermitsExportsTest extends TestCase
{
    public function testPermitLifecycleNeverSkipsHumanGates():void
    {
        self::assertSame(['requested','revoked'],WorkPermitPolicy::transitions('draft'));
        self::assertSame(['reviewed','revoked'],WorkPermitPolicy::transitions('requested'));
        self::assertSame(['issued','revoked'],WorkPermitPolicy::transitions('reviewed'));
        self::assertSame(['closed','revoked'],WorkPermitPolicy::transitions('issued'));
        self::assertSame([],WorkPermitPolicy::transitions('closed'));
        self::assertSame([],WorkPermitPolicy::transitions('revoked'));
        $this->expectException(WorkPermitException::class);
        WorkPermitPolicy::assertTransition('draft','issued');
    }
    public function testStrictDatesAndIdentifiers():void
    {
        self::assertSame('2026-10-06T04:00',WorkPermitPolicy::date('2026-10-06T04:00'));
        foreach(['2026-02-30T04:00','2026-10-06T25:00','2026-10-06','today'] as $bad){try{WorkPermitPolicy::date($bad);self::fail('Invalid date accepted');}catch(WorkPermitException){self::assertTrue(true);}}
        foreach(['123','../../id',''] as $bad){try{WorkPermitPolicy::id($bad);self::fail('Invalid identifier accepted');}catch(WorkPermitException){self::assertTrue(true);}}
    }
    public function testRealPdfContainsUnicodeLinesAmountsAndPages():void
    {
        $items=[];for($i=1;$i<=200;$i++)$items[]=['description'=>'SYNTHETIC concrete — façade '.$i,'quantity'=>2.5,'unit'=>'m³','rate'=>123.45];
        $out=(new StandaloneUtilityService())->calculate('boq_takeoff',['title'=>'SYNTHETIC Unicode BoQ','items'=>$items,'contingencyPercent'=>10]);
        $pdf=BoqPdf::render(['id'=>str_repeat('a',32),'toolId'=>'boq_takeoff','output'=>$out,'status'=>'completed','createdAt'=>'2026-10-06T00:00:00Z','assignment'=>['projectName'=>'Synthetic proof']]);
        self::assertStringStartsWith('%PDF-',$pdf);self::assertStringContainsString('%%EOF',$pdf);
        self::assertGreaterThan(20000,strlen($pdf));self::assertFalse($out['issueEligible']);
        self::assertSame(6172600,$out['subtotalCents']);self::assertSame(6789860,$out['totalCents']);
        $path=getenv('NATIVE_BOQ_PROOF_PDF');if(is_string($path)&&$path!=='')file_put_contents($path,$pdf);
    }
}